<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Events\AgentStepCompleted;
use App\Events\AgentStepStarted;
use App\Services\BpsApiService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * BpsApiTool
 *
 * Implementasi Tool Laravel AI SDK yang menggunakan BpsApiService sebagai sumber data.
 * Menggantikan MockMcpTool secara permanen — seluruh data berasal dari API BPS resmi.
 *
 * Setiap tool result menyertakan blok _meta dengan:
 *   - source       : "BPS WebAPI v1 (webapi.bps.go.id)"
 *   - fetched_at   : timestamp aktual pengambilan data
 *   - cache_status : HIT_REDIS | HIT_POSTGRES | MISS_FRESH | STALE
 *
 * LLM WAJIB mencantumkan informasi ini dalam setiap respons yang mengandung angka statistik.
 */
class BpsApiTool implements Tool
{
    public function __construct(
        protected string $toolName,
        protected string $toolDescription,
        protected BpsApiService $bpsService,
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
        $args = $request->all();

        event(new AgentStepStarted($this->chatId, $this->toolName, $currentStep));

        $result = $this->dispatch($args);

        $this->state->steps[] = [
            'step' => $currentStep,
            'tool' => $this->toolName,
            'arguments' => $args,
            'result' => $result,
        ];

        event(new AgentStepCompleted($this->chatId, $this->toolName, $currentStep, $result));

        return json_encode($result);
    }

    /**
     * Routing ke metode BpsApiService yang sesuai berdasarkan nama tool.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function dispatch(array $args): array
    {
        try {
            return match ($this->toolName) {
                'fetch_regional_report' => $this->bpsService->fetchRegionalReport(
                    domainCode: (string) ($args['regency_code'] ?? '6104'),
                    year: (int) ($args['year'] ?? 0),
                ),
                'get_bps_indicator' => $this->bpsService->getBpsIndicator(
                    domainCode: (string) ($args['domain_code'] ?? '6104'),
                    tableId: (string) ($args['table_id'] ?? ''),
                ),
                'compare_regencies' => $this->bpsService->compareRegencies(
                    domainCodes: (array) ($args['regency_codes'] ?? ['6104']),
                    metric: (string) ($args['metric'] ?? 'statictable'),
                ),
                'search_statistics' => $this->bpsService->searchStatistics(
                    domainCode: (string) ($args['domain_code'] ?? '6104'),
                    query: (string) ($args['query'] ?? ''),
                    page: (int) ($args['page'] ?? 1),
                ),
                default => ['error' => "Tool '{$this->toolName}' tidak ditemukan dalam katalog BPS API."],
            };
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                '_meta' => [
                    'source' => 'BPS WebAPI v1 (webapi.bps.go.id)',
                    'cache_status' => 'ERROR',
                    'fetched_at' => now()->toIso8601String(),
                ],
            ];
        }
    }

    /**
     * Definisi skema input tool menggunakan JsonSchema builder SDK.
     * Skema ini digunakan oleh LLM untuk menentukan argumen yang akan dikirim.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return match ($this->toolName) {
            'fetch_regional_report' => [
                'regency_code' => $schema->string()
                    ->description('Kode domain BPS kabupaten/kota 4 digit (contoh: "6104" untuk Mempawah, "6404" untuk Kutai Timur, "3171" untuk Jakarta Selatan)')
                    ->required(),
                'year' => $schema->integer()
                    ->description('Tahun laporan opsional (contoh: 2023). Jika tidak diisi, data terbaru yang diambil.'),
            ],

            'get_bps_indicator' => [
                'domain_code' => $schema->string()
                    ->description('Kode domain wilayah BPS 4 digit (contoh: "6104")')
                    ->required(),
                'table_id' => $schema->string()
                    ->description('ID tabel statis BPS (dapatkan dari fetch_regional_report terlebih dahulu)')
                    ->required(),
            ],

            'compare_regencies' => [
                'regency_codes' => $schema->array()
                    ->items($schema->string())
                    ->description('Daftar kode domain BPS wilayah yang dibandingkan (contoh: ["6104", "6404", "3171"])')
                    ->required(),
                'metric' => $schema->string()
                    ->description('Jenis metrik perbandingan: "statictable" (default) atau "var"'),
            ],

            'search_statistics' => [
                'domain_code' => $schema->string()
                    ->description('Kode domain BPS untuk pencarian (contoh: "6104")')
                    ->required(),
                'query' => $schema->string()
                    ->description('Kata kunci pencarian variabel/indikator (contoh: "IPM", "PDRB", "kemiskinan")')
                    ->required(),
                'page' => $schema->integer()
                    ->description('Halaman hasil (default: 1)'),
            ],

            default => [
                'query' => $schema->string()
                    ->description('Parameter pencarian atau instruksi untuk tool ini')
                    ->required(),
            ],
        };
    }
}
