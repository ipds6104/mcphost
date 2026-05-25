<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MethodologyRegistryService
 *
 * Mengquery tabel bps_methodology_breaks untuk mendeteksi apakah suatu
 * permintaan analisis melintasi titik diskontinuitas metodologi BPS.
 *
 * Digunakan oleh DisclaimerInjectorService untuk menentukan kapan dan
 * disclaimer apa yang harus diinjeksi ke dalam respons LLM.
 */
class MethodologyRegistryService
{
    /**
     * Daftar sektor yang dikenali sistem (lowercase, untuk matching fleksibel).
     */
    public const SECTOR_ALIASES = [
        'ipm' => 'IPM',
        'pembangunan manusia' => 'IPM',
        'human development' => 'IPM',
        'pdrb' => 'PDRB',
        'pertumbuhan ekonomi' => 'PDRB',
        'produk domestik' => 'PDRB',
        'gdp' => 'PDRB',
        'ihk' => 'IHK',
        'inflasi' => 'IHK',
        'harga konsumen' => 'IHK',
        'cpi' => 'IHK',
        'ketenagakerjaan' => 'Ketenagakerjaan',
        'tenaga kerja' => 'Ketenagakerjaan',
        'lapangan usaha' => 'Ketenagakerjaan',
        'kbli' => 'Ketenagakerjaan',
        'kemiskinan' => 'Kemiskinan',
        'garis kemiskinan' => 'Kemiskinan',
        'poverty' => 'Kemiskinan',
    ];

    /**
     * Deteksi methodology breaks yang relevan untuk kombinasi sektor + rentang tahun.
     *
     * @param  string  $sector  Nama sektor (IPM, PDRB, IHK, Ketenagakerjaan, Kemiskinan)
     * @param  int  $fromYear  Tahun awal rentang
     * @param  int  $toYear  Tahun akhir rentang
     * @return array<int, object> Array break yang relevan (kosong = aman dibandingkan)
     */
    public function detectBreaks(string $sector, int $fromYear, int $toYear): array
    {
        $canonicalSector = $this->canonicalizeSector($sector);
        if (! $canonicalSector) {
            return [];
        }

        $breaks = DB::table('bps_methodology_breaks')
            ->where('sector', $canonicalSector)
            ->whereBetween('break_year', [$fromYear + 1, $toYear])
            ->orderBy('break_year')
            ->get()
            ->toArray();

        if (! empty($breaks)) {
            Log::channel('ai_agent')->info('methodology_registry.breaks_detected', [
                'sector' => $canonicalSector,
                'from_year' => $fromYear,
                'to_year' => $toYear,
                'breaks' => array_column($breaks, 'break_year'),
            ]);
        }

        return $breaks;
    }

    /**
     * Apakah dua tahun spesifik aman untuk dibandingkan dalam satu series?
     */
    public function isSafeToCompare(string $sector, int $year1, int $year2): bool
    {
        [$from, $to] = $year1 < $year2 ? [$year1, $year2] : [$year2, $year1];

        return empty($this->detectBreaks($sector, $from, $to));
    }

    /**
     * Generate teks disclaimer siap pakai berdasarkan breaks yang ditemukan.
     *
     * @param  array<int, object>  $breaks
     * @return string Teks disclaimer dalam format Markdown
     */
    public function generateDisclaimer(string $sector, array $breaks): string
    {
        if (empty($breaks)) {
            return '';
        }

        $breakYears = implode(', ', array_column($breaks, 'break_year'));
        $brsRefs = array_filter(array_column($breaks, 'brs_reference'));
        $brsText = ! empty($brsRefs) ? "\n> 📄 **Referensi:** " . implode('; ', $brsRefs) : '';

        $descriptions = [];
        foreach ($breaks as $break) {
            $descriptions[] = "**{$break->break_year}:** {$break->description}";
        }
        $descText = "\n> \n> " . implode("\n> \n> ", $descriptions);

        return <<<MARKDOWN

> ⚠️ **Catatan Metodologi BPS — Penting untuk Interpretasi Data**
> Data **{$sector}** yang disajikan di atas mencakup rentang tahun yang melewati **titik diskontinuitas metodologi BPS** (tahun: {$breakYears}).
> Data dari periode berbeda **tidak dapat dibandingkan secara langsung (incomparable series)** tanpa penyesuaian metodologi.
>{$descText}{$brsText}
> 🔗 Verifikasi data dan metodologi: [bps.go.id](https://www.bps.go.id) | [webapi.bps.go.id](https://webapi.bps.go.id)
MARKDOWN;
    }

    /**
     * Normalisasi nama sektor menjadi nama kanonik di database.
     */
    public function canonicalizeSector(string $input): ?string
    {
        $lower = strtolower(trim($input));

        // Cek exact match atau alias
        if (isset(self::SECTOR_ALIASES[$lower])) {
            return self::SECTOR_ALIASES[$lower];
        }

        // Cek partial match (keyword ada di dalam input)
        foreach (self::SECTOR_ALIASES as $alias => $canonical) {
            if (str_contains($lower, $alias)) {
                return $canonical;
            }
        }

        return null;
    }

    /**
     * Mengecek apakah sebuah sektor disebutkan dalam teks.
     */
    public function isSectorMentioned(string $sector, string $text): bool
    {
        $canonicalized = $this->canonicalizeSector($sector);
        if (! $canonicalized) {
            return false;
        }

        // Cek semua alias untuk sektor ini
        $textLower = strtolower($text);
        foreach (self::SECTOR_ALIASES as $alias => $canonical) {
            if ($canonical === $canonicalized && str_contains($textLower, strtolower($alias))) {
                return true;
            }
        }

        // Fallback: cek nama kanonik langsung
        return str_contains($textLower, strtolower($sector));
    }

    /**
     * Ekstrak semua tahun yang disebutkan dalam teks (4 digit, rentang 1990-2030).
     *
     * @return array<int>
     */
    public function extractYearsFromText(string $text): array
    {
        preg_match_all('/\b(199[0-9]|20[0-2][0-9]|2030)\b/', $text, $matches);

        return array_unique(array_map('intval', $matches[1]));
    }
}
