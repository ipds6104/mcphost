<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FactGraderService
 *
 * Validator output LLM berlapis tiga:
 *   1. Postgres Reference Shield — koreksi nama wilayah berdasarkan kode BPS
 *   2. Numeric Grounding — bandingkan angka di teks LLM vs nilai resmi dari tool result
 *   3. Source Citation Enforcer — delegasi ke DisclaimerInjectorService
 */
class FactGraderService
{
    /**
     * Toleransi dinamis per indikator sebelum override deterministik dilakukan.
     */
    protected static function getToleranceForIndicator(string $indicator): float
    {
        $indicator = strtoupper(trim($indicator));
        if (str_contains($indicator, 'GINI')) {
            return 0.02; // Gini ratio skala 0.0 - 1.0 sangat ketat
        }
        if (
            str_contains($indicator, 'IPM') ||
            str_contains($indicator, 'TPT') ||
            str_contains($indicator, 'KEMISKINAN') ||
            str_contains($indicator, 'MISKIN') ||
            str_contains($indicator, 'AHH') ||
            str_contains($indicator, 'RLS') ||
            str_contains($indicator, 'HLS')
        ) {
            return 0.3; // Metrik dasar skala 0 - 100
        }
        if (
            str_contains($indicator, 'PPP') ||
            str_contains($indicator, 'PDRB') ||
            str_contains($indicator, 'RATA-RATA PENGELUARAN')
        ) {
            return 100.0; // Metrik nominal besar rupiah
        }
        return 0.5; // Default fallback
    }

    /**
     * Deteksi apakah perubahan YoY di data API BPS merupakan outlier statistik yang tidak masuk akal.
     */
    public static function isStatisticalOutlier(string $indicator, float $v1, float $v2): bool
    {
        $indicator = strtoupper(trim($indicator));
        $delta = abs($v2 - $v1);

        if (str_contains($indicator, 'GINI')) {
            return $delta > 0.08; // Perubahan Gini > 0.08 setahun adalah pencilan
        }
        if (str_contains($indicator, 'IPM')) {
            return $delta > 3.0;  // Perubahan IPM > 3.0 setahun adalah pencilan
        }
        if (str_contains($indicator, 'TPT') || str_contains($indicator, 'KEMISKINAN') || str_contains($indicator, 'MISKIN')) {
            return $delta > 7.0;  // Perubahan Kemiskinan/TPT > 7% setahun adalah pencilan
        }
        if (str_contains($indicator, 'AHH')) {
            return $delta > 2.5;  // Perubahan AHH > 2.5 tahun setahun adalah pencilan
        }
        return false;
    }

    /**
     * Kamus sinonim nama indikator untuk pencocokan kontekstual.
     */
    protected static function getIndicatorSynonyms(string $code): array
    {
        $code = strtoupper(trim($code));
        $map = [
            'IPM' => ['IPM', 'Indeks Pembangunan Manusia'],
            'AHH' => ['AHH', 'Angka Harapan Hidup', 'Harapan Hidup'],
            'RLS' => ['RLS', 'Rata-Rata Lama Sekolah', 'Rata Lama Sekolah'],
            'HLS' => ['HLS', 'Harapan Lama Sekolah'],
            'PPP' => ['PPP', 'Pengeluaran Per Kapita', 'Pengeluaran Riil Per Kapita'],
            'TPT' => ['TPT', 'Tingkat Pengangguran Terbuka', 'Pengangguran'],
            'KEMISKINAN' => ['Kemiskinan', 'Penduduk Miskin', 'Persentase Penduduk Miskin'],
            'GINI' => ['Gini', 'Gini Ratio', 'Rasio Gini'],
        ];

        return $map[$code] ?? [$code];
    }

    /**
     * Deteksi kode indikator standar dari nama/judul variabel dynamic table.
     */
    protected static function detectIndicatorCode(string $title): ?string
    {
        $titleLower = strtolower($title);
        if (str_contains($titleLower, 'pembangunan manusia') || str_contains($titleLower, 'ipm')) return 'IPM';
        if (str_contains($titleLower, 'harapan hidup') || str_contains($titleLower, 'ahh')) return 'AHH';
        if (str_contains($titleLower, 'lama sekolah') && str_contains($titleLower, 'rata')) return 'RLS';
        if (str_contains($titleLower, 'lama sekolah') && str_contains($titleLower, 'harapan')) return 'HLS';
        if (str_contains($titleLower, 'pengeluaran per kapita') || str_contains($titleLower, 'ppp')) return 'PPP';
        if (str_contains($titleLower, 'pengangguran') || str_contains($titleLower, 'tpt')) return 'TPT';
        if (str_contains($titleLower, 'miskin') || str_contains($titleLower, 'kemiskinan')) return 'KEMISKINAN';
        if (str_contains($titleLower, 'gini') || str_contains($titleLower, 'rasio gini')) return 'GINI';
        return null;
    }

