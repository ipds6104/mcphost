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

        // ── 2. Muat Mock MCP Tools Secara Langsung ────────
        $state = (object) ['stepIndex' => 0, 'steps' => []];
        $tools = [];

        $mockProvider = new MockMcpToolProvider();
        foreach ($mockProvider->getTools() as $toolData) {
            $tools[] = new MockMcpTool(
                toolName: $toolData['name'],
                toolDescription: $toolData['description'],
                provider: $mockProvider,
                chatId: $this->chatId,
                state: $state
            );
        }

        Log::channel('ai_agent')->debug('ai_agent.mock_tools_ready', [
            'trace_id' => $traceId,
            'chat_id' => $this->chatId,
            'tool_count' => count($tools),
            'tool_names' => array_map(fn ($t) => $t->name(), $tools),
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
                        '# FORMAT OUTPUT WAJIB',
                        '- Gunakan Bahasa Indonesia yang baik, formal, dan profesional.',
                        '- Gunakan tabel Markdown GFM (pipe | dan tanda hubung -) untuk data tabular.',
                        '- Gunakan heading ## dan ### untuk struktur laporan yang jelas.',
                        '- Sertakan kesimpulan strategis dan rekomendasi kebijakan di akhir laporan.',
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
