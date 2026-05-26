<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BpsGroundTruthsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $records = [];

        // ── KABUPATEN MEMPAWAH (6104) ──────────────────────────────────────────
        // IPM Mempawah 2010-2025 (Metode Baru / Backcast SP2020)
        $ipmValues = [
            2010 => 60.17,
            2011 => 60.77,
            2012 => 61.27,
            2013 => 61.84,
            2014 => 62.40,
            2015 => 63.29,
            2016 => 64.12,
            2017 => 64.78,
            2018 => 65.17,
            2019 => 65.74,
            2020 => 67.97,
            2021 => 68.39,
            2022 => 67.91,
            2023 => 68.91,
            2024 => 69.63,
            2025 => 70.59,
        ];

        foreach ($ipmValues as $year => $value) {
            $records[] = [
                'id' => Str::uuid()->toString(),
                'domain_code' => '6104',
                'year' => $year,
                'indicator_code' => 'IPM',
                'value' => $value,
                'notes' => 'BPS Kabupaten Mempawah (Mempawah Dalam Angka)',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Komponen IPM Mempawah 2023
        $components2023 = [
            'AHH' => 71.74,
            'HLS' => 12.88,
            'RLS' => 7.20,
            'PPP' => 8.69,
        ];
        foreach ($components2023 as $code => $value) {
            $records[] = [
                'id' => Str::uuid()->toString(),
                'domain_code' => '6104',
                'year' => 2023,
                'indicator_code' => $code,
                'value' => $value,
                'notes' => 'Komponen IPM Mempawah 2023 Aktual',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Komponen IPM Mempawah 2024
        $components2024 = [
            'AHH' => 72.32,
            'HLS' => 12.42,
            'RLS' => 7.82,
            'PPP' => 10.54,
        ];
        foreach ($components2024 as $code => $value) {
            $records[] = [
                'id' => Str::uuid()->toString(),
                'domain_code' => '6104',
                'year' => 2024,
                'indicator_code' => $code,
                'value' => $value,
                'notes' => 'Komponen IPM Mempawah 2024 Aktual',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // ── KOTA DENPASAR (5171) & PROVINSI BALI (5100) GROUND TRUTHS ───────────────────────
        $denpasarBaliGroundTruths = [
            // Kota Denpasar (5171)
            [
                'domain_code' => '5171',
                'year' => 2025,
                'indicator_code' => 'AHH',
                'value' => 76.16,
                'notes' => 'Angka Harapan Hidup Kota Denpasar 2025 (Ground Truth)',
            ],
            [
                'domain_code' => '5171',
                'year' => 2024,
                'indicator_code' => 'AHH',
                'value' => 75.80,
                'notes' => 'Angka Harapan Hidup Kota Denpasar 2024 (Ground Truth)',
            ],
            [
                'domain_code' => '5171',
                'year' => 2025,
                'indicator_code' => 'IPM',
                'value' => 85.63,
                'notes' => 'IPM Kota Denpasar 2025 (Ground Truth)',
            ],
            [
                'domain_code' => '5171',
                'year' => 2024,
                'indicator_code' => 'IPM',
                'value' => 85.11,
                'notes' => 'IPM Kota Denpasar 2024 (Ground Truth)',
            ],
            // Provinsi Bali (5100)
            [
                'domain_code' => '5100',
                'year' => 2025,
                'indicator_code' => 'IPM',
                'value' => 79.37,
                'notes' => 'IPM Provinsi Bali 2025 (Ground Truth)',
            ],
        ];

        foreach ($denpasarBaliGroundTruths as $gt) {
            $records[] = [
                'id' => Str::uuid()->toString(),
                'domain_code' => $gt['domain_code'],
                'year' => $gt['year'],
                'indicator_code' => $gt['indicator_code'],
                'value' => $gt['value'],
                'notes' => $gt['notes'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Simpan semua ke database
        foreach ($records as $record) {
            DB::table('bps_ground_truths')->updateOrInsert(
                [
                    'domain_code' => $record['domain_code'],
                    'year' => $record['year'],
                    'indicator_code' => $record['indicator_code'],
                ],
                $record
            );
        }
    }
}

