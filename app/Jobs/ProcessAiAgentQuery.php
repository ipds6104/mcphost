<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\GovtAnalyticsAgent;
use App\Ai\Tools\BpsApiTool;
use App\Events\AgentResponseGenerated;
use App\Exceptions\OffTopicQueryException;
use App\Exceptions\SecurityException;
use App\Models\Message;
use App\Services\BpsApiService;
use App\Services\DisclaimerInjectorService;
use App\Services\FactGraderService;
use App\Services\LlmInputValidator;
use App\Services\MethodologyRegistryService;
use Cortex\JsonRepair\JsonRepairer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Messages\Message as AiMessage;
use Laravel\Ai\Messages\MessageRole;

class ProcessAiAgentQuery implements ShouldQueue
{
    use Queueable;

    /**
     * Waktu maksimal eksekusi job sebelum di-terminate (detik).
     *
     * @var int
     */
    public $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $chatId,
        public int $userMessageId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // ┌─────────────────────────────────────────────────────────────┐
        // │  TRACE CONTEXT — Structured Observability (Standar Ke-13)  │
        // │  Setiap eksekusi job menghasilkan trace_id unik untuk       │
        // │  korelasi lintas logs, WebSocket events, dan DB queries.    │
        // └─────────────────────────────────────────────────────────────┘
        $traceId = (string) Str::uuid();
        $startedAt = hrtime(true);

        $userMessage = Message::findOrFail($this->userMessageId);

