<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Events\AgentStepCompleted;
use App\Events\AgentStepStarted;
use App\Services\MockMcpToolProvider;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * MockMcpTool
 *
 * Implementasi Tool yang menggunakan MockMcpToolProvider sebagai sumber data lokal.
 * Mempertahankan seluruh perilaku broadcasting WebSocket (AgentStepStarted/Completed).
 *
 * PENTING: method schema() HARUS mengembalikan array berisi JsonSchema builder objects
 * (bukan raw PHP array). SDK Laravel AI tidak bisa serialize array biasa.
 * Format yang benar: ['field' => $schema->string()->description('...')->required()]
 */
class MockMcpTool implements Tool
{
    public function __construct(
        protected string $toolName,
        protected string $toolDescription,
        protected MockMcpToolProvider $provider,
        protected string $chatId,
        protected object $state,
    ) {}

    public function name(): string
    {
        return $this->toolName;
    }

    public function description(): Stringable|string
    {
        return $this->toolDescription;
    }

    public function handle(Request $request): Stringable|string
    {
        $this->state->stepIndex++;
        $currentStep = $this->state->stepIndex;

        event(new AgentStepStarted($this->chatId, $this->toolName, $currentStep));

        $result = $this->provider->callTool($this->toolName, $request->all());

        $this->state->steps[] = [
            'step' => $currentStep,
            'tool' => $this->toolName,
            'arguments' => $request->all(),
            'result' => $result,
        ];

        event(new AgentStepCompleted($this->chatId, $this->toolName, $currentStep, $result));

        return json_encode($result);
    }

    /**
     * Mendefinisikan skema input tool menggunakan JsonSchema builder SDK.
     *
     * SDK menggunakan refleksi untuk meng-serialize return value ini ke format
     * JSON Schema yang dimengerti OpenAI API. Setiap value HARUS merupakan
     * hasil dari method builder $schema (bukan raw array atau primitive).
     *
     * Kita mendefinisikan skema umum yang valid untuk semua 4 tool kita,
     * karena LLM yang akan memutuskan argumen mana yang relevan.
     */
    public function schema(JsonSchema $schema): array
    {
        return match ($this->toolName) {
            'fetch_regional_report' => [
                'regency_code' => $schema->string()
                    ->description('Kode BPS kabupaten/kota (contoh: 6104 untuk Mempawah, 6101 untuk Sambas, 6103 untuk Pontianak)')
                    ->required(),
                'year' => $schema->integer()
                    ->description('Tahun laporan yang diminta (contoh: 2025). Opsional, default tahun terbaru.'),
            ],

            'get_bps_indicator' => [
                'indicator_id' => $schema->string()
                    ->description('ID indikator BPS (contoh: IPKP_6104 untuk IPKP Mempawah, EPSS_6104, IKP_6104)')
                    ->required(),
            ],

            'compare_regencies' => [
                'regency_codes' => $schema->array()
                    ->items($schema->string())
                    ->description('Daftar kode BPS kabupaten yang ingin dibandingkan (contoh: ["6104", "6101", "6103"])')
                    ->required(),
                'metric' => $schema->string()
                    ->description('Metrik perbandingan: IPKP, EPSS, atau IKP'),
            ],

            'search_statistics' => [
                'query' => $schema->string()
                    ->description('Kata kunci pencarian statistik BPS (contoh: "pembangunan keluarga", "ekonomi", "IPM")')
                    ->required(),
                'domain' => $schema->string()
                    ->description('Domain statistik: demografi, ekonomi, sosial, infrastruktur, statistik'),
            ],

            // Fallback schema untuk tool yang tidak dikenal
            default => [
                'query' => $schema->string()
                    ->description('Parameter pencarian atau instruksi untuk tool ini')
                    ->required(),
            ],
        };
    }
}
