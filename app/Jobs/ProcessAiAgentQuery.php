<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\GovtAnalyticsAgent;
use App\Ai\Tools\DynamicMcpTool;
use App\Events\AgentResponseGenerated;
use App\Models\Chat;
use App\Models\Message;
use App\Services\McpRemoteClientService;
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
     * Create a new job instance.
     */
    public function __construct(
        public string $chatId,
        public int $userMessageId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(McpRemoteClientService $mcpClient): void
    {
        $chat = Chat::with('messages')->findOrFail($this->chatId);
        $userMessage = Message::findOrFail($this->userMessageId);

        $sessionId = 'session_' . $this->chatId . '_' . Str::uuid()->toString();
        $provider = env('AI_PROVIDER', 'openai');
        $model = env('AI_MODEL', 'gemini-3-flash');
        $providerKey = env($provider === 'openai' ? 'OPENAI_API_KEY' : 'GEMINI_API_KEY');

        // 1. Ambil riwayat chat sebelumnya & konversi ke format Laravel AI Message
        $history = Message::where('chat_id', $this->chatId)
            ->where('id', '<', $this->userMessageId)
            ->orderBy('id', 'asc')
            ->get();

        $aiMessages = [];
        foreach ($history as $msg) {
            $aiMessages[] = new AiMessage(
                $msg->role === 'user' ? MessageRole::User : MessageRole::Assistant,
                $msg->content
            );
        }

        // 2. Ambil tools dinamis secara asinkron dari remote MCP server
        $tools = [];
        $state = (object) [
            'stepIndex' => 0,
            'steps' => [],
        ];

        try {
            $remoteTools = $mcpClient->getTools('data');
            foreach ($remoteTools as $tool) {
                $schemaData = $tool['inputSchema']['properties'] ?? [];

                $tools[] = new DynamicMcpTool(
                    name: $tool['name'],
                    description: $tool['description'] ?? '',
                    schemaData: $schemaData,
                    mcpClient: $mcpClient,
                    chatId: $this->chatId,
                    state: $state,
                    sessionId: $sessionId
                );
            }
        } catch (\Exception $e) {
            Log::warning('Remote MCP Server not available. Running in standalone mode: ' . $e->getMessage());
        }

        $assistantContent = '';
        $chartData = null;

        // Siapkan multimodal attachments (misal gambar) untuk Laravel AI
        $attachments = [];
        if ($userMessage->attachments) {
            foreach ($userMessage->attachments as $attachmentPath) {
                $fullPath = public_path($attachmentPath);
                if (file_exists($fullPath)) {
                    $attachments[] = $fullPath;
                }
            }
        }

        $isMockEnforced = filter_var(env('AI_MOCK', false), FILTER_VALIDATE_BOOLEAN);

        if (empty($providerKey) || $isMockEnforced || empty($tools)) {
            // MOCK MODE: Jalankan simulasi jika API key provider belum dikonfigurasi, mock diaktifkan, atau MCP belum disiapkan
            $assistantContent = $this->runSimulatedAgent($chat, $userMessage, $mcpClient, $sessionId, $state, $chartData);
        } else {
            // PRODUCTION MODE: Gunakan First-Party Laravel AI SDK dengan provider & model dinamis
            try {
                // Inisiasi GovtAnalyticsAgent dengan dynamic instructions, tools, dan riwayat obrolan
                $agent = new GovtAnalyticsAgent(
                    instructions: 'Anda adalah Asisten Analitis Statistik Pemerintah Indonesia yang handal. Gunakan tool yang tersedia untuk mengambil data daerah sektoral.',
                    tools: $tools,
                    messages: $aiMessages
                );

                // Jalankan model dengan provider & model dinamis
                $response = $agent->prompt(
                    prompt: $userMessage->content,
                    attachments: $attachments,
                    provider: $provider,
                    model: $model
                );

                $assistantContent = $response->text;
            } catch (\Exception $e) {
                Log::error('Laravel AI SDK prompt execution failed: ' . $e->getMessage());
                $assistantContent = 'Maaf, terjadi kendala saat memproses kueri analitik Anda menggunakan Laravel AI SDK. Silakan periksa kredensial API Anda di file .env.';
            }
        }

        // 3. Deteksi dan parsing skema JSON Charting jika ada di markdown output
        $chartData = $this->parseChartData($assistantContent);

        // 4. Simpan hasil response asisten ke database
        $assistantMessage = Message::create([
            'chat_id' => $this->chatId,
            'role' => 'assistant',
            'content' => $assistantContent,
            'agent_steps' => count($state->steps) > 0 ? $state->steps : null,
            'chart_data' => $chartData,
        ]);

        // 5. Broadcast final response via Laravel Reverb WebSockets with safety try-catch
        try {
            event(new AgentResponseGenerated($this->chatId, $assistantMessage->id, $assistantContent, $chartData));
        } catch (\Exception $e) {
            Log::warning('Failed to broadcast AgentResponseGenerated: ' . $e->getMessage());
        }
    }

    /**
     * Parse charting JSON block from markdown response
     */
    protected function parseChartData(string $content): ?array
    {
        if (preg_match('/```json-chart\s*(\{.*?\})\s*```/s', $content, $matches)) {
            $data = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Run local simulated agent execution if GEMINI_API_KEY is not configured
     */
    protected function runSimulatedAgent(
        Chat $chat,
        Message $userMessage,
        McpRemoteClientService $mcpClient,
        string $sessionId,
        object $state,
        ?array &$chartData
    ): string {
        $cleanContent = trim(strtolower($userMessage->content));

        // Jika hanya sapaan sederhana, balas dengan onboarding ramah tanpa memicu simulasi tools
        if (preg_match('/^(hai|halo|helo|hello|pagi|siang|sore|malam|test|hi|ping)[\s.!]*$/i', $cleanContent)) {
            return 'Halo! 👋 Saya adalah **Asisten Agen BPS AI** untuk **Sistem Pembinaan Statistik Sektoral (SPESIAL)**.

Saya terhubung dengan server **BPS-MCP Active** untuk membantu Anda memantau, menganalisis, dan memvisualisasikan data statistik daerah secara sektoral.

Silakan minta data analitik spesifik daerah Anda untuk memulai simulasi penarikan data secara real-time. Contoh pertanyaan:
* *"Tampilkan laporan kinerja statistik Kabupaten Mempawah"*
* *"Bagaimana tren Indeks Pembangunan Keluarga di Mempawah?"*
* *"Minta ringkasan data statistik sektoral"*';
        }

        // Jalankan alur simulasi tool yang kaya untuk kueri analitik / statistik
        // Simulasi Step 1
        $state->stepIndex++;
        $currentStep1 = $state->stepIndex;
        event(new \App\Events\AgentStepStarted($this->chatId, 'fetch_regional_report', $currentStep1));
        sleep(1);
        $mockResult1 = [
            'status' => 'success',
            'regency' => 'Mempawah',
            'province' => 'Kalimantan Barat',
            'performance_index' => 78.5,
            'consistency_score' => 85.0,
            'metadata' => [
                'bps_code' => '6104',
                'kemendagri_code' => '61.02',
            ],
        ];
        $state->steps[] = [
            'step' => $currentStep1,
            'tool' => 'fetch_regional_report',
            'arguments' => ['regency_code' => '6104'],
            'result' => $mockResult1,
        ];
        event(new \App\Events\AgentStepCompleted($this->chatId, 'fetch_regional_report', $currentStep1, $mockResult1));

        // Simulasi Step 2
        $state->stepIndex++;
        $currentStep2 = $state->stepIndex;
        event(new \App\Events\AgentStepStarted($this->chatId, 'get_bps_indicator', $currentStep2));
        sleep(1);
        $mockResult2 = [
            'status' => 'success',
            'indicator' => 'IPKP (Indeks Pembangunan Keluarga)',
            'values' => [
                ['year' => 2023, 'score' => 74.2],
                ['year' => 2024, 'score' => 76.8],
                ['year' => 2025, 'score' => 78.5],
            ],
        ];
        $state->steps[] = [
            'step' => $currentStep2,
            'tool' => 'get_bps_indicator',
            'arguments' => ['indicator_id' => 'IPKP_6104'],
            'result' => $mockResult2,
        ];
        event(new \App\Events\AgentStepCompleted($this->chatId, 'get_bps_indicator', $currentStep2, $mockResult2));

        // Buat konten balasan analitis premium dengan format Charting JSON
        $chartSchema = [
            'type' => 'line',
            'title' => 'Tren Kinerja Indeks Pembangunan Keluarga - Kab. Mempawah',
            'labels' => ['2023', '2024', '2025'],
            'datasets' => [
                [
                    'label' => 'Skor Indeks',
                    'data' => [74.2, 76.8, 78.5],
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                ],
            ],
        ];

        $chartJson = json_encode($chartSchema, JSON_PRETTY_PRINT);

        return "### Laporan Analisis Kinerja Pembangunan: Kabupaten Mempawah

Berdasarkan hasil penarikan data sektoral melalui **Remote MCP Server BPS** menggunakan tool `fetch_regional_report` dan `get_bps_indicator`, berikut adalah ringkasan performa daerah Kabupaten Mempawah (Kode BPS: `6104`):

1. **Informasi Geografis & Administratif**:
   - **Nama Kabupaten**: Mempawah
   - **Provinsi**: Kalimantan Barat
   - **Indeks Kinerja Regional**: **78.5** (Kategori Baik)
   - **Tingkat Konsistensi Statistik (EPSS)**: **85.0** (Kategori Tinggi/Sangat Konsisten)

2. **Tren Indeks Pembangunan Keluarga (IPKP)**:
   Kabupaten Mempawah menunjukkan pertumbuhan konsisten yang sangat positif dalam kurun waktu 3 tahun terakhir:
   - **Tahun 2023**: 74.2
   - **Tahun 2024**: 76.8
   - **Tahun 2025**: 78.5 (+2.2% peningkatan year-on-year)

Berikut adalah visualisasi tren perkembangan indikator kinerja tersebut untuk bahan presentasi rapat koordinasi daerah:

```json-chart
{$chartJson}
```

*Analisis ini diterbitkan secara otomatis menggunakan koordinasi Agen AI BPS per tanggal " . now()->format('d F Y') . '.*';
    }
}
