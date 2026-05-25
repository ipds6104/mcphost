<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\BpsApiService;
use App\Services\DisclaimerInjectorService;
use App\Services\FactGraderService;
use App\Services\MethodologyRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * BpsDataIntegrityTest
 *
 * Suite test deterministik untuk memverifikasi 4 layer data integrity system:
 *   - Group A: Cache-Aside pattern dan graceful degradation
 *   - Group B: MethodologyRegistry — deteksi series breaks
 *   - Group C: DisclaimerInjector — injeksi disclaimer otomatis
 *   - Group D: FactGrader — numeric grounding dan source citation
 */
class BpsDataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed methodology breaks untuk semua tests
        $this->artisan('db:seed', ['--class' => 'BpsMethodologyBreaksSeeder']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // GROUP A: Cache-Aside Pattern & Graceful Degradation
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_cache_aside_saves_to_postgres_after_fresh_api_call(): void
    {
        // Mock HTTP response BPS
        Http::fake([
            '*/webapi.bps.go.id/*' => Http::response([
                'status' => 200,
                'data' => [
                    ['total' => 5],
                    [
                        ['id' => '1', 'title' => 'Tabel IPM 2023'],
                    ],
                ],
            ], 200),
        ]);

        Cache::store('redis')->flush();

        $service = new BpsApiService((string) config('services.bps.key', 'test-key'));
        $result = $service->fetchRegionalReport('6104');

        // Pastikan data tersimpan di PostgreSQL
        $this->assertDatabaseHas('bps_api_cache', [
            'cache_key' => 'bps_6104_statictable_list',
            'domain_code' => '6104',
            'is_stale' => false,
        ]);

        // Pastikan _meta disertakan
        $this->assertArrayHasKey('_meta', $result);
        $this->assertEquals('MISS_FRESH', $result['_meta']['cache_status']);
        $this->assertEquals('6104', $result['_meta']['domain_code']);
    }

    /** @test */
    public function test_second_call_returns_from_redis_cache(): void
    {
        Cache::store('redis')->flush();
        Http::fake([
            '*/webapi.bps.go.id/*' => Http::response(['status' => 200, 'data' => [[], []]], 200),
        ]);

        $service = new BpsApiService('test-key');

        // Panggil pertama (MISS_FRESH)
        $service->fetchRegionalReport('6104');

        // Panggil kedua (HIT_REDIS)
        $result = $service->fetchRegionalReport('6104');

        $this->assertEquals('HIT_REDIS', $result['_meta']['cache_status']);

        // HTTP hanya dipanggil sekali
        Http::assertSentCount(1);
    }

    /** @test */
    public function test_graceful_degradation_serves_stale_data_when_api_fails(): void
    {
        // Pre-seed stale data di PostgreSQL
        DB::table('bps_api_cache')->insert([
            'cache_key' => 'bps_6104_statictable_list',
            'domain_code' => '6104',
            'endpoint_type' => 'statictable_list',
            'response_json' => json_encode(['tables' => [['id' => '1']], '_fetched_at' => now()->subDay()->toIso8601String()]),
            'fetched_at' => now()->subDay(),
            'expires_at' => now()->subHour(), // sudah expired
            'is_stale' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // API BPS gagal
        Http::fake(['*' => Http::response('', 500)]);
        Cache::store('redis')->flush();

        $service = new BpsApiService('test-key');
        $result = $service->fetchRegionalReport('6104');

        // Harus serve stale data, bukan throw exception
        $this->assertEquals('STALE', $result['_meta']['cache_status']);
        $this->assertNotNull($result['_meta']['data_warning']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // GROUP B: MethodologyRegistry — Series Break Detection
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_ipm_series_break_2014_detected_for_range_2010_to_2023(): void
    {
        $registry = new MethodologyRegistryService();
        $breaks = $registry->detectBreaks('IPM', 2010, 2023);

        $this->assertNotEmpty($breaks, 'Harus mendeteksi setidaknya 1 break untuk IPM 2010-2023');
        $this->assertContains(2014, array_column($breaks, 'break_year'));
    }

    /** @test */
    public function test_ipm_no_break_detected_for_same_methodology_range(): void
    {
        $registry = new MethodologyRegistryService();

        // 2023-2024 adalah dalam metodologi yang sama (post-revisi 2023)
        $breaks = $registry->detectBreaks('IPM', 2023, 2024);

        $this->assertEmpty($breaks, 'Tidak boleh ada break untuk IPM 2023-2024 (metodologi sama)');
    }

    /** @test */
    public function test_ipm_safe_to_compare_returns_false_across_2014_break(): void
    {
        $registry = new MethodologyRegistryService();

        $this->assertFalse(
            $registry->isSafeToCompare('IPM', 2012, 2020),
            'IPM 2012 vs 2020 seharusnya TIDAK aman (melewati break 2014)'
        );
    }

    /** @test */
    public function test_ipm_safe_to_compare_returns_true_within_same_methodology(): void
    {
        $registry = new MethodologyRegistryService();

        $this->assertTrue(
            $registry->isSafeToCompare('IPM', 2023, 2024),
            'IPM 2023 vs 2024 seharusnya AMAN (metodologi sama post-2023)'
        );
    }

    /** @test */
    public function test_pdrb_base_year_2010_break_detected(): void
    {
        $registry = new MethodologyRegistryService();
        $breaks = $registry->detectBreaks('PDRB', 2005, 2015);

        $this->assertNotEmpty($breaks);
        $this->assertContains(2010, array_column($breaks, 'break_year'));
    }

    /** @test */
    public function test_sector_aliases_resolve_correctly(): void
    {
        $registry = new MethodologyRegistryService();

        $this->assertEquals('IPM', $registry->canonicalizeSector('pembangunan manusia'));
        $this->assertEquals('PDRB', $registry->canonicalizeSector('pertumbuhan ekonomi'));
        $this->assertEquals('IHK', $registry->canonicalizeSector('inflasi'));
        $this->assertEquals('Kemiskinan', $registry->canonicalizeSector('garis kemiskinan'));
        $this->assertNull($registry->canonicalizeSector('cuaca hari ini'));
    }

    /** @test */
    public function test_extract_years_finds_correct_years_in_text(): void
    {
        $registry = new MethodologyRegistryService();
        $text = 'IPM Mempawah meningkat dari 68,70 (2021) ke 70,13 (2023). Bandingkan dengan data 2014.';

        $years = $registry->extractYearsFromText($text);

        $this->assertContains(2021, $years);
        $this->assertContains(2023, $years);
        $this->assertContains(2014, $years);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // GROUP C: DisclaimerInjector
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_disclaimer_injected_for_ipm_spanning_2014_break(): void
    {
        $service = new DisclaimerInjectorService(new MethodologyRegistryService());

        $content = 'Tren IPM Mempawah dari tahun 2010 hingga 2023 menunjukkan peningkatan signifikan.
IPM 2010: 64,23 | IPM 2023: 70,13';

        $result = $service->process($content, []);

        $this->assertStringContainsString('⚠️', $result, 'Harus mengandung disclaimer warning');
        $this->assertStringContainsString('diskontinuitas', $result);
        $this->assertStringContainsString('2014', $result);
    }

    /** @test */
    public function test_no_disclaimer_for_ipm_within_same_methodology(): void
    {
        $service = new DisclaimerInjectorService(new MethodologyRegistryService());

        $content = 'IPM Mempawah 2023: 70,13 dan IPM 2024 (estimasi): 71,2';

        $result = $service->process($content, []);

        // Tidak boleh ada disclaimer karena hanya dalam 1 tahun (tidak ada break 2023-2024)
        $this->assertStringNotContainsString('diskontinuitas metodologi BPS', $result);
    }

    /** @test */
    public function test_source_citation_injected_when_bps_tools_used(): void
    {
        $service = new DisclaimerInjectorService(new MethodologyRegistryService());

        $toolSteps = [[
            'tool' => 'fetch_regional_report',
            'result' => [
                '_meta' => [
                    'source' => 'BPS WebAPI v1',
                    'fetched_at' => '2026-05-25T07:00:00+07:00',
                    'cache_status' => 'MISS_FRESH',
                ],
            ],
        ]];

        $content = 'IPM Kabupaten Mempawah tahun 2023 adalah 70,13.';
        $result = $service->process($content, $toolSteps);

        $this->assertStringContainsString('webapi.bps.go.id', $result);
        $this->assertStringContainsString('Sumber Data', $result);
    }

    /** @test */
    public function test_no_duplicate_citation_if_already_present(): void
    {
        $service = new DisclaimerInjectorService(new MethodologyRegistryService());

        $toolSteps = [[
            'result' => ['_meta' => ['fetched_at' => now()->toIso8601String()]],
        ]];

        $content = "IPM 2023: 70,13\n\nSumber: BPS WebAPI (webapi.bps.go.id)";
        $result = $service->process($content, $toolSteps);

        // Hitung kemunculan "webapi.bps.go.id" — tidak boleh duplikat
        $occurrences = substr_count($result, 'webapi.bps.go.id');
        $this->assertLessThanOrEqual(2, $occurrences, 'Citation tidak boleh diduplikasi');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // GROUP D: FactGrader — Numeric Grounding
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_fact_grader_preserves_correct_values_within_tolerance(): void
    {
        $steps = [[
            'tool' => 'fetch_regional_report',
            'result' => ['ipm' => 70.13],
        ]];

        // LLM menulis 70.13 — tepat sama dengan tool result
        $content = 'IPM Mempawah 2023 adalah 70,13 poin.';
        $result = FactGraderService::verifyAndCorrect($content, $steps);

        $this->assertStringContainsString('70,13', $result, 'Nilai yang benar tidak boleh diubah');
    }

    /** @test */
    public function test_disclaimer_service_instantiates_without_errors(): void
    {
        $registry = new MethodologyRegistryService();
        $injector = new DisclaimerInjectorService($registry);

        $result = $injector->process('Tidak ada data statistik di sini.', []);

        $this->assertIsString($result);
        $this->assertEquals('Tidak ada data statistik di sini.', $result);
    }

    /** @test */
    public function test_bps_service_serves_data_from_ground_truth_table_correctly(): void
    {
        // Masukkan data test ke database
        DB::table('bps_ground_truths')->insert([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'domain_code' => '6104',
            'year' => 2024,
            'indicator_code' => 'IPM',
            'value' => 69.63,
            'notes' => 'Test ground truth value',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new BpsApiService('test-key');
        $result = $service->getBpsIndicator('6104', 'IPM');

        $this->assertEquals('6104', $result['domain_code']);
        $this->assertEquals('IPM', $result['table_id']);
        $this->assertEquals('HIT_GROUND_TRUTH_LOCAL', $result['_meta']['cache_status']);

        // Pastikan nilainya ada di dalam data
        $foundValue = false;
        foreach ($result['data'] as $row) {
            if (isset($row['year']) && $row['year'] === '2024' && isset($row['value']) && $row['value'] === '69.63') {
                $foundValue = true;
            }
        }
        $this->assertTrue($foundValue, 'Angka ground-truth harus ada di dalam payload data');
    }

    /** @test */
    public function test_output_shield_intercepts_hallucination_when_restricted(): void
    {
        $steps = [[
            'tool' => 'get_bps_indicator',
            'result' => [
                'error' => 'BPS_API_RESTRICTED',
                'message' => 'Akses data dinamis BPS ditolak/dibatasi (Allowed to take this action).',
                'is_restricted' => true,
                'grounding_failed' => true,
            ],
        ]];

        // LLM menulis angka karangan "70,61" ketika API BPS restricted
        $hallucinatedContent = 'IPM Kabupaten Mempawah tahun 2024 adalah 70,61.';
        $result = FactGraderService::verifyAndCorrect($hallucinatedContent, $steps);

        // Harus di-intersep dan digantikan oleh pesan penolakan formal
        $this->assertStringContainsString('Keterbatasan Layanan Data BPS', $result);
        $this->assertStringContainsString('integritas informasi publik', $result);
        $this->assertStringNotContainsString('70,61', $result);
    }
}