    /**
     * Validasi dan selaraskan seluruh draf respons LLM terhadap fakta nyata.
     *
     * @param  string  $content  Draf respons mentah dari LLM
     * @param  array  $steps  Tool steps dari job (berisi actual BPS API results)
     */
    public static function verifyAndCorrect(string $content, array $steps): string
    {
        // ── LAPISAN 1: Postgres Reference Shield ───────────────────────────────
        $content = self::enforcePostgresShield($content);

        // ── LAPISAN 2: Numeric Grounding & YoY Checks ──────────────────────────
        $content = self::enforceNumericGrounding($content, $steps);

        // ── LAPISAN 2.5: Output Shield Interceptor (Short-Circuit Hallucination) ─────────────────────
        $content = self::enforceOutputShield($content, $steps);

        return $content;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // LAPISAN 1: Postgres Reference Shield
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Memindai teks respons dan mengoreksi nama wilayah berdasarkan Kode BPS Resmi.
     */
    protected static function enforcePostgresShield(string $content): string
    {
        if (! preg_match_all('/\b\d{4}\b/', $content, $matches)) {
            return $content;
        }

        $codes = array_unique($matches[0]);

        foreach ($codes as $code) {
            $officialRegency = DB::table('bps_regencies')
                ->where('code', $code)
                ->first();

            if (! $officialRegency) {
                continue;
            }

            $officialName = $officialRegency->name;

            $patterns = [
                '/(?:Kabupaten|Kota|Kab\.|Kec\.)?\\s*[a-zA-Z\\s]+(?=\\s*\\(Kode BPS:\\s*' . $code . '\\))/i',
                '/(?:Kabupaten|Kota|Kab\.|Kec\.)?\\s*[a-zA-Z\\s]+(?=\\s*\\([^\\)]*BPS:\\s*' . $code . '\\))/i',
                '/(?:Kabupaten|Kota|Kab\\.)?\\s*[a-zA-Z\\s]+(?=\\s*\\(' . $code . '\\))/i',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content, $match)) {
                    $detectedName = trim($match[0]);

                    if (
                        ! empty($detectedName)
                        && strtolower($detectedName) !== strtolower($officialName)
                        && strtolower($detectedName) !== 'kode'
                        && strtolower($detectedName) !== 'bps'
                    ) {
                        Log::channel('ai_agent')->warning('fact_grader.postgres_shield.override', [
                            'code' => $code,
                            'detected_name' => $detectedName,
                            'corrected_to' => $officialName,
                        ]);

                        $content = str_replace(
                            $detectedName . ' (Kode BPS: ' . $code,
                            $officialName . ' (Kode BPS: ' . $code,
                            $content
                        );
                        $content = str_replace(
                            $detectedName . ' (',
                            $officialName . ' (',
                            $content
                        );
                    }
                }
            }
        }

        return $content;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // LAPISAN 2: Agnostic Structured Fact Grounding
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Mengekstrak data numerik terstruktur yang didapatkan asisten dari tool steps.
     *
     * @return array array fakta terstruktur ['indicator', 'synonyms', 'year', 'value', 'regency_code']
     */
    public static function extractStructuredFacts(array $steps): array
    {
        $facts = [];

        foreach ($steps as $step) {
            $tool = $step['tool'] ?? '';
            $args = $step['arguments'] ?? [];
            $result = $step['result'] ?? [];

            $regencyCode = (string)($args['domain_code'] ?? $args['regency_code'] ?? $args['domain'] ?? '');

            // Kasus A: Laporan regional atau Indikator spesifik dengan tabel terstruktur
            if (isset($result['table_id']) && isset($result['data']) && is_array($result['data'])) {
                $indicatorCode = strtoupper(trim((string)$result['table_id']));
                $synonyms = self::getIndicatorSynonyms($indicatorCode);

                foreach ($result['data'] as $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    $year = null;
                    if (isset($row['year'])) {
                        $year = (int)$row['year'];
                    } elseif (isset($row['tahun'])) {
                        $year = (int)$row['tahun'];
                    }

                    $value = null;
                    if (isset($row['value']) && is_numeric($row['value'])) {
                        $value = (float)$row['value'];
                    } elseif (isset($row['nilai']) && is_numeric($row['nilai'])) {
                        $value = (float)$row['nilai'];
                    }

                    if ($year && $value !== null) {
                        $facts[] = [
                            'indicator' => $indicatorCode,
                            'synonyms' => $synonyms,
                            'year' => $year,
                            'value' => $value,
                            'regency_code' => $regencyCode ?: ($result['domain_code'] ?? '')
                        ];
                    }
                }
            }

            // Kasus B: Data dinamis acak/nested dari BPS API (execute_js / bps_query)
            self::extractFactsFromGenericData($result, $facts, $regencyCode ?: ($result['domain_code'] ?? ''));
        }

        return $facts;
    }

