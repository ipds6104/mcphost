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
     * Toleransi selisih numerik sebelum override deterministik dilakukan.
     * Angka dalam teks LLM yang menyimpang melebihi threshold ini akan diganti
     * dengan nilai resmi dari tool result BPS.
     */
    private const NUMERIC_TOLERANCE = 0.5;

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

        // ── LAPISAN 2: Numeric Grounding ────────────────────────────────────────
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
    // LAPISAN 2: Numeric Grounding
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Bandingkan angka dalam teks LLM terhadap nilai resmi dari tool result BPS.
     * Jika selisih melebihi NUMERIC_TOLERANCE → override deterministik.
     *
     * Strategi: ekstrak pasangan (label, nilai) dari tool steps, lalu cari
     * pola angka serupa di dalam teks dan koreksi jika menyimpang.
     *
     * @param  array  $steps  Tool steps dari ProcessAiAgentQuery
     */
    protected static function enforceNumericGrounding(string $content, array $steps): string
    {
        if (empty($steps)) {
            return $content;
        }

        // Kumpulkan semua angka desimal dari tool results BPS
        $officialValues = self::extractOfficialValues($steps);

        if (empty($officialValues)) {
            return $content;
        }

        foreach ($officialValues as $label => $officialNum) {
            // Cari pola angka desimal di sekitar kata kunci label dalam teks LLM
            // Contoh: "IPM Mempawah: 79,13" atau "IPM: 78.5"
            $escapedLabel = preg_quote($label, '/');

            $pattern = '/(' . $escapedLabel . '[^0-9]{0,30}?)(\d{2,3}[.,]\d{1,2})/iu';

            if (preg_match_all($pattern, $content, $found, PREG_SET_ORDER)) {
                foreach ($found as $match) {
                    $detectedStr = $match[2];
                    $detectedNum = (float) str_replace(',', '.', $detectedStr);

                    if (abs($detectedNum - $officialNum) > self::NUMERIC_TOLERANCE) {
                        $correctedStr = number_format($officialNum, 2, ',', '.');

                        Log::channel('ai_agent')->warning('fact_grader.numeric_override', [
                            'label' => $label,
                            'detected' => $detectedNum,
                            'official' => $officialNum,
                            'delta' => abs($detectedNum - $officialNum),
                            'corrected' => $correctedStr,
                        ]);

                        $content = str_replace($match[0], $match[1] . $correctedStr, $content);
                    }
                }
            }
        }

        return $content;
    }

    /**
     * Ekstrak pasangan (nama-indikator => nilai-numerik) dari tool step results.
     *
     * @return array<string, float> ['IPM' => 70.13, 'PDRB' => ...]
     */
    private static function extractOfficialValues(array $steps): array
    {
        $values = [];

        foreach ($steps as $step) {
            $result = $step['result'] ?? [];

            // Iterasi rekursif mencari angka numerik bermakna dalam tool result
            self::flattenNumericValues($result, $values);
        }

        return $values;
    }

    /**
     * Rekursif flatten array tool result untuk mengekstrak angka numerik.
     *
     * @param  array<string, float>  &$values  Output reference
     * @param  string  $prefix  Path prefix untuk key generation
     */
    private static function flattenNumericValues(mixed $data, array &$values, string $prefix = ''): void
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                // Skip metadata dan non-data keys
                if (in_array($key, ['_meta', 'error', 'source', 'fetched_at', 'cache_status'], true)) {
                    continue;
                }

                $newPrefix = $prefix ? "{$prefix}.{$key}" : (string) $key;
                self::flattenNumericValues($value, $values, $newPrefix);
            }
        } elseif (is_float($data) || (is_int($data) && $data > 0)) {
            // Hanya simpan nilai yang bermakna secara statistik (0-10000)
            if ($data > 0 && $data < 10000) {
                $values[$prefix] = (float) $data;
            }
        }
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
            // Jika LLM mencoba mengarang data numerik desimal baru (misal: "70,61" atau "68.91")
            // kita intersep respons secara penuh demi integritas data BPS.
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
