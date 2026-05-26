<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BpsRegencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $regencies = [
            [
                'code' => '6104',
                'name' => 'Kabupaten Mempawah',
                'province' => 'Kalimantan Barat',
            ],
            [
                'code' => '5171',
                'name' => 'Kota Denpasar',
                'province' => 'Bali',
            ],
            [
                'code' => '6404',
                'name' => 'Kabupaten Kutai Timur',
                'province' => 'Kalimantan Timur',
            ],
            [
                'code' => '6402',
                'name' => 'Kabupaten Kutai Kartanegara',
                'province' => 'Kalimantan Timur',
            ],
            [
                'code' => '3404',
                'name' => 'Kabupaten Sleman',
                'province' => 'Daerah Istimewa Yogyakarta',
            ],
            [
                'code' => '3171',
                'name' => 'Kota Jakarta Pusat',
                'province' => 'DKI Jakarta',
            ],
            [
                'code' => '3173',
                'name' => 'Kota Jakarta Barat',
                'province' => 'DKI Jakarta',
            ],
            [
                'code' => '3174',
                'name' => 'Kota Jakarta Selatan',
                'province' => 'DKI Jakarta',
            ],
            [
                'code' => '3175',
                'name' => 'Kota Jakarta Timur',
                'province' => 'DKI Jakarta',
            ],
            [
                'code' => '3172',
                'name' => 'Kota Jakarta Utara',
                'province' => 'DKI Jakarta',
            ],
        ];

        foreach ($regencies as $regency) {
            DB::table('bps_regencies')->updateOrInsert(
                ['code' => $regency['code']],
                [
                    'id' => (string) Str::uuid(),
                    'name' => $regency['name'],
                    'province' => $regency['province'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