    /**
     * Rekursif scan untuk mengekstrak data time-series dari response dynamic BPS API.
     */
    protected static function extractFactsFromGenericData(mixed $data, array &$facts, string $regencyCode): void
    {
        if (!is_array($data)) {
            return;
        }

        if (isset($data['datacontent']) && is_array($data['datacontent'])) {
            $variables = [];
            if (isset($data['var']) && is_array($data['var'])) {
                foreach ($data['var'] as $v) {
                    if (isset($v['var_id'])) {
                        $variables[$v['var_id']] = $v;
                    }
                }
            }

            foreach ($data['datacontent'] as $key => $val) {
                // Key format: "varId_turvarId_tahunId_turtahunId_regionCode" atau format time-series
                if (is_numeric($val)) {
                    $parts = explode('_', (string)$key);
                    if (count($parts) >= 3) {
                        $varId = $parts[0];
                        $varMeta = $variables[$varId] ?? null;
                        $varTitle = $varMeta['title'] ?? 'Indikator';
                        $indicatorCode = self::detectIndicatorCode($varTitle);

                        $year = null;
                        foreach ($parts as $p) {
                            if (strlen($p) === 4 && ($p[0] === '2' || $p[0] === '1')) {
                                $year = (int)$p;
                            }
                        }

                        if ($year && $indicatorCode) {
                            $facts[] = [
                                'indicator' => $indicatorCode,
                                'synonyms' => self::getIndicatorSynonyms($indicatorCode),
                                'year' => $year,
                                'value' => (float)$val,
                                'regency_code' => $regencyCode
                            ];
                        }
                    }
                }
            }
        }

        foreach ($data as $key => $val) {
            if (is_array($val) && $key !== 'var' && $key !== 'period' && $key !== 'vervar') {
                self::extractFactsFromGenericData($val, $facts, $regencyCode);
            }
        }
    }

    /**
     * Bandingkan angka desimal dalam teks LLM terhadap nilai resmi yang didapat dari BPS API.
     */
    protected static function enforceNumericGrounding(string $content, array $steps): string
    {
        if (empty($steps)) {
            return $content;
        }

        $facts = self::extractStructuredFacts($steps);

        if (empty($facts)) {
            return $content;
        }

        foreach ($facts as $fact) {
            $indicator = $fact['indicator'];
            $year = $fact['year'];
            $officialValue = $fact['value'];
            $regencyCode = $fact['regency_code'];

            $tolerance = self::getToleranceForIndicator($indicator);

            $regencyName = '';
            if (!empty($regencyCode)) {
                $regency = DB::table('bps_regencies')->where('code', $regencyCode)->first();
                if ($regency) {
                    $regencyName = str_replace(['Kabupaten ', 'Kota '], '', $regency->name);
                }
            }

            foreach ($fact['synonyms'] as $synonym) {
                $escapedSynonym = preg_quote($synonym, '/');
                $escapedYear = preg_quote((string)$year, '/');

                $patterns = [];
                if (!empty($regencyName)) {
                    $escapedRegency = preg_quote($regencyName, '/');
                    $patterns[] = '/(' . $escapedSynonym . '.*?'.$escapedRegency.'.*?'.$escapedYear.'.*?)([0-9]+[.,][0-9]+)/iu';
                    $patterns[] = '/(' . $escapedRegency . '.*?' . $escapedSynonym . '.*?' . $escapedYear . '.*?)([0-9]+[.,][0-9]+)/iu';
                }
                $patterns[] = '/(' . $escapedSynonym . '.*?' . $escapedYear . '.*?)([0-9]+[.,][0-9]+)/iu';
                $patterns[] = '/(' . $escapedYear . '.*?' . $escapedSynonym . '.*?)([0-9]+[.,][0-9]+)/iu';

                foreach ($patterns as $pattern) {
                    if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                        foreach ($matches as $match) {
                            $detectedStr = $match[2];
                            $detectedNum = (float)str_replace(',', '.', $detectedStr);

                            if (abs($detectedNum - $year) < 0.1) {
                                continue;
                            }

                            if (abs($detectedNum - $officialValue) > $tolerance) {
                                // Skip jika PPP nominal besar ditulis pendek / dibulatkan
                                if ($indicator === 'PPP' && $detectedNum < 1000) {
                                    continue;
                                }

                                $correctedStr = number_format($officialValue, 2, ',', '.');
                                if ($indicator === 'GINI') {
                                    $correctedStr = number_format($officialValue, 3, ',', '.');
                                }

                                Log::channel('ai_agent')->warning('fact_grader.numeric_override.agnostic', [
                                    'indicator' => $indicator,
                                    'year' => $year,
                                    'detected' => $detectedNum,
                                    'official' => $officialValue,
                                    'corrected' => $correctedStr
                                ]);

                                $content = str_replace($match[0], $match[1] . $correctedStr, $content);
                            }
                        }
                    }
                }
            }
        }

