<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ToolExecutionHeuristic;
use Illuminate\Database\Seeder;

class ToolHeuristicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pattern = [
            'REGION_NAME' => 'REGION_NAME',
        ];

        ToolExecutionHeuristic::updateOrCreate(
            [
                'tool_name' => 'get_bps_indicator',
                'error_signature' => 'data_not_found',
                'parameter_pattern_hash' => ToolExecutionHeuristic::generatePatternHash($pattern),
            ],
            [
                'parameter_pattern' => $pattern,
                'rewrite_instruction' => [
                    'instruction' => 'Jika pengambilan indikator pembangunan untuk wilayah REGION_NAME tidak ditemukan atau kosong, pivot ke parent provinsi menggunakan kode wilayah induk PARENT_REGION_CODE (yaitu regional parent BPS domain) untuk data pencarian yang lebih menyeluruh.',
                ],
                'is_active' => true,
                'is_system' => true,
                'success_count' => 1,
                'failure_count' => 0,
                'validation_count' => 1,
            ]
        );

        // Heuristik Sistem 2: Aturan pencarian kata kunci paralel
        ToolExecutionHeuristic::updateOrCreate(
            [
                'tool_name' => 'search_statistics',
                'error_signature' => 'data_not_found',
                'parameter_pattern_hash' => ToolExecutionHeuristic::generatePatternHash($pattern),
            ],
            [
                'parameter_pattern' => $pattern,
                'rewrite_instruction' => [
                    'instruction' => 'ATURAN DISCOVERY WAJIB: Jika melakukan pencarian variabel untuk REGION_NAME, panggil kueri keyword "kemiskinan" dan "miskin" secara PARALEL dengan Promise.all. Jangan paginasi page-by-page. Jika kosong, langsung ulangi kueri paralel tersebut pada domain provinsi induk PARENT_REGION_CODE.',
                ],
                'is_active' => true,
                'is_system' => true,
                'success_count' => 1,
                'failure_count' => 0,
                'validation_count' => 1,
            ]
        );
    }
}
