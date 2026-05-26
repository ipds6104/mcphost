<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ToolExecutionHeuristic;

class AgnosticGraderService
{
    /**
     * Lakukan auto-grading terhadap hasil pemanggilan perkakas dengan rubrik Opsi A (Agnostik Murni).
     *
     * @param mixed $result Hasil kembalian perkakas/API
     * @param string $originalQuery Kueri asli dari pengguna
     * @return float Skor hasil evaluasi (0.0, 50.0, atau 100.0)
     */
    public function gradeResponse(mixed $result, string $originalQuery): float
    {
        if (!$result) return 0.0;

        $text = is_array($result) ? json_encode($result) : (string)$result;
        
        // Tier 1: Parsing Struktural JSON yang Aman
        $decoded = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return 0.0; // Bukan format JSON valid
        }

        if (($decoded['isError'] ?? false) === true) {
            return 0.0; // Eror eksplisit dari API
        }

        // Tier 2: Kuantitas Data & Ketiadaan Teks Kegagalan
        $textLower = strtolower($text);
        // Menggunakan double quotes untuk mendeteksi newline nyata pada result:\n[]
        $failureKeywords = ['restricted', 'data tidak ditemukan', 'grounding_failed', "result:\n[]", 'no data field'];
        foreach ($failureKeywords as $kw) {
            if (str_contains($textLower, $kw)) {
                return 0.0;
            }
        }

        if (trim($text) === '[]' || trim($text) === '{}') return 0.0;

        // Tier 3: Structural Ground Truth (Opsi A)
        // Memverifikasi adanya data desimal/numerik yang bersanding dengan dimensi waktu/tahun
        $hasNumerics = (bool)preg_match('/\d+[\.,]\d+/', $text);
        $hasTemporalDimension = (bool)preg_match('/\b(19|20)\d{2}\b/', $text);

        if ($hasNumerics && $hasTemporalDimension) {
            return 100.0; // Lulus Sempurna (Struktural + Semantik)
        }

        return 50.0; // Sukses Struktural Dasar (Tier 1 & 2 Lulus, Tier 3 Lemah)
    }

    /**
     * Terapkan rumus peluruhan (decay logic) pada aturan heuristik setelah eksekusi.
     * Menggunakan updateQuietly untuk mencegah overwrite dari request konkuren konkuren.
     *
     * @param ToolExecutionHeuristic $heuristic
     * @param bool $isSuccess
     * @return void
     */
    public function applyDecay(ToolExecutionHeuristic $heuristic, bool $isSuccess): void
    {
        // 1. Lakukan atomic increment di database untuk mereduksi round-trip
        $heuristic->increment($isSuccess ? 'success_count' : 'failure_count');
        $heuristic->increment('validation_count');
        
        // 2. Ambil status database terbaru secara instan untuk sinkronisasi konkuren
        $heuristic->refresh();
        
        $totalExecutions = $heuristic->success_count + $heuristic->failure_count;
        $updates = [
            'last_validated_at' => now(),
        ];
        
        // 3. Terapkan Formula Peluruhan (Grace Period = 10 Eksekusi)
        if (!$heuristic->is_system && $totalExecutions >= 10) {
            $failureRate = $heuristic->failure_count / $totalExecutions;
            if ($failureRate > 0.40) {
                $updates['is_active'] = false; // Tandai tidak aktif secara otonom
            }
        }
        
        // 4. Update hanya field yang berubah menggunakan updateQuietly untuk menghindari race condition
        $heuristic->updateQuietly($updates);
    }
}