        // ── 0.5. Lakukan Validasi Input (Input Shield & Dialogue Rails) ──
        try {
            $validatedPrompt = LlmInputValidator::validate($userMessage->content);
            if ($validatedPrompt !== $userMessage->content) {
                $userMessage->update(['content' => $validatedPrompt]);
            }
        } catch (OffTopicQueryException $e) {
            $rejectionContent = $e->getMessage();
            $rejectionMessage = Message::create([
                'chat_id' => $this->chatId,
                'role' => 'assistant',
                'content' => $rejectionContent,
                'agent_steps' => null,
                'chart_data' => null,
            ]);
            event(new AgentResponseGenerated($this->chatId, $rejectionMessage->id, $rejectionContent, null));
            Log::channel('ai_agent')->info('ai_agent.off_topic_rejected', [
                'trace_id' => $traceId,
                'chat_id' => $this->chatId,
                'prompt' => $userMessage->content,
            ]);

            return;
        } catch (SecurityException $e) {
            $rejectionContent = '⚠️ **Keamanan Terdeteksi:** ' . $e->getMessage();
            $rejectionMessage = Message::create([
                'chat_id' => $this->chatId,
                'role' => 'assistant',
                'content' => $rejectionContent,
                'agent_steps' => null,
                'chart_data' => null,
            ]);
            event(new AgentResponseGenerated($this->chatId, $rejectionMessage->id, $rejectionContent, null));
            Log::channel('ai_agent')->warning('ai_agent.security_validation_rejected', [
                'trace_id' => $traceId,
                'chat_id' => $this->chatId,
                'prompt' => $userMessage->content,
                'error' => $e->getMessage(),
            ]);

            return;
        } catch (\InvalidArgumentException $e) {
            $rejectionContent = '⚠️ **Validasi Gagal:** ' . $e->getMessage();
            $rejectionMessage = Message::create([
                'chat_id' => $this->chatId,
                'role' => 'assistant',
                'content' => $rejectionContent,
                'agent_steps' => null,
                'chart_data' => null,
            ]);
            event(new AgentResponseGenerated($this->chatId, $rejectionMessage->id, $rejectionContent, null));
            Log::channel('ai_agent')->warning('ai_agent.input_validation_failed', [
                'trace_id' => $traceId,
                'chat_id' => $this->chatId,
                'prompt' => $userMessage->content,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        // ── 0. Generasikan judul obrolan secara asinkron menggunakan AI jika ini adalah pesan pertama ──
        $userMessagesCount = Message::where('chat_id', $this->chatId)
            ->where('role', 'user')
            ->count();

        if ($userMessagesCount === 1) {
            try {
                $titleAgent = new \App\Ai\Agents\TitleGeneratorAgent();
                $titleResponse = $titleAgent->prompt(
                    prompt: $userMessage->content,
                    provider: $provider ?? config('ai.default', 'openai'),
                    model: $model ?? config('ai.default_model', 'gemini-3-flash')
                );

                $newTitle = trim(strip_tags($titleResponse->text));
                if (! empty($newTitle) && strlen($newTitle) > 3) {
                    $chat = \App\Models\Chat::find($this->chatId);
                    if ($chat) {
                        $chat->update(['title' => $newTitle]);
                        event(new \App\Events\ChatTitleUpdated($this->chatId, $newTitle));

                        Log::channel('ai_agent')->info('ai_agent.title_generated', [
                            'trace_id' => $traceId,
                            'chat_id' => $this->chatId,
                            'new_title' => $newTitle,
                        ]);
                    }
                }
            } catch (\Exception $titleEx) {
                Log::channel('ai_agent')->warning('ai_agent.title_generation_failed', [
                    'trace_id' => $traceId,
                    'chat_id' => $this->chatId,
                    'error' => $titleEx->getMessage(),
                ]);
            }
        }

        // ── Baca konfigurasi AI dari config() — BUKAN env() ─────────────
        $provider = config('ai.default', 'openai');
        $model = config('ai.default_model', 'gemini-3-flash');
        $providerKey = config("ai.providers.{$provider}.key");

        Log::channel('ai_agent')->info('ai_agent.job_started', [
            'trace_id' => $traceId,
            'chat_id' => $this->chatId,
            'user_message_id' => $this->userMessageId,
            'provider' => $provider,
            'model' => $model,
        ]);

        // ── 1. Muat riwayat percakapan sebagai konteks untuk LLM ────────
        $history = Message::where('chat_id', $this->chatId)
            ->where('id', '<', $this->userMessageId)
            ->orderBy('id', 'asc')
            ->get();

        $aiMessages = $history->map(fn (Message $msg) => new AiMessage(
            $msg->role === 'user' ? MessageRole::User : MessageRole::Assistant,
            $msg->content
        ))->all();

        // ── 2. Inisialisasi BPS API Tools (Real API, bukan mock) ─────────────
        $state = (object) ['stepIndex' => 0, 'steps' => []];
        $tools = [];
        $bpsService = new BpsApiService((string) config('services.bps.key', ''));

        $bpsToolDefinitions = [
            [
                'name' => 'fetch_regional_report',
                'description' => 'Mengambil daftar tabel statistik resmi BPS untuk suatu kabupaten/kota berdasarkan kode domain BPS (data real-time dari webapi.bps.go.id).',
            ],
            [
                'name' => 'get_bps_indicator',
                'description' => 'Mengambil data dari tabel statistik BPS tertentu untuk suatu wilayah (data real-time dari webapi.bps.go.id).',
            ],
            [
                'name' => 'compare_regencies',
                'description' => 'Membandingkan data statistik BPS antar beberapa kabupaten/kota secara paralel (data real-time dari webapi.bps.go.id).',
            ],
            [
                'name' => 'search_statistics',
                'description' => 'Mencari variabel dan indikator statistik BPS berdasarkan kata kunci untuk suatu wilayah (data real-time dari webapi.bps.go.id).',
            ],
        ];

        foreach ($bpsToolDefinitions as $toolDef) {
            $tools[] = new BpsApiTool(
                toolName: $toolDef['name'],
                toolDescription: $toolDef['description'],
                bpsService: $bpsService,
                chatId: $this->chatId,
                state: $state
            );
        }

        Log::channel('ai_agent')->debug('ai_agent.bps_tools_ready', [
            'trace_id' => $traceId,
            'chat_id' => $this->chatId,
            'tool_count' => count($tools),
            'tool_names' => array_map(fn ($t) => $t->name(), $tools),
            'bps_key_ok' => ! empty(config('services.bps.key')),
        ]);

        // ── 3. Siapkan multimodal attachments (gambar, dll.) ─────────────
        $attachments = [];
        if ($userMessage->attachments) {
            foreach ($userMessage->attachments as $attachmentPath) {
                $fullPath = public_path($attachmentPath);
                if (file_exists($fullPath)) {
                    $attachments[] = $fullPath;
                }
            }
        }

        // ── 4. Jalankan LLM — selalu gunakan AI nyata jika API key ada ───
        $assistantContent = '';

        if (! $providerKey) {
            // Tidak ada API key sama sekali — beri respons informatif
            $assistantContent = implode("\n\n", [
                '⚠️ **Konfigurasi API Belum Lengkap**',
                'Asisten AI belum dapat merespons karena API key belum dikonfigurasi di file `.env`.',
                'Tambahkan salah satu konfigurasi berikut:',
                "```\n# Untuk OpenAI-compatible provider:\nAI_PROVIDER=openai\nOPENAI_API_KEY=sk-...\nOPENAI_URL=https://api.openai.com/v1\n\n# Atau untuk Gemini langsung:\nAI_PROVIDER=gemini\nGEMINI_API_KEY=AIza...\n```",
                'Setelah mengatur API key, restart container dengan `docker compose restart worker`.',
            ]);
        } else {
            try {
                $agent = new GovtAnalyticsAgent(
                    instructions: implode("\n", [
                        '# IDENTITAS & PERAN',
                        'Anda adalah Asisten Analitis Statistik Pemerintah Indonesia yang handal dan cerdas.',
                        'Waktu sekarang: ' . now()->translatedFormat('l, d F Y') . ' pukul ' . now()->format('H:i') . ' WIB.',
                        '',
                        '# ALUR KERJA WAJIB (IKUTI URUTAN INI)',
                        '1. PLAN: Tentukan data apa saja yang dibutuhkan sebelum memanggil tool apapun.',
                        '2. EXECUTE: Panggil SEMUA data yang diperlukan dalam SATU langkah paralel menggunakan execute_js + Promise.all().',
                        '3. ANALYZE: Analisis data yang diterima dan buat jawaban komprehensif.',
                        '4. RESPOND: Berikan jawaban dalam format Markdown yang rapi.',
                        '',
                        '# ATURAN KRITIS — WAJIB DIPATUHI',
                        '',
                        '## A. GUNAKAN bps_query UNTUK KOMPARASI WILAYAH (CARA PALING EFISIEN)',
                        'Untuk pertanyaan komparasi antar wilayah (misal: "bandingkan Kutai Timur vs Mempawah"),',
                        'SELALU gunakan tool bps_query dengan parameter wilayah yang tepat.',
                        'Tool bps_query sudah menangani pencarian domain, variabel, dan data secara otomatis.',
                        'JANGAN gunakan execute_js + simdasi-regions atau domain lookup manual untuk komparasi regional.',
                        '',
                        '## B. FORMAT RESPONS API BPS — STRUKTUR DATA YANG BENAR',
                        'Semua endpoint bpsFetch mengembalikan: { status: 200, data: [pagination_obj, data_array] }',
                        'SELALU ambil data dengan: response.data[1] (array), bukan response.data atau response langsung.',
                        'Contoh: bpsFetch("/domain", { type: "all" }) => domain list ada di: result.data[1]',
                        'Contoh: bpsFetch("/list", { model: "var", domain: "6404" }) => var list ada di: result.data[1]',
                        '',
                        '## C. KODE DOMAIN WILAYAH — SUDAH DIKETAHUI, JANGAN CARI ULANG',
                        'Kutai Timur (Kaltim): domain_id = "6404" (kode BPS Kabupaten Kutai Timur)',
                        'Mempawah (Kalbar): domain_id = "6104" (kode BPS Kabupaten Mempawah)',
                        'Jika perlu domain wilayah lain, gunakan: bps_domain_list tool (bukan execute_js + /domain).',
                        'JANGAN gunakan simdasi-regions untuk mencari kode wilayah — tool itu untuk data SIMDASI, bukan domain BPS.',
                        '',
                        '## D. STOP LOOP — MAKSIMAL 3 LANGKAH execute_js PER SESI',
                        'Jika execute_js sudah mengembalikan data, JANGAN panggil API yang sama dengan variasi parameter.',
                        'Jika data tidak tersedia, gunakan data yang ada untuk membuat analisis terbaik yang mungkin.',
                        'Lebih baik jawaban parsial daripada loop tak terbatas.',
                        '',
                        '## E. PARALEL WAJIB — CONTOH KODE YANG BENAR',
                        'Setiap request ke BPS memakan ~45 detik. WAJIB paralel, DILARANG sequential.',
                        '```javascript',
                        '// Contoh komparasi 2 wilayah dalam 1 langkah:',
                        'const [d1, d2] = await Promise.all([',
                        '  bps.bpsFetch("/list", { model: "var", domain: "6404", page: 1 }),',
                        '  bps.bpsFetch("/list", { model: "var", domain: "6104", page: 1 })',
                        ']);',
                        'const extract = (r) => (r.data?.[1] || []).slice(0, 15).map(v => ({ id: v.var_id, name: v.title }));',
                        'return { kutim: extract(d1), mempawah: extract(d2) };',
                        '```',
                        '',
                        '## F. TRANSPARANSI DATA — WAJIB',
                        'Setiap kali menyajikan angka statistik dari tool result BPS:',
                        '1. Cantumkan: "Sumber: BPS WebAPI (webapi.bps.go.id), diambil pada: [fetched_at dari field _meta]",',
                        '2. Cantumkan versi metodologi jika tersedia di field _meta,',
                        '3. DILARANG KERAS memodifikasi, menginterpolasi, atau memperkirakan angka dari tool result,',
                        '4. Gunakan angka PERSIS seperti dikembalikan tool — bukan pengetahuan internal Anda.',
                        '5. JANGAN PERNAH berasumsi, mengira-ngira, menginterpolasi, atau mengarang angka statistik jika data dari BPS API tool kosong, null, atau mengembalikan error/restricted (terdapat field grounding_failed). Jika data tidak diperoleh dari tool, Anda WAJIB menjawab secara jujur: "Maaf, data tidak tersedia karena keterbatasan hak akses API BPS atau data kosong."',
                        '',
                        '## G. COMPARABILITY — WAJIB PERIKSA',
                        'Jika analisis mencakup rentang > 10 tahun pada sektor IPM/PDRB/IHK/Kemiskinan/Ketenagakerjaan:',
                        '- WAJIB sebutkan kemungkinan diskontinuitas metodologi BPS antar periode,',
                        '- JANGAN sambung angka lintas tahun perubahan metodologi dalam satu grafik tanpa anotasi,',
                        '- System akan otomatis menambahkan disclaimer metodologi jika relevan.',
                        '',
                        '## H. STRATEGI KATA KUNCI & PEMAHAMAN KONTEKS (WAJIB)',
                        'Saat merumuskan kata kunci pencarian pada tool search_statistics, Anda harus cerdas menebak maksud user berdasarkan konteks percakapan dan melakukan ekspansi kata kunci:',
                        '1. Singkatan Umum Pemerintah/BPS: Petakan singkatan ke nama variabel lengkapnya:',
                        '   - "IPM" -> "Indeks Pembangunan Manusia"',
                        '   - "PDRB" -> "Produk Domestik Regional Bruto"',
                        '   - "IHK" -> "Indeks Harga Konsumen"',
                        '   - "TPT" -> "Tingkat Pengangguran Terbuka"',
                        '   - "AHH" -> "Angka Harapan Hidup"',
                        '   - "RLS" -> "Rata-rata Lama Sekolah"',
                        '   - "HLS" -> "Harapan Lama Sekolah"',
                        '   - "PPP" -> "Pengeluaran Per Kapita"',
                        '   - "Inflasi" -> "Indeks Harga Konsumen" atau "Inflasi"',
                        '   - "Gini" / "Rasio Gini" -> "Gini Ratio" atau "Rasio Gini"',
                        '2. Ekspansi Sinonim & Variasi Pencarian:',
                        '   - Jika pencarian dengan satu kata kunci singkat (seperti "IPM") tidak mengembalikan hasil yang memuaskan atau kosong, Anda WAJIB mencoba melakukan pencarian kembali dengan kata kunci lengkapnya (seperti "Indeks Pembangunan Manusia") secara paralel.',
                        '   - Tebak maksud user: Jika user menyebutkan "pertumbuhan ekonomi", gunakan kata kunci "PDRB" atau "Laju Pertumbuhan PDRB". Jika user menyebutkan "pengangguran", gunakan kata kunci "Tingkat Pengangguran Terbuka" atau "TPT". Jika user menyebutkan "kesejahteraan", gunakan kata kunci "Kemiskinan" atau "IPM" atau "PDRB Per Kapita".',
                        '',
                        '- Gunakan Bahasa Indonesia yang baik, formal, dan profesional.',
                        '- Gunakan tabel Markdown GFM (pipe | dan tanda hubung -) untuk data tabular.',
                        '- Gunakan heading ## dan ### untuk struktur laporan yang jelas.',
                        '- Sertakan kesimpulan strategis dan rekomendasi kebijakan di akhir laporan.',
                    ]),
                    tools: $tools,
                    messages: $aiMessages
                );

                $attempts = 0;
                $maxAttempts = 3;
                $currentPrompt = $userMessage->content;
                $assistantContent = '';

                while ($attempts < $maxAttempts) {
                    $response = $agent->prompt(
                        prompt: $currentPrompt,
                        attachments: $attachments,
                        provider: $provider,
                        model: $model
                    );

                    $draftContent = $response->text;

                    // Lakukan verifikasi nama wilayah dan koreksi numerik
                    $correctedContent = FactGraderService::verifyAndCorrect($draftContent, $state->steps);

                    // Injeksi disclaimer metodologi + source citation otomatis
                    $disclaimerService = new DisclaimerInjectorService(
                        new MethodologyRegistryService()
                    );
                    $correctedContent = $disclaimerService->process($correctedContent, $state->steps);

                    // Evaluasi format diagram (json-chart) jika ada dalam teks
                    $isChartValid = true;
                    if (preg_match('/```json-chart\s*(.*?)\s*```/s', $correctedContent, $chartMatches)) {
                        try {
                            $repaired = (new JsonRepairer($chartMatches[1]))->repair();
                            json_decode($repaired, true);
                            if (json_last_error() !== JSON_ERROR_NONE) {
                                $isChartValid = false;
                            }
                        } catch (\Exception $chartEx) {
                            $isChartValid = false;
                        }
                    }

                    if ($isChartValid) {
                        $assistantContent = $correctedContent;
                        break;
                    }

                    // Jika struktur JSON diagram tidak valid, trigger auto re-ask loop
                    $attempts++;
                    Log::channel('ai_agent')->warning('ai_agent.self_correction.re_ask', [
                        'trace_id' => $traceId,
                        'chat_id' => $this->chatId,
                        'attempt' => $attempts,
                        'reason' => 'Struktur JSON-Chart tidak valid atau tidak dapat diperbaiki.',
                    ]);

                    $currentPrompt = 'Draf respons Anda sebelumnya memiliki format diagram (json-chart) yang rusak/tidak valid. Harap koreksi data visualisasi diagram Anda di dalam blok ```json-chart ... ``` dengan benar. Pastikan format JSON valid tanpa tanda koma menggantung atau tag tidak berpasangan, lalu kirim ulang seluruh laporan yang telah diperbaiki.';
                }

                // Fallback jika setelah maxAttempts masih gagal
                if (empty($assistantContent)) {
                    $assistantContent = FactGraderService::verifyAndCorrect($response->text, $state->steps);
                }

            } catch (\Exception $e) {
                Log::channel('ai_agent')->error('ai_agent.llm_failed', [
                    'trace_id' => $traceId,
                    'chat_id' => $this->chatId,
                    'provider' => $provider,
                    'model' => $model,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]);

                $assistantContent = implode("\n\n", [
                    '❌ **Terjadi Kendala Koneksi ke AI**',
                    'Asisten AI mengalami masalah saat memproses permintaan Anda.',
                    '**Detail error:** `' . $e->getMessage() . '`',
                    'Silakan coba lagi dalam beberapa saat.',
                ]);
            }
        }

        // ── 5. Parsing chart data dari markdown output LLM ───────────────
        $chartData = $this->parseChartData($assistantContent);

        // ── 6. Simpan respons asisten ke database ─────────────────────────
        $assistantMessage = Message::create([
            'chat_id' => $this->chatId,
            'role' => 'assistant',
            'content' => $assistantContent,
            'agent_steps' => count($state->steps) > 0 ? $state->steps : null,
            'chart_data' => $chartData,
        ]);

        // ── 7. Broadcast respons final via Laravel Reverb WebSocket ───────
        event(new AgentResponseGenerated($this->chatId, $assistantMessage->id, $assistantContent, $chartData));

        // ┌──────────────────────────────────────────────────────────┐
        // │  TRACE COMPLETE — Catat durasi total & ringkasan hasil  │
        // └──────────────────────────────────────────────────────────┘
        $durationMs = round((hrtime(true) - $startedAt) / 1_000_000, 2);

        Log::channel('ai_agent')->info('ai_agent.job_completed', [
            'trace_id' => $traceId,
            'chat_id' => $this->chatId,
            'assistant_msg_id' => $assistantMessage->id,
            'duration_ms' => $durationMs,
            'step_count' => count($state->steps),
            'has_chart' => $chartData !== null,
            'content_length' => strlen($assistantContent),
        ]);
    }

    /**
     * Parse custom ```json-chart block dari output markdown LLM.
     * Memungkinkan LLM menghasilkan data grafik terstruktur yang dirender
     * sebagai chart interaktif di frontend.
     */
    protected function parseChartData(string $content): ?array
    {
        if (preg_match('/```json-chart\s*(.*?)\s*```/s', $content, $matches)) {
            try {
                // Perbaiki struktur JSON kotor menggunakan library Cortex JSON Repair
                $repaired = (new JsonRepairer($matches[1]))->repair();
                $data = json_decode($repaired, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $data;
                }
            } catch (\Exception $e) {
                Log::channel('ai_agent')->warning('ai_agent.json_repair_failed', [
                    'error' => $e->getMessage(),
                    'raw_json' => $matches[1],
                ]);
            }
        }

        return null;
    }

    /**
     * Handle a job failure (e.g. timeout or fatal exception).
     * Menjamin UI pengguna tidak hang selamanya dan menerima info fallback yang jelas.
     */
    public function failed(\Throwable $exception): void
    {
        Log::channel('ai_agent')->error('ai_agent.job_failed_fatal', [
            'chat_id' => $this->chatId,
            'user_message_id' => $this->userMessageId,
            'error' => $exception->getMessage(),
            'exception' => get_class($exception),
        ]);

        $errorMessage = implode("\n\n", [
            '❌ **Terjadi Jeda Waktu Koneksi (Timeout)**',
            'Proses analisis data statistik ditangguhkan karena koneksi ke server AI atau remote BPS gateway melebihi batas waktu aman (60 detik).',
            'Silakan klik tombol **Buat Ulang (Regenerate)** di bawah untuk mengirim ulang permintaan Anda.',
        ]);

        // Simpan pesan error ke database agar UI ter-update
        $assistantMessage = Message::create([
            'chat_id' => $this->chatId,
            'role' => 'assistant',
            'content' => $errorMessage,
        ]);

        // Broadcast ke WebSocket agar UI berhenti memuat
        try {
            event(new AgentResponseGenerated($this->chatId, $assistantMessage->id, $errorMessage, null));
        } catch (\Exception $e) {
            Log::warning('Failed to broadcast AgentResponseGenerated on job failure: ' . $e->getMessage());
        }
    }
}
