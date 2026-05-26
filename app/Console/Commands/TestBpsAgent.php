<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ai\Agents\GovtAnalyticsAgent;
use App\Ai\Tools\BpsApiTool;
use App\Models\Chat;
use App\Models\McpServer;
use App\Models\User;
use App\Services\BpsApiService;
use App\Services\DisclaimerInjectorService;
use App\Services\DisclaimerInjectorService as MethodologyRegistryServiceMock; // fallback alias
use App\Services\FactGraderService;
use App\Services\McpSseClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Messages\Message as AiMessage;
use Laravel\Ai\Messages\MessageRole;

class TestBpsAgent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bps:test-agent 
                            {--prompt= : Prompt khusus untuk diuji} 
                            {--region=denpasar : Wilayah target pengujian}
                            {--disable-builtin : Nonaktifkan built-in BPS tools secara paksa}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menguji BPS AI Agent secara komprehensif, mengukur durasi, menghitung wasted MCP calls, dan mengukur akurasi.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info("================================================================================");
        $this->info(" 🚀 MEMULAI PENGUJIAN OTOMATIS BPS AI AGENT PERFORMANCE & ACCURACY ");
        $this->info("================================================================================");

        // 1. Tentukan Prompt Pengujian
        $regionOption = $this->option('region');
        
        if (strtolower($regionOption) === 'random') {
            $randomRegency = \Illuminate\Support\Facades\DB::table('bps_regencies')->inRandomOrder()->first();
            if (!$randomRegency) {
                $this->error("❌ Tidak ada wilayah ditemukan di tabel bps_regencies!");
                return 1;
            }
            $region = strtolower(str_replace(['Kabupaten ', 'Kota '], '', $randomRegency->name));
            $defaultPrompt = "Tarik data indikator pembangunan strategis terbaru dari BPS " . $randomRegency->name;
            $this->info("🎲 Wilayah Acak Terpilih: {$randomRegency->name} (Kode BPS: {$randomRegency->code})");
        } else {
            $region = strtolower($regionOption);
            $defaultPrompt = "Tarik data indikator pembangunan strategis terbaru dari BPS kota denpasar";
            if ($region === 'mempawah') {
                $defaultPrompt = "Tarik data indikator pembangunan strategis terbaru dari BPS Kabupaten Mempawah";
            }
        }
        
        $prompt = $this->option('prompt') ?: $defaultPrompt;
        $this->line("💬 Prompt Uji: \"{$prompt}\"");
        $this->line("📍 Wilayah Target: " . ucfirst($region));

        // 2. Tentukan User & Preferensi MCP
        $user = User::first();
        if (!$user) {
            $this->error("❌ Tidak ada user ditemukan di database untuk memuat preferensi obrolan.");
            return 1;
        }
        
        $disableBuiltin = $this->option('disable-builtin') || ($user->disable_builtin_mcp ?? false);
        $this->line("🔧 Status MCP Bawaan: " . ($disableBuiltin ? 'NONAKTIF (Eksklusif MCP Dinamis)' : 'AKTIF'));

        // 3. Konfigurasi AI & Model
        $provider = config('ai.default', 'openai');
        $model = config('ai.default_model', 'gemini-3-flash');
        $providerKey = config("ai.providers.{$provider}.key");

        if (!$providerKey) {
            $this->error("❌ API Key untuk provider {$provider} belum dikonfigurasi di file .env!");
            return 1;
        }

        $this->line("🤖 AI Provider: " . strtoupper($provider) . " | Model: {$model}");
        $this->info("🔄 Menghubungkan ke Server MCP dan memuat perkakas...");

        // 4. Siapkan Tools
        $state = (object) ['stepIndex' => 0, 'steps' => []];
        $tools = [];
        $chatId = (string) Str::uuid();

        // A. Muat Built-in BPS API Tools
        if (!$disableBuiltin) {
            $bpsService = new BpsApiService((string) config('services.bps.key', ''));
            $bpsToolDefinitions = [
                ['name' => 'fetch_regional_report', 'description' => 'Mengambil daftar tabel statistik resmi BPS...'],
                ['name' => 'get_bps_indicator', 'description' => 'Mengambil data dari tabel statistik BPS tertentu...'],
                ['name' => 'compare_regencies', 'description' => 'Membandingkan data statistik BPS antar beberapa kabupaten/kota...'],
                ['name' => 'search_statistics', 'description' => 'Mencari variabel dan indikator statistik BPS...'],
            ];

            foreach ($bpsToolDefinitions as $toolDef) {
                // Filter built-in tools yang lambat & redundan dibanding execute_js
                if (in_array($toolDef['name'], ['search_statistics', 'get_bps_indicator'])) {
                    continue;
                }

                $tools[] = new BpsApiTool(
                    toolName: $toolDef['name'],
                    toolDescription: $toolDef['description'],
                    bpsService: $bpsService,
                    chatId: $chatId,
                    state: $state
                );
            }
            $filteredBuiltin = count($tools);
            $this->line("✅ BPS API Tools Bawaan berhasil dimuat ({$filteredBuiltin} perkakas, search_statistics & get_bps_indicator dinonaktifkan).");
        }

        // B. Muat Dynamic MCP Servers
        try {
            $activeServers = McpServer::where('is_active', true)->get();
            $sseClient = new McpSseClient();

            foreach ($activeServers as $server) {
                $mcpTools = $sseClient->listTools($server->url, $server->token);
                foreach ($mcpTools as $mcpTool) {
                    // Filter seluruh discovery & data fetching redundan
                    // untuk memaksa LLM menggunakan execute_js secara langsung (Zero-Discovery).
                    $forbiddenMcpTools = [
                        'bps_query',
                        'bps_get_indicator_map',
                        'bps_list_variable',
                        'bps_list_period',
                        'bps_list_vertical_var',
                        'bps_get_dynamic_data'
                    ];

                    if (in_array($mcpTool['name'], $forbiddenMcpTools)) {
                        continue;
                    }

                    $tools[] = new \App\Ai\Tools\McpSseTool(
                        toolName: $mcpTool['name'],
                        toolDescription: $mcpTool['description'] ?? 'Perkakas eksternal dari ' . $server->name,
                        rawSchema: $mcpTool['inputSchema'] ?? [],
                        serverUrl: $server->url,
                        serverToken: $server->token,
                        sseClient: $sseClient,
                        chatId: $chatId,
                        state: $state
                    );
                }
                $filteredCount = count($mcpTools) - 6; // -6 untuk tool discovery yang disaring
                $this->line("✅ Server MCP Universal '{$server->name}' berhasil dimuat ({$filteredCount} perkakas, discovery tools dinonaktifkan).");
            }
        } catch (\Exception $mcpEx) {
            $this->warn("⚠️ Peringatan saat memuat MCP eksternal: " . $mcpEx->getMessage());
        }

        $this->line("📦 Total perkakas yang terdaftar di Agent: " . count($tools));

        // 5. Jalankan AI Agent dan Ukur Waktu
        $this->info("⏳ Menjalankan AI Agent... Harap tunggu (proses ini melibatkan pemanggilan LLM & API BPS)...");
        $startTime = microtime(true);

        try {
            $tokenizer = new \App\Services\HybridTokenizerService();
            $tokens = $tokenizer->tokenize($prompt);

            $cognitiveHints = [];
            $appliedHeuristicIds = [];
            $normalizedIntent = strtolower($prompt);

            // A. Cek Heuristik Aktif
            if (!empty($tokens)) {
                $activeHeuristics = \App\Models\ToolExecutionHeuristic::where('is_active', true)->get();
                foreach ($activeHeuristics as $heuristic) {
                    $matches = true;
                    foreach ($heuristic->parameter_pattern as $paramKey => $expectedToken) {
                        if (!isset($tokens[$expectedToken])) {
                            $matches = false;
                            break;
                        }
                    }

                    if ($matches) {
                        $instruction = $heuristic->rewrite_instruction['instruction'] ?? '';
                        foreach ($tokens as $tokenKey => $tokenVal) {
                            $instruction = str_replace($tokenKey, (string)$tokenVal, $instruction);
                        }
                        $cognitiveHints[] = "- **Aturan Pemulihan [ID: {$heuristic->id}]:** " . $instruction;
                        $appliedHeuristicIds[] = $heuristic->id;
                    }
                }
            }

            // B. Cek Lintasan Memori Teruji
            if (!empty($tokens)) {
                if (isset($tokens['REGION_NAME'])) {
                    $normalizedIntent = str_replace(strtolower($tokens['REGION_NAME']), 'REGION_NAME', $normalizedIntent);
                }
                if (isset($tokens['YEAR_TOKEN'])) {
                    $normalizedIntent = str_replace($tokens['YEAR_TOKEN'], 'YEAR_TOKEN', $normalizedIntent);
                }

                $matchedTrajectory = \App\Models\AgnosticTrajectoryMemory::where('intent_pattern', $normalizedIntent)
                    ->where('score', '>=', 90.0)
                    ->first();

                if ($matchedTrajectory) {
                    $matchedTrajectory->increment('use_count');
                    $matchedTrajectory->update(['last_used_at' => now()]);

                    $executionHint = "Ditemukan JALUR EKSEKUSI TERUJI untuk kueri terabstraksi ini:\n";
                    foreach ($matchedTrajectory->successful_execution_graph as $idx => $graphStep) {
                        $executionHint .= "   " . ($idx + 1) . ". Panggil perkakas `" . ($graphStep['tool'] ?? 'unknown') . "` dengan parameter optimal.\n";
                    }
                    $executionHint .= "   Panggil langsung perkakas-perkakas ini secara paralel untuk meminimalkan durasi eksekusi.";
                    $cognitiveHints[] = "- **Rute Pintas Lintasan Memori:** " . $executionHint;
                }
            }

            $indicatorMapAddendum = "";
            if (isset($tokens['SUB_REGION_CODE']) && isset($tokens['REGION_NAME'])) {
                $service = new \App\Services\BpsIndicatorMapService();
                $indicatorMapAddendum = $service->buildInjectionAddendum($tokens['SUB_REGION_CODE'], $tokens['REGION_NAME']);
            }

            $instructionsAddendum = "";
            if (!empty($cognitiveHints)) {
                $instructionsAddendum = "\n\n# MEMORI & HEURISTIK KOGNITIF TERKOREKSI (IKUTI ATURAN INI)\n" . implode("\n", $cognitiveHints);
            }
            if (!empty($indicatorMapAddendum)) {
                $instructionsAddendum .= $indicatorMapAddendum;
            }

            $agent = new GovtAnalyticsAgent(
                instructions: implode("\n", [
                    'Anda adalah Asisten Analitis Statistik Pemerintah Indonesia yang handal dan cerdas.',
                    'Waktu sekarang: ' . now()->translatedFormat('l, d F Y') . '.',
                    'PENTING: Jika meminta Indikator Strategis, Anda HARUS memecahnya menjadi daftar koma: "IPM, Kemiskinan, Gini Ratio, Pengangguran, PDRB, Angka Harapan Hidup". Jangan pernah mengirimkan kata "Indikator Strategis" secara langsung.',
                ]) . $instructionsAddendum,
                tools: $tools,
                messages: []
            );

            $response = $agent->prompt(
                prompt: $prompt,
                attachments: [],
                provider: $provider,
                model: $model
            );

            $duration = microtime(true) - $startTime;
            $assistantContent = $response->text;

            // Jalankan verifikasi fakta & disclaimers (simulasi pipeline lengkap)
            $assistantContent = FactGraderService::verifyAndCorrect($assistantContent, $state->steps);
            try {
                $disclaimerService = new DisclaimerInjectorService(
                    new \App\Services\MethodologyRegistryService()
                );
                $assistantContent = $disclaimerService->process($assistantContent, $state->steps);
            } catch (\Exception $disEx) {
                // Ignore disclaimer if registry fails in CLI
            }

            $this->info("✅ Agen selesai memproses respons!");
            
            $this->line("\n💬 RESPON AGEN BPS:\n" . $assistantContent . "\n");
            
            // 6. Analisis Metrik & Wasted Calls
            $steps = $state->steps;
            $totalCalls = count($steps);
            $wastedCalls = 0;
            $usefulCalls = 0;
            $detailedSteps = [];

            foreach ($steps as $idx => $step) {
                $isWasted = $this->isWastedCall($step);
                if ($isWasted) {
                    $wastedCalls++;
                } else {
                    $usefulCalls++;
                }

                $detailedSteps[] = [
                    'step' => $step['step'],
                    'tool' => $step['tool'],
                    'arguments' => $step['arguments'],
                    'is_wasted' => $isWasted,
                    'result_summary' => $this->summarizeResult($step['result'])
                ];
            }

            $efficiencyScore = $totalCalls > 0 ? round(($usefulCalls / $totalCalls) * 100, 2) : 100;

            // 7. Auto-Grading Akurasi Klaim (Agnostik Terhadap Wilayah Target)
            $accuracyData = $this->gradeAccuracy($assistantContent, $region, $steps);

            // 7.5. Agnostic Auto-Grading & Reinforcement Loop Update (Fase 2)
            $grader = new \App\Services\AgnosticGraderService();
            $agnosticScore = $grader->gradeResponse($steps, $prompt);

            if (!empty($appliedHeuristicIds)) {
                $heuristics = \App\Models\ToolExecutionHeuristic::whereIn('id', $appliedHeuristicIds)->get();
                foreach ($heuristics as $h) {
                    $isSuccess = $agnosticScore >= 50.0;
                    $grader->applyDecay($h, $isSuccess);
                }
            }

            if ($agnosticScore >= 100.0 && count($steps) > 0) {
                $executionGraph = [];
                foreach ($steps as $step) {
                    $executionGraph[] = [
                        'tool' => $step['tool'] ?? '',
                        'parameters' => $step['parameters'] ?? [],
                    ];
                    
                    // Auto-record var_id discovery dari argumen tool (Pembelajaran Mandiri)
                    if ($step['tool'] === 'bps_get_dynamic_data' && isset($step['arguments']['var_id'])) {
                        $domainCode = $step['arguments']['domain'] ?? '0000';
                        $level = $domainCode === '0000' ? 'nasional' : (str_ends_with($domainCode, '00') ? 'provinsi' : 'kota');
                        
                        \App\Models\BpsVariableMap::recordDiscovery(
                            domainCode: $domainCode,
                            domainLevel: $level,
                            indicatorSlug: 'auto_discovered_' . $step['arguments']['var_id'],
                            varId: (int) $step['arguments']['var_id']
                        );
                        // Invalidate cache
                        (new \App\Services\BpsIndicatorMapService())->invalidateCache($domainCode);
                    }
                }

                \App\Models\AgnosticTrajectoryMemory::updateOrCreate(
                    ['intent_pattern' => $normalizedIntent],
                    [
                        'successful_execution_graph' => $executionGraph,
                        'applied_heuristic_ids' => $appliedHeuristicIds,
                        'score' => $agnosticScore,
                        'duration' => $duration,
                        'last_used_at' => now(),
                    ]
                );
            }

            // 8. Tampilkan Laporan Premium di Konsol
            $this->line("");
            $this->info("================================================================================");
            $this->info(" 📊 HASIL ANALISIS KINERJA AI AGENT (PERFORMANCE REPORT) ");
            $this->info("================================================================================");
            
            $this->table(
                ['Metrik Kunci', 'Nilai Hasil Pengujian'],
                [
                    ['Total Waktu Respon', round($duration, 2) . " detik"],
                    ['Jumlah Langkah Agent (Steps)', $totalCalls . " langkah"],
                    ['MCP Calls Berguna (Useful)', $usefulCalls . " panggilan"],
                    ['MCP Calls Sia-sia (Wasted)', "<fg=red>{$wastedCalls}</> panggilan"],
                    ['Skor Efisiensi Panggilan', ($efficiencyScore >= 80 ? "<fg=green>{$efficiencyScore}%</>" : "<fg=yellow>{$efficiencyScore}%</>")],
                    ['Skor Akurasi Klaim Terarah', ($accuracyData['score'] >= 80 ? "<fg=green>{$accuracyData['score']}%</>" : "<fg=yellow>{$accuracyData['score']}%</>") . " (" . $accuracyData['matched'] . "/" . $accuracyData['total'] . " Klaim Valid)"],
                    ['Agnostic Auto-Grader Score', ($agnosticScore >= 80.0 ? "<fg=green>{$agnosticScore}%</>" : ($agnosticScore >= 50.0 ? "<fg=yellow>{$agnosticScore}%</>" : "<fg=red>{$agnosticScore}%</>"))],
                ]
            );

            // Tampilkan Detail Panggilan Langkah Perkakas
            if ($totalCalls > 0) {
                $this->info("📝 DETAIL LANGKAH PERKAKAS (TOOL EXECUTION PATH):");
                $tableData = [];
                foreach ($detailedSteps as $ds) {
                    $tableData[] = [
                        $ds['step'],
                        $ds['tool'],
                        json_encode($ds['arguments']),
                        $ds['is_wasted'] ? "<fg=red>Sia-sia (Wasted)</>" : "<fg=green>Berguna (Useful)</>",
                        Str::limit($ds['result_summary'], 50)
                    ];
                }
                $this->table(['Step', 'Nama Perkakas', 'Argumen', 'Status Efisiensi', 'Ringkasan Hasil'], $tableData);
            }

            // Tampilkan Klaim Data yang Terverifikasi
            $this->info("✅ STATUS VERIFIKASI KLAIM DATA GROUND TRUTH:");
            $claimTable = [];
            foreach ($accuracyData['claims'] as $c => $data) {
                $claimTable[] = [
                    $c,
                    $data['expected'],
                    $data['matched'] ? "<fg=green>TERVERIFIKASI (OK)</>" : "<fg=yellow>TIDAK DITEMUKAN</>"
                ];
            }
            $this->table(['Indikator Statistik', 'Ground Truth Nilai Acuan', 'Hasil Verifikasi'], $claimTable);

            // Simpan Log ke Berkas JSON Terstruktur
            $logPath = storage_path('logs/bps_agent_tests.json');
            $existingLogs = [];
            if (file_exists($logPath)) {
                $existingLogs = json_decode(file_get_contents($logPath), true) ?: [];
            }

            $currentLog = [
                'timestamp' => now()->toIso8601String(),
                'prompt' => $prompt,
                'region' => $region,
                'duration_seconds' => round($duration, 2),
                'total_steps' => $totalCalls,
                'useful_calls' => $usefulCalls,
                'wasted_calls' => $wastedCalls,
                'efficiency_score' => $efficiencyScore,
                'accuracy_score' => $accuracyData['score'],
                'matched_claims' => $accuracyData['matched'],
                'total_claims' => $accuracyData['total'],
                'steps' => $detailedSteps,
            ];

            $existingLogs[] = $currentLog;
            file_put_contents($logPath, json_encode($existingLogs, JSON_PRETTY_PRINT));
            $this->info("💾 Laporan pengujian disimpan dengan sukses pada: [storage/logs/bps_agent_tests.json]");

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Terjadi kegagalan eksekusi Agent: " . $e->getMessage());
            $this->line($e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Cek apakah panggilan MCP/Tool sia-sia (tidak mengembalikan data valid).
     */
    protected function isWastedCall(array $step): bool
    {
        $result = $step['result'] ?? null;
        if (!$result) {
            return true;
        }

        // Cek error eksplisit
        if (isset($result['isError']) && $result['isError']) return true;
        if (isset($result['error'])) return true;
        if (isset($result['status']) && strtolower((string)$result['status']) === 'error') return true;

        // Parsing teks isi
        $text = '';
        if (isset($result['content']) && is_array($result['content'])) {
            foreach ($result['content'] as $item) {
                if (isset($item['type']) && $item['type'] === 'text') {
                    $text .= ' ' . ($item['text'] ?? '');
                }
            }
        } else {
            $text = json_encode($result);
        }

        $textLower = strtolower($text);

        // Kriteria pemanggilan sia-sia
        if (str_contains($textLower, 'data tidak ditemukan')) return true;
        if (str_contains($textLower, 'parameter is missing')) return true;
        if (str_contains($textLower, 'no data field')) return true;
        if (str_contains($textLower, 'grounding_failed')) return true;
        if (str_contains($textLower, '"data":[]')) return true;
        if (str_contains($textLower, 'result:\n[]')) return true;
        if (trim($text) === '[]' || trim($text) === 'Result:\n[]') return true;

        return false;
    }

    /**
     * Ringkas hasil pemanggilan perkakas untuk dicetak di tabel.
     */
    protected function summarizeResult(mixed $result): string
    {
        if (!$result) return 'Null';

        if (isset($result['content']) && is_array($result['content'])) {
            $texts = [];
            foreach ($result['content'] as $item) {
                if (isset($item['type']) && $item['type'] === 'text') {
                    $texts[] = $item['text'];
                }
            }
            return implode(" ", $texts);
        }

        return json_encode($result);
    }

    /**
     * Lakukan auto-grading terhadap akurasi data statistik asisten berdasarkan baseline ground-truth.
     */
    protected function gradeAccuracy(string $content, string $region, array $steps): array
    {
        $claims = [];
        
        // 1. Dapatkan Kode BPS Wilayah Target yang Diharapkan
        $expectedCode = null;
        $officialName = '';
        $regency = \Illuminate\Support\Facades\DB::table('bps_regencies')
            ->where('name', 'like', "%{$region}%")
            ->first();
            
        if ($regency) {
            $expectedCode = (string)$regency->code;
            $officialName = $regency->name;
        }

        // 2. Lakukan Pemeriksaan Domain Integrity (Hancurkan Circular Validation!)
        $hasDomainMatch = false;
        $hasApiCalls = false;
        
        foreach ($steps as $step) {
            $tool = $step['tool'] ?? '';
            $args = $step['arguments'] ?? [];
            
            // Periksa jika ada pemanggilan tool BPS API
            if (str_contains(strtolower($tool), 'bps') || in_array($tool, ['fetch_regional_report', 'get_bps_indicator', 'compare_regencies'])) {
                $hasApiCalls = true;
                $domainUsed = (string)($args['domain_code'] ?? $args['regency_code'] ?? $args['domain'] ?? '');
                
                if ($expectedCode && !empty($domainUsed) && str_contains($domainUsed, $expectedCode)) {
                    $hasDomainMatch = true;
                }
            }
        }

        // Jika agen melakukan tool call BPS tapi salah alamat (domain_code tidak cocok), penalti 100%!
        $domainIntegrityPenalty = false;
        if ($hasApiCalls && $expectedCode && !$hasDomainMatch) {
            $domainIntegrityPenalty = true;
        }

        // 3. Ekstrak fakta nyata dari steps secara dinamis (Agnostic Grounding)
        $facts = \App\Services\FactGraderService::extractStructuredFacts($steps);

        if ($region === 'denpasar' && empty($facts)) {
            // Ground Truth Kota Denpasar & Bali (Legacy mode jika data tidak dimuat dinamis)
            $claims = [
                'IPM Kota Denpasar 2025' => [
                    'expected' => '85.63 / 85,63',
                    'regex' => '/85[\.,]63/'
                ],
                'IPM Kota Denpasar 2024 (BPS)' => [
                    'expected' => '85.22 / 85,22 atau LKPJ 85.11 / 85,11',
                    'regex' => '/85[\.,](22|11)/'
                ],
                'Tingkat Pengangguran Terbuka (TPT) 2025' => [
                    'expected' => '1.41% / 1,41%',
                    'regex' => '/1[\.,]41/'
                ],
                'Tingkat Pengangguran Terbuka (TPT) 2024' => [
                    'expected' => '2.11% / 2,11%',
                    'regex' => '/2[\.,]11/'
                ],
                'Persentase Kemiskinan 2025' => [
                    'expected' => '2.16% / 2,16%',
                    'regex' => '/2[\.,]16/'
                ],
                'Persentase Kemiskinan 2024' => [
                    'expected' => '2.59% / 2,59% atau 27.27 Ribu Jiwa',
                    'regex' => '/(2[\.,]59|27[\.,]27)/'
                ],
                'Gini Rasio 2024' => [
                    'expected' => '0.341 / 0,341 atau 0.34 / 0,34',
                    'regex' => '/0[\.,]34/'
                ],
                'Angka Harapan Hidup 2025' => [
                    'expected' => '76.16 / 76,16 atau 76.49 / 76,49',
                    'regex' => '/76[\.,](16|49)/'
                ],
                'IPM Provinsi Bali 2025' => [
                    'expected' => '79.37 / 79,37',
                    'regex' => '/79[\.,]37/'
                ]
            ];
        } else {
            // Agnostic Dynamic Claims berdasarkan data aktual BPS API
            if (!empty($facts)) {
                foreach ($facts as $fact) {
                    $indicator = $fact['indicator'];
                    $year = $fact['year'];
                    $val = $fact['value'];
                    
                    $title = "Indikator {$indicator} Tahun {$year} di {$officialName}";
                    
                    $valFormatted1 = str_replace('.', ',', number_format($val, 2));
                    $valFormatted2 = str_replace('.', ',', number_format($val, 3));
                    $valRaw = (string)$val;
                    
                    $escapedVal1 = preg_quote($valFormatted1, '/');
                    $escapedVal2 = preg_quote($valFormatted2, '/');
                    $escapedRaw = preg_quote($valRaw, '/');
                    
                    $claims[$title] = [
                        'expected' => "Nilai Resmi: {$val} (Format: {$valFormatted1} atau {$valRaw})",
                        'regex' => '/' . $escapedVal1 . '|' . $escapedVal2 . '|' . $escapedRaw . '/'
                    ];
                }
            } else {
                // Fallback jika asisten tidak memanggil API atau data kosong sama sekali
                $claims = [
                    'Eksistensi Angka Desimal Statistik' => [
                        'expected' => 'Mendeteksi eksistensi angka desimal format statistik (XX,XX)',
                        'regex' => '/\b\d{1,2}[\.,]\d{1,2}\b/'
                    ]
                ];
            }
        }

        $matchedCount = 0;
        $totalCount = count($claims);
        $claimResults = [];

        foreach ($claims as $title => $data) {
            $isMatched = (bool) preg_match($data['regex'], $content);
            if ($isMatched) {
                $matchedCount++;
            }
            $claimResults[$title] = [
                'expected' => $data['expected'],
                'matched' => $isMatched
            ];
        }

        $score = $totalCount > 0 ? round(($matchedCount / $totalCount) * 100, 2) : 100;

        // Jika integritas domain rusak, kurangi skor akurasi menjadi 0%!
        if ($domainIntegrityPenalty) {
            $score = 0.0;
            $claimResults['❌ DOMAIN INTEGRITY BREACH'] = [
                'expected' => "Memanggil domain BPS yang sesuai dengan wilayah target ({$expectedCode})",
                'matched' => false
            ];
            $totalCount++;
        }

        return [
            'score' => $score,
            'matched' => $domainIntegrityPenalty ? 0 : $matchedCount,
            'total' => $totalCount,
            'claims' => $claimResults
        ];
    }
}
