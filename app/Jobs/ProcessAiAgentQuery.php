<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\GovtAnalyticsAgent;
use App\Ai\Tools\MockMcpTool;
use App\Events\AgentResponseGenerated;
use App\Models\Message;
use App\Services\MockMcpToolProvider;
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
     * Create a new job instance.
     */
    public function __construct(
        public string $chatId,
        public int $userMessageId
    ) {}

    /**
     * Execute the job.
     *
     * Alur baru yang benar:
     * 1. Buat trace context untuk observabilitas
     * 2. Muat riwayat percakapan dari DB (konteks AI)
     * 3. Inisialisasi MockMcpTool sebagai tools yang dapat dipanggil LLM
     * 4. Jalankan LLM (selalu — jika ada API key); jika tidak, kembalikan pesan error informatif
     * 5. Parsing chart data dari respons
     * 6. Simpan ke DB & broadcast via WebSocket
     * 7. Log trace selesai
     */
    public function handle(MockMcpToolProvider $mockProvider): void
    {
        // ┌─────────────────────────────────────────────────────────────┐
        // │  TRACE CONTEXT — Structured Observability (Standar Ke-13)  │
        // │  Setiap eksekusi job menghasilkan trace_id unik untuk       │
        // │  korelasi lintas logs, WebSocket events, dan DB queries.    │
        // └─────────────────────────────────────────────────────────────┘
        $traceId = (string) Str::uuid();
        $startedAt = hrtime(true);

        $userMessage = Message::findOrFail($this->userMessageId);

        // ── Baca konfigurasi AI dari config() — BUKAN env() ─────────────
        // PENTING: env() tidak berfungsi di jobs setelah `php artisan optimize`
        // karena config sudah di-cache. Selalu gunakan config() untuk runtime.
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

        // ── 2. Inisialisasi Mock MCP Tools dari MockMcpToolProvider ──────
        $state = (object) ['stepIndex' => 0, 'steps' => []];

        $mockToolDefs = $mockProvider->getTools();
        $tools = array_map(fn (array $toolDef) => new MockMcpTool(
            toolName: $toolDef['name'],
            toolDescription: $toolDef['description'],
            provider: $mockProvider,
            chatId: $this->chatId,
            state: $state,
        ), $mockToolDefs);

        Log::channel('ai_agent')->debug('ai_agent.mock_tools_ready', [
            'trace_id' => $traceId,
            'chat_id' => $this->chatId,
            'tool_count' => count($tools),
            'tool_names' => array_column($mockToolDefs, 'name'),
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
                    instructions: implode(' ', [
                        'Anda adalah Asisten Analitis Statistik Pemerintah Indonesia yang handal dan cerdas.',
                        'Anda memiliki akses ke tools BPS (Badan Pusat Statistik) untuk mengambil data statistik daerah.',
                        'Gunakan tools hanya jika pertanyaan pengguna memerlukan data statistik spesifik.',
                        'Untuk pertanyaan umum, jawab langsung dengan pengetahuan Anda tanpa memanggil tools.',
                        'Selalu gunakan Bahasa Indonesia yang baik dan profesional.',
                        'Format jawaban dengan Markdown untuk keterbacaan yang optimal.',
                    ]),
                    tools: $tools,
                    messages: $aiMessages
                );

                $response = $agent->prompt(
                    prompt: $userMessage->content,
                    attachments: $attachments,
                    provider: $provider,
                    model: $model
                );

                $assistantContent = $response->text;

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
}