        // Terapkan YoY & Outlier Sanity Checks
        $content = self::checkYoYPlausibility($content, $facts);

        return $content;
    }

    /**
     * Memeriksa konsistensi runtun waktu YoY di memori.
     */
    protected static function checkYoYPlausibility(string $content, array $facts): string
    {
        $grouped = [];
        foreach ($facts as $fact) {
            $key = $fact['indicator'] . '_' . $fact['regency_code'];
            $grouped[$key][$fact['year']] = $fact['value'];
        }

        foreach ($grouped as $key => $years) {
            if (count($years) < 2) {
                continue;
            }

            ksort($years);
            $yearKeys = array_keys($years);

            for ($i = 1; $i < count($yearKeys); $i++) {
                $y1 = $yearKeys[$i - 1];
                $y2 = $yearKeys[$i];
                $v1 = $years[$y1];
                $v2 = $years[$y2];

                $parts = explode('_', $key);
                $indicator = $parts[0];

                if (self::isStatisticalOutlier($indicator, $v1, $v2)) {
                    Log::channel('ai_agent')->warning('fact_grader.yoy_outlier_detected', [
                        'indicator' => $indicator,
                        'year_1' => $y1,
                        'value_1' => $v1,
                        'year_2' => $y2,
                        'value_2' => $v2,
                        'delta' => abs($v2 - $v1)
                    ]);
                }
            }
        }

        return $content;
    }

    /**
     * Intersep respons LLM jika mendeteksi adanya status RESTRICTED/grounding_failed
     * di tool results, sementara LLM tetap mencoba mempublikasikan angka hasil halusinasi.
     */
    protected static function enforceOutputShield(string $content, array $steps): string
    {
        if (empty($steps)) {
            return $content;
        }

        $hasRestricted = false;
        $restrictedMessage = '';

        foreach ($steps as $step) {
            $result = $step['result'] ?? [];
            if (isset($result['is_restricted']) && $result['is_restricted'] === true) {
                $hasRestricted = true;
                $restrictedMessage = $result['message'] ?? 'Akses data dinamis BPS dibatasi.';
                break;
            }
        }

        if ($hasRestricted) {
            if (preg_match('/\b\d{2}[.,]\d{1,2}\b/', $content)) {
                Log::channel('ai_agent')->warning('fact_grader.output_shield.intercepted_hallucination', [
                    'message' => 'LLM attempted to write statistical decimals under a restricted BPS API key. Intercepting response.',
                ]);

                return implode("\n\n", [
                    '⚠️ **Keterbatasan Layanan Data BPS**',
                    'Mohon maaf, sistem saat ini tidak dapat menampilkan rincian data analisis statistik yang Anda minta secara lengkap.',
                    '**Penyebab:** ' . $restrictedMessage,
                    'Sistem kami melarang keras spekulasi, estimasi, atau manipulasi angka statistik di luar data resmi BPS demi menjaga integritas informasi publik dan akurasi data dasar & sektoral pemerintah.',
                    'Silakan verifikasi data resmi atau sesuaikan kata kunci pencarian Anda ke indikator lain yang tersedia. Anda juga dapat memverifikasi langsung melalui situs resmi [bps.go.id](https://www.bps.go.id).',
                ]);
            }
        }

        return $content;
    }
}
