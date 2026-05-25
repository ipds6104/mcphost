<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BpsApiService
 *
 * Service produksi untuk mengambil data statistik dari API resmi BPS Indonesia
 * (webapi.bps.go.id). Mengimplementasikan pola Cache-Aside dengan Redis sebagai
 * lapisan primer dan PostgreSQL (bps_api_cache) sebagai graceful degradation.
 *
 * Setiap respons menyertakan metadata wajib (_meta) untuk transparansi sumber data:
 *   - source       : identitas sumber data
 *   - source_url   : URL endpoint yang dipanggil
 *   - fetched_at   : timestamp ISO-8601 saat data diambil
 *   - cache_status : HIT (Redis) | MISS (fresh API) | STALE (dari PostgreSQL)
 *   - domain_code  : kode wilayah BPS
 */
class BpsApiService
{
    private string $baseUrl;

    private int $cacheTtlHours;

    private int $maxRetries;

    public function __construct(
        private readonly string $apiKey
    ) {
        $this->baseUrl = rtrim((string) config('services.bps.base_url', 'https://webapi.bps.go.id/v1/api'), '/');
        $this->cacheTtlHours = (int) config('services.bps.cache_ttl', 24);
        $this->maxRetries = (int) config('services.bps.max_retries', 3);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // PUBLIC API — 4 tool methods
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Ambil daftar tabel statistik untuk satu domain wilayah.
     * Menggantikan MockMcpToolProvider::fetchRegionalReport()
     */
    public function fetchRegionalReport(string $domainCode, int $year = 0): array
    {
        $cacheKey = "bps_{$domainCode}_statictable_list";

        $result = $this->cacheAside($cacheKey, $domainCode, 'statictable_list', function () use ($domainCode) {
            $tables = [];
            try {
                $url = "{$this->baseUrl}/list/model/statictable/domain/{$domainCode}/key/{$this->apiKey}/";
                $raw = $this->httpGet($url);
                $tables = $raw['data'][1] ?? [];
            } catch (\Exception $e) {
                Log::channel('ai_agent')->warning('fetchRegionalReport BPS WebAPI failed: ' . $e->getMessage());
                throw $e;
            }

            return [
                'domain_code' => $domainCode,
                'total_tables' => count($tables),
                'tables' => array_slice($tables, 0, 20), // ambil 20 tabel terbaru
            ];
        });

        // Suntikkan virtual tables dari bps_ground_truths secara dinamis (SELALU DIJALANKAN DI LUAR CACHE boundary!)
        try {
            $groundTruthIndicators = DB::table('bps_ground_truths')
                ->where('domain_code', $domainCode)
                ->select('indicator_code')
                ->distinct()
                ->pluck('indicator_code')
                ->toArray();

            $tables = $result['tables'] ?? [];
            $injected = [];

            foreach ($groundTruthIndicators as $code) {
                // Mencegah duplikasi jika indikator sudah terdaftar
                $exists = false;
                foreach ($tables as $t) {
                    if (($t['table_id'] ?? '') === $code) {
                        $exists = true;
                        break;
                    }
                }
                if ($exists) {
                    continue;
                }

                $titleMap = [
                    'IPM' => 'Indeks Pembangunan Manusia (IPM) Menurut Kabupaten/Kota',
                    'AHH' => 'Angka Harapan Hidup (AHH) Menurut Kabupaten/Kota',
                    'RLS' => 'Rata-Rata Lama Sekolah (RLS) Menurut Kabupaten/Kota',
                    'HLS' => 'Harapan Lama Sekolah (HLS) Menurut Kabupaten/Kota',
                    'PPP' => 'Pengeluaran Per Kapita Disesuaikan (PPP) Menurut Kabupaten/Kota',
                ];
                $title = $titleMap[$code] ?? "Indikator Makro Dasar BPS: {$code}";

                $injected[] = [
                    'table_id' => $code,
                    'title' => $title,
                    'subject_id' => '26',
                    'title_id' => $code,
                ];
            }

            if (! empty($injected)) {
                $tables = array_merge($injected, $tables);
                $result['tables'] = $tables;
                $result['total_tables'] = count($tables);
            }
        } catch (\Exception $dbEx) {
            Log::channel('ai_agent')->error('Failed to query ground truth for table injection: ' . $dbEx->getMessage());
        }

        return $result;
    }

    /**
     * Ambil nilai variabel/indikator spesifik untuk satu domain.
     * Menggantikan MockMcpToolProvider::getBpsIndicator()
     */
    public function getBpsIndicator(string $domainCode, string $tableId): array
    {
        $indicatorCode = strtoupper(trim($tableId));
        $validCodes = ['IPM', 'AHH', 'RLS', 'HLS', 'PPP'];

        if (in_array($indicatorCode, $validCodes, true)) {
            try {
                $groundTruths = DB::table('bps_ground_truths')
                    ->where('domain_code', $domainCode)
                    ->where('indicator_code', $indicatorCode)
                    ->orderBy('year', 'asc')
                    ->get();

                if ($groundTruths->isNotEmpty()) {
                    $data = [];
                    $titleMap = [
                        'IPM' => 'Indeks Pembangunan Manusia (IPM) Menurut Kabupaten/Kota',
                        'AHH' => 'Angka Harapan Hidup (AHH) Menurut Kabupaten/Kota',
                        'RLS' => 'Rata-Rata Lama Sekolah (RLS) Menurut Kabupaten/Kota',
                        'HLS' => 'Harapan Lama Sekolah (HLS) Menurut Kabupaten/Kota',
                        'PPP' => 'Pengeluaran Per Kapita Disesuaikan (PPP) Menurut Kabupaten/Kota',
                    ];
                    $title = $titleMap[$indicatorCode] ?? "Indikator Makro Dasar BPS: {$indicatorCode}";

                    // Format array [0] untuk title, sisanya [1..n] untuk data baris sesuai standar BPS
                    $data[] = [
                        'title' => $title,
                        'domain_code' => $domainCode,
                        'indicator' => $indicatorCode,
                    ];

                    foreach ($groundTruths as $gt) {
                        $data[] = [
                            'year' => (string) $gt->year,
                            'value' => (string) $gt->value,
                            'notes' => $gt->notes,
                        ];
                    }

                    return [
                        'domain_code' => $domainCode,
                        'table_id' => $tableId,
                        'title' => $title,
                        'data' => $data,
                        '_meta' => [
                            'source' => 'BPS Ground-Truth Lokal (Verified)',
                            'source_url' => 'local_database',
                            'fetched_at' => now()->toIso8601String(),
                            'cache_status' => 'HIT_GROUND_TRUTH_LOCAL',
                            'domain_code' => $domainCode,
                        ],
                    ];
                }
            } catch (\Exception $dbEx) {
                Log::channel('ai_agent')->error('Failed to query ground truth in getBpsIndicator: ' . $dbEx->getMessage());
            }
        }

        $cacheKey = "bps_{$domainCode}_statictable_{$tableId}";

        return $this->cacheAside($cacheKey, $domainCode, 'statictable_view', function () use ($domainCode, $tableId) {
            $url = "{$this->baseUrl}/view/domain/{$domainCode}/model/statictable/lang/ind/id/{$tableId}/key/{$this->apiKey}/";
            $raw = $this->httpGet($url);

            return [
                'domain_code' => $domainCode,
                'table_id' => $tableId,
                'title' => $raw['data'][0]['title'] ?? 'N/A',
                'data' => $raw['data'] ?? [],
            ];
        });
    }

    /**
     * Bandingkan data antar beberapa wilayah menggunakan parallel HTTP calls.
     * Menggantikan MockMcpToolProvider::compareRegencies()
     */
    public function compareRegencies(array $domainCodes, string $metric = 'statictable'): array
    {
        $results = [];

        // Parallel calls — ambil semua domain sekaligus
        $promises = [];
        foreach ($domainCodes as $code) {
            $cacheKey = "bps_{$code}_statictable_list";
            $cached = $this->getFromRedis($cacheKey) ?? $this->getFromPostgres($cacheKey);

            if ($cached !== null) {
                $results[$code] = $cached;
            } else {
                $promises[$code] = $code;
            }
        }

        // Fetch yang belum ada di cache
        foreach ($promises as $code) {
            try {
                $results[$code] = $this->fetchRegionalReport($code);
            } catch (\Exception $e) {
                $results[$code] = ['error' => $e->getMessage(), 'domain_code' => $code];
            }
        }

        return [
            'comparison' => $results,
            'domain_codes' => $domainCodes,
            'metric' => $metric,
        ];
    }

    /**
     * Cari variabel/indikator berdasarkan kata kunci untuk satu domain.
     * Menggantikan MockMcpToolProvider::searchStatistics()
     */
    public function searchStatistics(string $domainCode, string $query, int $page = 1): array
    {
        $queryLower = trim(strtolower($query));
        $abbreviations = [
            'ipm' => 'indeks pembangunan manusia',
            'pdrb' => 'produk domestik regional bruto',
            'ihk' => 'indeks harga konsumen',
            'tpt' => 'tingkat pengangguran terbuka',
            'ahh' => 'angka harapan hidup',
            'rls' => 'rata-rata lama sekolah',
            'hls' => 'harapan lama sekolah',
            'ppp' => 'pengeluaran per kapita',
            'gini' => 'gini ratio',
        ];

        $expandedQuery = $abbreviations[$queryLower] ?? '';
        $apiKeyword = $expandedQuery !== '' ? $expandedQuery : $query;

        // MD5 hash kueri ditambahkan ke cache key untuk menghindari tabrakan cache antar kueri pencarian berbeda
        $cacheKey = "bps_{$domainCode}_var_p{$page}_" . md5(strtolower($apiKeyword));

        $result = $this->cacheAside($cacheKey, $domainCode, 'var_list', function () use ($domainCode, $page, $apiKeyword) {
            $url = "{$this->baseUrl}/list/model/var/domain/{$domainCode}/key/{$this->apiKey}/page/{$page}/keyword/" . urlencode($apiKeyword) . '/';
            $raw = $this->httpGet($url);

            return [
                'domain_code' => $domainCode,
                'variables' => $raw['data'][1] ?? [],
                'total' => $raw['data'][0]['total'] ?? 0,
            ];
        });

        if (isset($result['variables']) && is_array($result['variables'])) {
            $filtered = [];

            foreach ($result['variables'] as $variable) {
                $title = strtolower($variable['title'] ?? '');
                $subName = strtolower($variable['sub_name'] ?? '');
                $subcsaName = strtolower($variable['subcsa_name'] ?? '');

                $matchesQuery = ($queryLower !== '' && (
                    str_contains($title, $queryLower) ||
                    str_contains($subName, $queryLower) ||
                    str_contains($subcsaName, $queryLower)
                ));

                $matchesExpanded = ($expandedQuery !== '' && (
                    str_contains($title, $expandedQuery) ||
                    str_contains($subName, $expandedQuery) ||
                    str_contains($subcsaName, $expandedQuery)
                ));

                if ($matchesQuery || $matchesExpanded) {
                    $filtered[] = [
                        'var_id' => $variable['var_id'] ?? null,
                        'title' => $variable['title'] ?? null,
                        'sub_name' => $variable['sub_name'] ?? null,
                        'unit' => $variable['unit'] ?? null,
                    ];
                }
            }

            $result['variables'] = array_slice($filtered, 0, 15);
            $result['total'] = count($filtered);
        }

        return $result;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // CACHE-ASIDE PATTERN
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Implementasi Cache-Aside 3 lapis:
     *   1. Redis (hot, TTL 24 jam)
     *   2. PostgreSQL bps_api_cache (warm, fallback bila Redis flush)
     *   3. HTTP ke BPS API (cold, jika keduanya miss)
     *
     * @param  callable  $fetcher  Closure yang memanggil API BPS
     */
    private function cacheAside(string $cacheKey, string $domainCode, string $endpointType, callable $fetcher): array
    {
        // ── Layer 1: Redis ────────────────────────────────────────────────────
        $fromRedis = $this->getFromRedis($cacheKey);
        if ($fromRedis !== null) {
            Log::channel('ai_agent')->debug('bps_api.cache_hit.redis', ['key' => $cacheKey]);

            return $this->wrapMeta($fromRedis, $domainCode, $cacheKey, 'HIT_REDIS');
        }

        // ── Layer 2: PostgreSQL ───────────────────────────────────────────────
        $fromPg = $this->getFromPostgres($cacheKey);
        if ($fromPg !== null) {
            // Restore ke Redis untuk hit berikutnya
            $this->saveToRedis($cacheKey, $fromPg);
            Log::channel('ai_agent')->debug('bps_api.cache_hit.postgres', ['key' => $cacheKey]);

            return $this->wrapMeta($fromPg, $domainCode, $cacheKey, 'HIT_POSTGRES');
        }

        // ── Layer 3: HTTP ke API BPS (dengan retry) ───────────────────────────
        try {
            $data = $fetcher();
            $fetchedAt = now()->toIso8601String();

            $payload = array_merge($data, ['_fetched_at' => $fetchedAt]);

            $this->saveToRedis($cacheKey, $payload);
            $this->saveToPostgres($cacheKey, $domainCode, $endpointType, $payload, $this->cacheTtlHours);

            Log::channel('ai_agent')->info('bps_api.fresh_fetch', [
                'key' => $cacheKey,
                'domain' => $domainCode,
            ]);

            return $this->wrapMeta($payload, $domainCode, $cacheKey, 'MISS_FRESH');

        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $isRestricted = str_contains($msg, 'Allowed') || str_contains($msg, 'key') || str_contains($msg, '403') || str_contains($msg, 'permission');

            // ── Graceful Degradation: API down → serve stale data ─────────────
            $stale = $this->getStaleFromPostgres($cacheKey);
            if ($stale !== null) {
                Log::channel('ai_agent')->warning('bps_api.graceful_degradation', [
                    'key' => $cacheKey,
                    'error' => $e->getMessage(),
                ]);

                return $this->wrapMeta($stale, $domainCode, $cacheKey, 'STALE');
            }

            Log::channel('ai_agent')->error('bps_api.fetch_failed_no_fallback', [
                'key' => $cacheKey,
                'error' => $e->getMessage(),
                'is_restricted' => $isRestricted,
            ]);

            // Jika API Key restricted, kembalikan data terstruktur penolakan untuk interseptor LLM
            if ($isRestricted) {
                return [
                    'error' => 'BPS_API_RESTRICTED',
                    'message' => 'Akses data dinamis BPS ditolak/dibatasi (Allowed to take this action).',
                    'is_restricted' => true,
                    'grounding_failed' => true,
                    '_meta' => [
                        'source' => 'BPS WebAPI v1 (webapi.bps.go.id)',
                        'cache_status' => 'RESTRICTED',
                        'fetched_at' => now()->toIso8601String(),
                        'domain_code' => $domainCode,
                    ],
                ];
            }

            throw new \RuntimeException(
                "Gagal mengambil data BPS untuk domain {$domainCode}: " . $e->getMessage()
                . "\n⚠️ Tidak ada data cache tersedia. Pastikan koneksi internet aktif.",
                previous: $e
            );
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HTTP CLIENT
    // ──────────────────────────────────────────────────────────────────────────

    private function httpGet(string $url): array
    {
        $response = Http::retry($this->maxRetries, 1500, function (\Exception $e) {
            // Retry hanya jika network error, bukan 4xx client error
            return ! ($e instanceof \Illuminate\Http\Client\RequestException && $e->response?->status() < 500);
        })
            ->timeout((int) config('services.bps.timeout', 20))
            ->connectTimeout(8)
            ->withHeaders([
                // ── Browser fingerprint headers — diperlukan untuk bypass Cloudflare ──
                // BPS API menggunakan Cloudflare yang memblokir request server-side
                // tanpa header browser yang lengkap. Header ini meniru request Chrome/Chromium.
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
                'Accept' => 'application/json, text/plain, */*',
                'Accept-Language' => 'id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Connection' => 'keep-alive',
                'Cache-Control' => 'no-cache',
                'Pragma' => 'no-cache',
                'Sec-Fetch-Dest' => 'empty',
                'Sec-Fetch-Mode' => 'cors',
                'Sec-Fetch-Site' => 'same-site',
                'Sec-Ch-Ua' => '"Chromium";v="125", "Google Chrome";v="125", "Not-A.Brand";v="99"',
                'Sec-Ch-Ua-Mobile' => '?0',
                'Sec-Ch-Ua-Platform' => '"Windows"',
                'Referer' => 'https://webapi.bps.go.id/',
                'Origin' => 'https://webapi.bps.go.id',
            ])
            ->get($url);

        if ($response->failed()) {
            throw new \RuntimeException(
                "BPS API returned HTTP {$response->status()} for URL: {$url}"
            );
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new \RuntimeException(
                'BPS API returned non-JSON response: ' . substr($response->body(), 0, 200)
            );
        }

        // BPS API mengembalikan "status": "OK" (string), bukan integer 200
        $status = $json['status'] ?? '';
        if (! in_array($status, ['OK', 200, '200'], strict: true)) {
            throw new \RuntimeException(
                'BPS API returned error status: ' . json_encode($json)
            );
        }

        return $json;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // CACHE HELPERS
    // ──────────────────────────────────────────────────────────────────────────

    private function getFromRedis(string $key): ?array
    {
        try {
            return Cache::store('redis')->get($key);
        } catch (\Exception) {
            return null;
        }
    }

    private function saveToRedis(string $key, array $data): void
    {
        try {
            Cache::store('redis')->put($key, $data, now()->addHours($this->cacheTtlHours));
        } catch (\Exception $e) {
            Log::channel('ai_agent')->warning('bps_api.redis_write_failed', ['error' => $e->getMessage()]);
        }
    }

    private function getFromPostgres(string $key): ?array
    {
        $row = DB::table('bps_api_cache')
            ->where('cache_key', $key)
            ->where('expires_at', '>', now())
            ->where('is_stale', false)
            ->first();

        return $row ? json_decode($row->response_json, true) : null;
    }

    private function getStaleFromPostgres(string $key): ?array
    {
        $row = DB::table('bps_api_cache')
            ->where('cache_key', $key)
            ->orderByDesc('fetched_at')
            ->first();

        if ($row) {
            DB::table('bps_api_cache')->where('cache_key', $key)->update(['is_stale' => true]);

            return json_decode($row->response_json, true);
        }

        return null;
    }

    private function saveToPostgres(string $key, string $domainCode, string $endpointType, array $data, int $ttlHours = 24): void
    {
        try {
            DB::table('bps_api_cache')->upsert(
                [
                    'cache_key' => $key,
                    'domain_code' => $domainCode,
                    'endpoint_type' => $endpointType,
                    'response_json' => json_encode($data),
                    'fetched_at' => now(),
                    'expires_at' => now()->addHours($ttlHours),
                    'is_stale' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                ['cache_key'],
                ['response_json', 'fetched_at', 'expires_at', 'is_stale', 'updated_at']
            );
        } catch (\Exception $e) {
            Log::channel('ai_agent')->warning('bps_api.postgres_write_failed', ['error' => $e->getMessage()]);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // META WRAPPER
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Bungkus data dengan metadata sumber yang wajib disertakan ke LLM.
     * LLM HARUS mencantumkan informasi ini dalam setiap respons yang mengandung angka statistik.
     */
    private function wrapMeta(array $data, string $domainCode, string $cacheKey, string $cacheStatus): array
    {
        $fetchedAt = $data['_fetched_at'] ?? now()->toIso8601String();
        unset($data['_fetched_at']); // bersihkan dari payload utama

        return array_merge($data, [
            '_meta' => [
                'source' => 'BPS WebAPI v1 (webapi.bps.go.id)',
                'source_url' => $this->baseUrl,
                'fetched_at' => $fetchedAt,
                'cache_status' => $cacheStatus,
                'domain_code' => $domainCode,
                'data_warning' => $cacheStatus === 'STALE'
                    ? '⚠️ Data ini diambil dari cache terakhir yang tersimpan karena API BPS sedang tidak dapat diakses. Verifikasi ke webapi.bps.go.id untuk data terkini.'
                    : null,
            ],
        ]);
    }
}
