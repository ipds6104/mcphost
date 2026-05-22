<?php

declare(strict_types=1);

namespace App\Services;

/**
 * MockMcpToolProvider
 *
 * Menyediakan daftar tool MCP lokal yang realistis beserta logika eksekusinya
 * tanpa memerlukan koneksi ke remote MCP server eksternal.
 *
 * Gunakan class ini saat MCP_MODE=mock di .env, atau sebagai fallback
 * saat remote server tidak tersedia. Data yang dikembalikan adalah
 * data statistik BPS Kabupaten Mempawah yang representatif.
 */
class MockMcpToolProvider
{
    /**
     * Kembalikan daftar tool yang tersedia, dalam format kompatibel
     * dengan spesifikasi MCP tools/list response.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTools(): array
    {
        return [
            [
                'name' => 'fetch_regional_report',
                'description' => 'Mengambil laporan kinerja statistik sektoral untuk suatu kabupaten/kota berdasarkan kode BPS.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'regency_code' => ['type' => 'string', 'description' => 'Kode BPS kabupaten (contoh: 6104 untuk Mempawah)'],
                        'year' => ['type' => 'integer', 'description' => 'Tahun laporan (contoh: 2025)'],
                    ],
                    'required' => ['regency_code'],
                ],
            ],
            [
                'name' => 'get_bps_indicator',
                'description' => 'Mengambil nilai tren indikator statistik BPS tertentu untuk suatu wilayah.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'indicator_id' => ['type' => 'string', 'description' => 'ID indikator (contoh: IPKP_6104, EPSS_6104)'],
                        'years' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Rentang tahun yang diminta'],
                    ],
                    'required' => ['indicator_id'],
                ],
            ],
            [
                'name' => 'compare_regencies',
                'description' => 'Membandingkan kinerja statistik sektoral antara dua atau lebih kabupaten/kota.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'regency_codes' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Daftar kode BPS kabupaten yang ingin dibandingkan'],
                        'metric' => ['type' => 'string', 'description' => 'Metrik perbandingan: IPKP, EPSS, atau IKP'],
                    ],
                    'required' => ['regency_codes'],
                ],
            ],
            [
                'name' => 'search_statistics',
                'description' => 'Mencari data statistik BPS berdasarkan kata kunci topik atau nama indikator.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Kata kunci pencarian statistik'],
                        'domain' => ['type' => 'string', 'description' => 'Domain statistik: demografi, ekonomi, sosial, infrastruktur'],
                    ],
                    'required' => ['query'],
                ],
            ],
        ];
    }

    /**
     * Eksekusi tool berdasarkan nama dan argumen yang diberikan oleh LLM.
     * Mengembalikan data mock realistis yang disesuaikan dengan argumen.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function callTool(string $toolName, array $arguments = []): array
    {
        return match ($toolName) {
            'fetch_regional_report' => $this->fetchRegionalReport($arguments),
            'get_bps_indicator' => $this->getBpsIndicator($arguments),
            'compare_regencies' => $this->compareRegencies($arguments),
            'search_statistics' => $this->searchStatistics($arguments),
            default => ['error' => "Tool '{$toolName}' tidak ditemukan dalam katalog MCP lokal."],
        };
    }

    /** @param array<string, mixed> $args */
    private function fetchRegionalReport(array $args): array
    {
        $code = $args['regency_code'] ?? '6104';
        $year = $args['year'] ?? 2025;

        $regencies = [
            '6104' => ['name' => 'Mempawah', 'province' => 'Kalimantan Barat', 'ipkp' => 78.5, 'epss' => 85.0, 'ikp' => 72.3],
            '6101' => ['name' => 'Sambas', 'province' => 'Kalimantan Barat', 'ipkp' => 74.2, 'epss' => 79.5, 'ikp' => 68.1],
            '6103' => ['name' => 'Pontianak', 'province' => 'Kalimantan Barat', 'ipkp' => 81.3, 'epss' => 88.2, 'ikp' => 77.6],
            '3404' => ['name' => 'Sleman', 'province' => 'DI Yogyakarta', 'ipkp' => 83.2, 'epss' => 89.5, 'ikp' => 81.1],
        ];

        $data = $regencies[$code] ?? $regencies['6104'];

        return [
            'status' => 'success',
            'source' => 'BPS Mock Data v1.0',
            'regency_code' => $code,
            'regency_name' => $data['name'],
            'province' => $data['province'],
            'report_year' => $year,
            'performance_index' => $data['ipkp'],
            'epss_score' => $data['epss'],
            'ikp_score' => $data['ikp'],
            'metadata' => [
                'bps_code' => $code,
                'data_updated_at' => now()->format('Y-m-d'),
            ],
        ];
    }

