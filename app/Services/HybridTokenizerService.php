<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HybridTokenizerService
{
    /**
     * Parsing kueri masukan pengguna secara hibrida untuk mendeteksi token terabstraksi.
     *
     * @param string $query Kueri asli dari obrolan
     * @return array<string, mixed> Kumpulan token terdeteksi
     */
    public function tokenize(string $query): array
    {
        $tokens = [
            'YEAR_TOKEN' => null,
            'SUB_REGION_CODE' => null,
            'PARENT_REGION_CODE' => null,
            'REGION_NAME' => null,
            'PROVINCE_NAME' => null,
        ];

        // 1. Deteksi Tahun (Regex Tokenizer)
        if (preg_match('/\b(19|20)\d{2}\b/', $query, $yearMatches)) {
            $tokens['YEAR_TOKEN'] = $yearMatches[0];
        }

        // 2. Deteksi Wilayah via Database Lookup O(1)
        // Ambil seluruh wilayah terdaftar secara lokal untuk dicocokkan dengan kueri secara agnostik
        $regencies = DB::table('bps_regencies')->get();
        $queryLower = strtolower($query);

        foreach ($regencies as $regency) {
            // Bersihkan nama wilayah (contoh: "Kabupaten Sleman" -> "sleman", "Kota Jakarta Barat" -> "jakarta barat")
            $cleanName = strtolower($regency->name);
            $cleanName = str_replace(['kabupaten ', 'kota ', 'kab. ', 'kec. '], '', $cleanName);
            $cleanName = trim($cleanName);

            if (!empty($cleanName) && str_contains($queryLower, $cleanName)) {
                $tokens['REGION_NAME'] = $regency->name;
                $tokens['SUB_REGION_CODE'] = $regency->code;
                $tokens['PROVINCE_NAME'] = $regency->province;
                
                // Rumus Agnostik BPS: 2 digit pertama kode adalah Provinsi Induk
                if (strlen($regency->code) >= 2) {
                    $tokens['PARENT_REGION_CODE'] = substr($regency->code, 0, 2) . '00';
                }
                break;
            }
        }

        // 3. Fallback: Jika tidak ada kota/kabupaten yang cocok, periksa apakah pengguna menyebut nama provinsi
        if (!$tokens['SUB_REGION_CODE']) {
            foreach ($regencies as $regency) {
                $cleanProvince = strtolower($regency->province);
                if (str_contains($queryLower, $cleanProvince)) {
                    $tokens['PROVINCE_NAME'] = $regency->province;
                    if (strlen($regency->code) >= 2) {
                        // Level provinsi di BPS diakhiri '00'
                        $tokens['PARENT_REGION_CODE'] = substr($regency->code, 0, 2) . '00';
                    }
                    break;
                }
            }
        }

        return array_filter($tokens);
    }
}
