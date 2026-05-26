<?php

namespace Database\Seeders;

use App\Models\BpsVariableMap;
use Illuminate\Database\Seeder;

/**
 * BpsVariableMapSeeder
 *
 * Menyediakan data awal (pre-warm) untuk agen BPS sehingga tidak perlu
 * melakukan discovery loop pada eksekusi pertama.
 *
 * Data ini diperoleh dari hasil eksekusi agen yang telah diverifikasi manual.
 *
 * STRUKTUR DATA:
 *   - var_id    : ID variabel BPS WebAPI (dari /list model=var)
 *   - vervar_id : ID variabel vertikal (filter sub-wilayah dalam response /list model=data)
 *                 NULL = tidak perlu filter (data memang hanya untuk 1 wilayah)
 * 
 * CARA KERJA vervar_id:
 *   BPS API /list?model=data sering mengembalikan data untuk SEMUA kab/kota dalam provinsi.
 *   agen perlu tahu `vervar_id` untuk mem-filter baris yang relevan.
 *   Contoh: IPM domain 5171, respons berisi data untuk semua kota di Bali.
 *   vervar_id=264 berarti "Kota Denpasar" → agen langsung pakai ini tanpa scan.
 */
class BpsVariableMapSeeder extends Seeder
{
    public function run(): void
    {
        // ─────────────────────────────────────────────────────────────────────
        // DOMAIN 5171 — Kota Denpasar
        // vervar_id di bawah sudah diverifikasi dari output agen (bps_agent_tests.json)
        // ─────────────────────────────────────────────────────────────────────
        $denpasarVars = [
            // var_id, vervar_id, vervar_label, slug
            [237, null,  null,                         'ipm',                 null],
            [112, 1,     'Kota Denpasar',              'kemiskinan_pct',      null],
            [113, 1,     'Kota Denpasar',              'kemiskinan_jiwa',     null],
            [110, 1,     'Kota Denpasar',              'kemiskinan_kedalaman',null],
            [108, 1,     'Kota Denpasar',              'kemiskinan_keparahan',null],
            [111, 1,     'Kota Denpasar',              'garis_kemiskinan',    null],
            [77,  null,  null,                         'tpt',                 null],
            [101, 8,     'PRODUK DOMESTIK REGIONAL BRUTO', 'pdrb_laju',      null],
            [8,   null,  null,                         'pdrb_adhb',           null],
            [192, 12,    'Umum (Inflasi Tahunan)',     'inflasi_tahunan',     null],
            [238, null,  null,                         'harapan_hidup',       null],
            [238, null,  null,                         'ahh',                 null],
            [238, null,  null,                         'uhh',                 null],
        ];

        foreach ($denpasarVars as [$varId, $vervarId, $vervarLabel, $slug, $notes]) {
            BpsVariableMap::recordDiscovery(
                domainCode:   '5171',
                domainLevel:  'kota',
                indicatorSlug: $slug,
                varId:        $varId,
                notes:        $notes,
                vervarId:     $vervarId,
                vervarLabel:  $vervarLabel,
            );
        }

        // ─────────────────────────────────────────────────────────────────────
        // DOMAIN 3101, 3171 - 3175 — DKI Jakarta Administrative Regions
        // pre-loaded route mappings to domain 3100 (DKI Jakarta) with verified vervar_ids.
        // ─────────────────────────────────────────────────────────────────────
        $dkiRegions = [
            '3101' => [
                'name' => 'Kepulauan Seribu',
                'ipm_vervar' => 2,
                'poverty_vervar' => 1,
                'tpt_vervar' => 3101,
            ],
            '3171' => [
                'name' => 'Kota Jakarta Pusat',
                'ipm_vervar' => 5,
                'poverty_vervar' => 4,
                'tpt_vervar' => 3173,
            ],
            '3172' => [
                'name' => 'Kota Jakarta Utara',
                'ipm_vervar' => 7,
                'poverty_vervar' => 6,
                'tpt_vervar' => 3175,
            ],
            '3173' => [
                'name' => 'Kota Jakarta Barat',
                'ipm_vervar' => 6,
                'poverty_vervar' => 5,
                'tpt_vervar' => 3174,
            ],
            '3174' => [
                'name' => 'Kota Jakarta Selatan',
                'ipm_vervar' => 3,
                'poverty_vervar' => 2,
                'tpt_vervar' => 3171,
            ],
            '3175' => [
                'name' => 'Kota Jakarta Timur',
                'ipm_vervar' => 4,
                'poverty_vervar' => 3,
                'tpt_vervar' => 3172,
            ],
        ];

        foreach ($dkiRegions as $domain => $meta) {
            // IPM
            BpsVariableMap::recordDiscovery(
                domainCode:    $domain,
                domainLevel:   'kota',
                indicatorSlug: 'ipm',
                varId:         744,
                notes:         "ROUTED: {$meta['name']} IPM is queried from province domain 3100.",
                vervarId:      $meta['ipm_vervar'],
                vervarLabel:   $meta['name'],
                routeToDomain: '3100',
            );

            // Kemiskinan Persentase
            BpsVariableMap::recordDiscovery(
                domainCode:    $domain,
                domainLevel:   'kota',
                indicatorSlug: 'kemiskinan_pct',
                varId:         1125,
                notes:         "ROUTED: {$meta['name']} Poverty is queried from province domain 3100.",
                vervarId:      $meta['poverty_vervar'],
                vervarLabel:   $meta['name'],
                routeToDomain: '3100',
            );

            // TPT
            BpsVariableMap::recordDiscovery(
                domainCode:    $domain,
                domainLevel:   'kota',
                indicatorSlug: 'tpt',
                varId:         45,
                notes:         "ROUTED: {$meta['name']} TPT is queried from province domain 3100.",
                vervarId:      $meta['tpt_vervar'],
                vervarLabel:   $meta['name'],
                routeToDomain: '3100',
            );

            // Harapan Hidup / AHH / UHH
            foreach (['harapan_hidup', 'ahh', 'uhh'] as $slug) {
                BpsVariableMap::recordDiscovery(
                    domainCode:    $domain,
                    domainLevel:   'kota',
                    indicatorSlug: $slug,
                    varId:         900,
                    notes:         "ROUTED: {$meta['name']} Life Expectancy is queried from province domain 3100.",
                    vervarId:      $meta['poverty_vervar'],
                    vervarLabel:   $meta['name'],
                    routeToDomain: '3100',
                );
            }

            // Gini (Provincial cross-domain pivot)
            BpsVariableMap::recordDiscovery(
                domainCode:    $domain,
                domainLevel:   'kota',
                indicatorSlug: 'gini',
                varId:         41,
                notes:         "CROSS-DOMAIN: Gini Ratio is queried from province domain 3100.",
                vervarId:      null,
                vervarLabel:   null,
                routeToDomain: '3100',
            );
        }

        // ─────────────────────────────────────────────────────────────────────
        // DOMAIN 5100 — Provinsi Bali (Cross-domain pivots)
        // ─────────────────────────────────────────────────────────────────────
        BpsVariableMap::recordDiscovery(
            domainCode:    '5100',
            domainLevel:   'provinsi',
            indicatorSlug: 'gini',
            varId:         41,
            notes:         'CROSS-DOMAIN: Gini Ratio tidak tersedia di domain kota. Gunakan domain PROVINSI INDUK. vervar_id=9 = Kota Denpasar dalam distribusi Bali.',
            vervarId:      9,
            vervarLabel:   'Kota Denpasar (dalam distribusi Provinsi Bali)',
        );

        BpsVariableMap::recordDiscovery(
            domainCode:    '5100',
            domainLevel:   'provinsi',
            indicatorSlug: 'kemiskinan_pct_prov',
            varId:         125,
            notes:         'CROSS-DOMAIN: Data distribusi kemiskinan per kab/kota. vervar_id=9 = Kota Denpasar.',
            vervarId:      9,
            vervarLabel:   'Kota Denpasar (dalam distribusi Provinsi Bali)',
        );

        // ─────────────────────────────────────────────────────────────────────
        // DOMAIN GENERIK — Kota/Kabupaten level (akan dipakai sebagai fallback
        // untuk kota lain yang belum memiliki entry spesifik di tabel ini)
        // var_id berikut berlaku hampir universal di semua domain kota BPS.
        // ─────────────────────────────────────────────────────────────────────
        $genericKotaVars = [
            [237, 'ipm',                 null, null, 'kota'],
            [112, 'kemiskinan_pct',      null, null, 'kota'],
            [113, 'kemiskinan_jiwa',     null, null, 'kota'],
            [77,  'tpt',                 null, null, 'kota'],
            [101, 'pdrb_laju',           null, null, 'kota'],
            [8,   'pdrb_adhb',           null, null, 'kota'],
            [238, 'harapan_hidup',       null, null, 'kota'],
            [238, 'ahh',                 null, null, 'kota'],
            [238, 'uhh',                 null, null, 'kota'],
            // Provinsi generik
            [41,  'gini',                'CROSS-DOMAIN: Gini di domain PROVINSI INDUK (kode_kota → 2 digit + 00)', null, 'provinsi'],
        ];

        // Seed ke domain '0000' sebagai "pola generik" untuk fallback
        foreach ($genericKotaVars as [$varId, $slug, $notes, $vervarId, $level]) {
            // Hanya insert jika belum ada (tidak override existing spesifik)
            BpsVariableMap::firstOrCreate(
                ['domain_code' => '0000', 'indicator_slug' => $slug],
                [
                    'domain_level' => $level,
                    'var_id'       => $varId,
                    'vervar_id'    => $vervarId,
                    'notes'        => $notes,
                    'verified_at'  => now(),
                ]
            );
        }
    }
}