    /** @param array<string, mixed> $args */
    private function getBpsIndicator(array $args): array
    {
        $indicatorId = $args['indicator_id'] ?? 'IPKP_6104';
        $years = $args['years'] ?? [2023, 2024, 2025];

        // Ekstrak tipe indikator dan kode wilayah dari ID
        $parts = explode('_', $indicatorId);
        $type = $parts[0] ?? 'IPKP';
        $code = $parts[1] ?? '6104';

        $baseValues = match ("{$type}_{$code}") {
            'EPSS_3404' => [83.0, 86.2, 89.5],
            'IKP_3404' => [77.5, 79.1, 81.1],
            'IPKP_3404' => [79.2, 81.5, 83.2],
            'EPSS_6104' => [79.0, 82.1, 85.0],
            'IKP_6104' => [68.5, 70.4, 72.3],
            default => match ($type) {
                'EPSS' => [79.0, 82.1, 85.0],
                'IKP' => [68.5, 70.4, 72.3],
                default => [74.2, 76.8, 78.5], // IPKP
            },
        };

        $values = [];
        foreach (array_values($years) as $i => $year) {
            $values[] = [
                'year' => $year,
                'score' => $baseValues[$i] ?? round($baseValues[2] + ($i - 2) * 1.8, 1),
            ];
        }

        return [
            'status' => 'success',
            'source' => 'BPS Mock Data v1.0',
            'indicator_id' => $indicatorId,
            'indicator' => "{$type} (Indeks Kinerja Pembangunan Statistik Sektoral)",
            'unit' => 'Indeks (0-100)',
            'values' => $values,
            'trend' => 'meningkat',
        ];
    }

    /** @param array<string, mixed> $args */
    private function compareRegencies(array $args): array
    {
        $codes = $args['regency_codes'] ?? ['6104', '6101'];
        $metric = $args['metric'] ?? 'IPKP';

        $data = [
            '6104' => ['name' => 'Mempawah',  'IPKP' => 78.5, 'EPSS' => 85.0, 'IKP' => 72.3],
            '6101' => ['name' => 'Sambas',    'IPKP' => 74.2, 'EPSS' => 79.5, 'IKP' => 68.1],
            '6103' => ['name' => 'Pontianak', 'IPKP' => 81.3, 'EPSS' => 88.2, 'IKP' => 77.6],
            '6105' => ['name' => 'Sanggau',   'IPKP' => 71.8, 'EPSS' => 76.3, 'IKP' => 65.4],
            '3404' => ['name' => 'Sleman',    'IPKP' => 83.2, 'EPSS' => 89.5, 'IKP' => 81.1],
        ];

        $comparison = [];
        foreach ($codes as $code) {
            $reg = $data[$code] ?? ['name' => "Kab. {$code}", 'IPKP' => 70.0, 'EPSS' => 75.0, 'IKP' => 65.0];
            $comparison[] = [
                'regency_code' => $code,
                'regency_name' => $reg['name'],
                'metric' => $metric,
                'value' => $reg[$metric] ?? $reg['IPKP'],
                'year' => 2025,
            ];
        }

        // Urutkan dari tertinggi ke terendah
        usort($comparison, fn ($a, $b) => $b['value'] <=> $a['value']);

        return [
            'status' => 'success',
            'source' => 'BPS Mock Data v1.0',
            'metric' => $metric,
            'comparison' => $comparison,
            'winner' => $comparison[0]['regency_name'] ?? '-',
        ];
    }

    /** @param array<string, mixed> $args */
    private function searchStatistics(array $args): array
    {
        $query = strtolower($args['query'] ?? '');

        $catalog = [
            ['id' => 'IPKP', 'name' => 'Indeks Pembangunan Keluarga', 'domain' => 'sosial', 'unit' => 'Indeks (0-100)', 'latest_value' => 78.5],
            ['id' => 'EPSS', 'name' => 'Evaluasi Penyelenggaraan Statistik Sektoral', 'domain' => 'statistik', 'unit' => 'Skor (0-100)', 'latest_value' => 85.0],
            ['id' => 'IKP',  'name' => 'Indeks Kinerja Pembangunan', 'domain' => 'ekonomi', 'unit' => 'Indeks (0-100)', 'latest_value' => 72.3],
            ['id' => 'IPM',  'name' => 'Indeks Pembangunan Manusia', 'domain' => 'sosial', 'unit' => 'Indeks (0-100)', 'latest_value' => 68.7],
            ['id' => 'PDRB', 'name' => 'Produk Domestik Regional Bruto', 'domain' => 'ekonomi', 'unit' => 'Miliar Rupiah', 'latest_value' => 12453.8],
            ['id' => 'IPKP_3404', 'name' => 'IPKP Kabupaten Sleman', 'domain' => 'sosial', 'unit' => 'Indeks (0-100)', 'latest_value' => 83.2],
            ['id' => 'EPSS_3404', 'name' => 'EPSS Kabupaten Sleman', 'domain' => 'statistik', 'unit' => 'Skor (0-100)', 'latest_value' => 89.5],
            ['id' => 'IKP_3404', 'name' => 'IKP Kabupaten Sleman', 'domain' => 'ekonomi', 'unit' => 'Indeks (0-100)', 'latest_value' => 81.1],
            ['id' => 'IPKP_DIY', 'name' => 'IPKP Provinsi DI Yogyakarta', 'domain' => 'sosial', 'unit' => 'Indeks (0-100)', 'latest_value' => 85.4],
        ];

        $results = array_filter($catalog, function ($item) use ($query) {
            return str_contains(strtolower($item['name']), $query)
                || str_contains(strtolower($item['id']), $query)
                || str_contains(strtolower($item['domain']), $query);
        });

        return [
            'status' => 'success',
            'source' => 'BPS Mock Data v1.0',
            'query' => $args['query'] ?? '',
            'total_found' => count($results),
            'results' => array_values($results),
        ];
    }
}
