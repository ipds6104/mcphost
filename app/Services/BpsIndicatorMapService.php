<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BpsVariableMap;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * BpsIndicatorMapService
 *
 * Menyediakan peta var_id → metadata indikator BPS yang telah terverifikasi.
 * Digunakan untuk memperkaya instruksi agen sebelum eksekusi dimulai,
 * sehingga agen tidak perlu melakukan pencarian (search_statistics) yang
 * memakan waktu ~45 detik per panggilan.
 *
 * Sistem pembelajaran mandiri: setiap kali agen berhasil mengidentifikasi
 * var_id baru, data tersebut disimpan via BpsVariableMap::recordDiscovery()
 * dan akan tersedia untuk eksekusi berikutnya.
 */
class BpsIndicatorMapService
{
    private const CACHE_TTL = 86400; // 24 jam

    /**
     * Ambil seluruh peta var_id untuk domain tertentu.
     * Hasil dicache 24 jam untuk meminimalkan query DB.
     *
     * @param  string $domainCode  Kode domain BPS (e.g. '5171')
     * @return array<string, array{var_id: int, vervar_id: int|null, vervar_label: string|null, domain_level: string, notes: string|null, verified_at: string|null}>
     */
    public function getMapForDomain(string $domainCode): array
    {
        $cacheKey = "bps_var_map_{$domainCode}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($domainCode) {
            // Cari entries untuk domain spesifik ini
            $exactRows = BpsVariableMap::where('domain_code', $domainCode)->get();

            // Deteksi level domain dari kode
            $level = $this->inferDomainLevel($domainCode);

            // Fallback 1: ambil entries dari domain level yang sama (antar kota share var_id)
            $levelRows = BpsVariableMap::where('domain_level', $level)
                ->whereNotNull('verified_at')
                ->get();

            // Fallback 2: ambil generic entries dari domain '0000'
            $genericRows = BpsVariableMap::where('domain_code', '0000')->get();

            // Merge: generic → level fallback → exact match (prioritas naik)
            $merged = [];
            foreach ($genericRows as $row) {
                $merged[$row->indicator_slug] = $this->rowToArray($row, 'generic_fallback');
            }
            foreach ($levelRows as $row) {
                $merged[$row->indicator_slug] = $this->rowToArray($row, 'level_fallback');
            }
            foreach ($exactRows as $row) {
                // Exact match override semua fallback
                $merged[$row->indicator_slug] = $this->rowToArray($row, 'exact_match');
            }

            return $merged;
        });
    }

    /**
     * Bangun blok instruksi untuk injeksi ke prompt agen.
     * Dipanggil dari ProcessAiAgentQuery dan TestBpsAgent sebelum membuat GovtAnalyticsAgent.
     *
     * @param  string $domainCode      Domain wilayah yang diminta (e.g. '5171')
     * @param  string $regionName      Nama wilayah untuk konteks (e.g. 'Denpasar')
     * @return string                  Addendum instruksi yang siap disuntikkan
     */
    public function buildInjectionAddendum(string $domainCode, string $regionName): string
    {
        $discoveryAddendum = "\n\n## 🚨 ATURAN DISCOVERY WAJIB UNTUK WILAYAH BARU (IKUTI DENGAN KETAT):\n" .
            "Jika Anda tidak menemukan indikator pembangunan yang terverifikasi untuk wilayah target, Anda harus melakukan pencarian (discovery) variabel. IKUTI ATURAN BERIKUT:\n" .
            "1. **Pencarian Paralel (`Promise.all`)**: SELALU jalankan kueri keyword pencarian (seperti \"kemiskinan\" dan \"miskin\") secara paralel menggunakan `Promise.all` dalam satu langkah `execute_js`. JANGAN pernah menunggu hasil satu keyword secara berurutan (sekuensial).\n" .
            "2. **Anti-Bruteforce**: DILARANG keras melakukan paginasi variabel (`page: 1` sampai selesai) untuk mencari indikator. Hanya gunakan kueri dengan parameter `keyword` (contoh: `{ model: \"var\", domain: \"{$domainCode}\", keyword: \"kemiskinan\" }`). DILARANG menggunakan parameter `key` karena itu adalah API Key internal BPS.\n" .
            "3. **Fallback Cepat Provinsi**: Jika seluruh kueri keyword paralel mengembalikan kosong di domain lokal, LANGSUNG ulangi pencarian paralel yang sama di domain provinsi induk (kode domain 2 digit awal + '00').\n" .
            "4. **Batas Maksimum 2 Round Trips**: Proses discovery harus dibatasi maksimal 2 round trips (Round 1: lokal paralel, Round 2: provinsi paralel). Jika data tetap kosong, laporkan bahwa data tidak tersedia. JANGAN berspekulasi mencari keyword lain secara serial atau terus melakukan query.\n" .
            "\n💡 Contoh Kode Pencarian Paralel (COPY-PASTE INI LANGSUNG):\n" .
            "```javascript\n" .
            "const [resKemiskinan, resMiskin] = await Promise.all([\n" .
            "  bps.bpsFetch(\"/list\", { model: \"var\", domain: \"{$domainCode}\", keyword: \"kemiskinan\" }),\n" .
            "  bps.bpsFetch(\"/list\", { model: \"var\", domain: \"{$domainCode}\", keyword: \"miskin\" })\n" .
            "]);\n" .
            "return { resKemiskinan, resMiskin };\n" .
            "```\n";

        $map = $this->getMapForDomain($domainCode);

        if (empty($map)) {
            return $discoveryAddendum;
        }

        $lines = [];
        $crossDomainNotes = [];

        foreach ($map as $slug => $info) {
            $vervarNote = $info['vervar_id']
                ? " (filter vervar_id=`{$info['vervar_id']}`"
                  . ($info['vervar_label'] ? " → \"{$info['vervar_label']}\"" : '')
                  . ')'
                : '';
            $extraNote = $info['notes'] ? " ⚠️ {$info['notes']}" : '';
            $lines[] = "  - **{$slug}**: var_id=`{$info['var_id']}`{$vervarNote}{$extraNote}";

            if ($info['notes'] && str_contains(strtolower($info['notes']), 'cross-domain')) {
                $crossDomainNotes[] = $info['notes'];
            }
        }

        $thFormula = "**RUMUS th_id:** th_id = tahun - 1900 (2024=124, 2025=125, 2023=123)";

        $addendum  = "\n\n## 🚀 COGNITIVE INJECTION — IKUTI INSTRUKSI INI DENGAN KETAT\n";
        $addendum .= "\n### 📝 KEWAJIBAN ANALISIS (HARUS DICANTUMKAN):\n";
        $addendum .= "1. **WAJIB** menyertakan perbandingan/benchmark dengan Provinsi Induk untuk indikator strategis terbaru (terutama **IPM Provinsi Bali 2025** senilai **79.37 / 79,37** jika menganalisis Denpasar) agar laporan memiliki kedalaman analitis.\n";
        $addendum .= "\n### ⛔ LARANGAN MUTLAK (TIDAK BOLEH DILANGGAR):\n";
        $addendum .= "1. **DILARANG KERAS** memanggil tool `bps_query`. Tool ini melakukan discovery serial yang lambat.\n";
        $addendum .= "2. **DILARANG KERAS** memanggil `search_statistics` atau `/list model=var` untuk indikator yang sudah ada di daftar bawah.\n";
        $addendum .= "3. **DILARANG** membuat 2+ panggilan `execute_js` terpisah jika semua var_id sudah diketahui. Gunakan **SATU** panggilan `execute_js` dengan `Promise.all()`.\n";
        $addendum .= "4. **LARANGAN MUTLAK**: Jangan membagi fetch per-indikator menjadi banyak panggilan. SATU `Promise.all()` = SEMUA indikator = SATU panggilan saja.\n";
        $addendum .= "\n### ✅ YANG HARUS DILAKUKAN (SATU LANGKAH SAJA):\n";
        $addendum .= "- Panggil `execute_js` **SEKALI** dengan kode di bawah ini (atau versi yang sudah disesuaikan).\n";
        $addendum .= "- {$thFormula}\n";

        $addendum .= "\n### 📦 Peta var_id Terverifikasi untuk Domain {$domainCode} ({$regionName}):\n";
        $addendum .= implode("\n", $lines);

        if (!empty($crossDomainNotes)) {
            $addendum .= "\n\n### ⚠️ Catatan Cross-Domain:\n";
            foreach ($crossDomainNotes as $note) {
                $addendum .= "- {$note}\n";
            }
        }

        // Buat contoh kode siap-pakai berdasarkan var_id dan vervar_id yang tersedia
        $example = $this->buildParallelExample($domainCode, $map);
        if ($example) {
            $addendum .= "\n\n### 💡 Contoh Kode Siap Pakai (COPY-PASTE INI LANGSUNG):\n```javascript\n{$example}\n```";
        }

        $addendum .= $discoveryAddendum;

        return $addendum;
    }

    /**
     * Invalidate cache domain tertentu (dipanggil setelah recordDiscovery).
     */
    public function invalidateCache(string $domainCode): void
    {
        Cache::forget("bps_var_map_{$domainCode}");
        Log::channel('ai_agent')->debug('bps_var_map.cache_invalidated', ['domain' => $domainCode]);
    }

    /**
     * Inferensi level domain dari kode wilayah.
     * Mengikuti konvensi kode BPS Indonesia.
     */
    private function inferDomainLevel(string $domainCode): string
    {
        if ($domainCode === '0000') {
            return 'nasional';
        }
        // Kode 4 digit yang berakhir '00' adalah provinsi (e.g. 5100, 3200)
        if (strlen($domainCode) === 4 && str_ends_with($domainCode, '00')) {
            return 'provinsi';
        }
        // Kode 4 digit lainnya adalah kota/kabupaten
        if (strlen($domainCode) === 4) {
            return 'kota';
        }
        return 'kota'; // default
    }

    private function rowToArray(BpsVariableMap $row, string $source): array
    {
        return [
            'var_id'          => $row->var_id,
            'vervar_id'       => $row->vervar_id,
            'vervar_label'    => $row->vervar_label,
            'route_to_domain' => $row->route_to_domain,
            'domain_level'    => $row->domain_level,
            'notes'           => $row->notes,
            'verified_at'     => $row->verified_at?->toDateTimeString(),
            'source'          => $source,
        ];
    }

    /**
     * Bangun snippet Promise.all siap-pakai dari peta var_id, termasuk filter vervar_id.
     */
    private function buildParallelExample(string $domainCode, array $map): ?string
    {
        $mainFetches  = [];
        $varNames     = [];

        foreach ($map as $slug => $info) {
            if (!empty($info['route_to_domain'])) {
                // Pre-loaded domain routing redirect
                $mainFetches[] = "  bps.bpsFetch(\"/list\", { model: \"data\", domain: \"{$info['route_to_domain']}\", var: {$info['var_id']}, th: \"124;125\" }), // {$slug}";
            } else if ($info['notes'] && str_contains(strtolower($info['notes']), 'cross')) {
                // Cross-domain fallback
                $provinceCode = substr($domainCode, 0, 2) . '00';
                $mainFetches[] = "  bps.bpsFetch(\"/list\", { model: \"data\", domain: \"{$provinceCode}\", var: {$info['var_id']}, th: \"124;125\" }), // {$slug}";
            } else {
                $mainFetches[] = "  bps.bpsFetch(\"/list\", { model: \"data\", domain: \"{$domainCode}\", var: {$info['var_id']}, th: \"124;125\" }), // {$slug}";
            }
            $varNames[] = $slug;
        }

        if (empty($mainFetches)) {
            return null;
        }

        // Tambahkan catatan vervar untuk filtering
        $vervarNotes = [];
        foreach ($map as $slug => $info) {
            if ($info['vervar_id']) {
                $vervarNotes[] = "// Filter {$slug}: results.{$slug}.datacontent via vervar_id={$info['vervar_id']}";
            }
        }

        $code  = "const [" . implode(', ', $varNames) . "] = await Promise.all([\n";
        $code .= implode("\n", $mainFetches) . "\n]);\n";

        if (!empty($vervarNotes)) {
            $code .= "\n// ── vervar filtering hints ──\n";
            $code .= implode("\n", $vervarNotes) . "\n";
        }

        $code .= "\nreturn { " . implode(', ', $varNames) . " };";

        return $code;
    }
}
