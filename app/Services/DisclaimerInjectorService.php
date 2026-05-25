<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * DisclaimerInjectorService
 *
 * Post-processor yang berjalan SETELAH LLM menghasilkan respons, SEBELUM
 * disimpan ke database dan di-broadcast ke UI.
 *
 * Tanggung jawab:
 *  1. Deteksi sektor statistik dan rentang tahun dalam teks respons LLM
 *  2. Jika rentang tahun melewati known methodology break → inject disclaimer
 *  3. Inject source citation jika ada angka numerik tanpa atribusi sumber
 *  4. Tambahkan annotation ke json-chart jika series melewati break tahun
 */
class DisclaimerInjectorService
{
    public function __construct(
        private readonly MethodologyRegistryService $registry
    ) {}

    /**
     * Entry point utama: proses teks respons LLM dan injeksi disclaimer/citation.
     */
    public function process(string $content, array $toolSteps = []): string
    {
        // ── 1. Deteksi dan injeksi disclaimer metodologi ─────────────────────
        $content = $this->injectMethodologyDisclaimers($content);

        // ── 2. Injeksi source citation jika ada angka tanpa sumber ───────────
        $content = $this->injectSourceCitation($content, $toolSteps);

        // ── 3. Tambahkan annotation pada json-chart ───────────────────────────
        $content = $this->annotateChartBreaks($content);

        return $content;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // LAPISAN 1: Methodology Disclaimer
    // ──────────────────────────────────────────────────────────────────────────

    private function injectMethodologyDisclaimers(string $content): string
    {
        $years = $this->registry->extractYearsFromText($content);

        if (count($years) < 2) {
            return $content; // Tidak ada rentang tahun yang bisa dibandingkan
        }

        $minYear = min($years);
        $maxYear = max($years);

        // Cek setiap sektor yang diketahui
        $sectors = ['IPM', 'PDRB', 'IHK', 'Ketenagakerjaan', 'Kemiskinan'];
        $allDisclaimers = '';
        $injectedSectors = [];

        foreach ($sectors as $sector) {
            // Skip jika sektor ini tidak disebutkan dalam teks
            if (! $this->sectorMentionedInText($sector, $content)) {
                continue;
            }

            // Hindari duplikasi jika sektor yang sama sudah diproses
            if (in_array($sector, $injectedSectors, true)) {
                continue;
            }

            $breaks = $this->registry->detectBreaks($sector, $minYear, $maxYear);
            if (! empty($breaks)) {
                $disclaimer = $this->registry->generateDisclaimer($sector, $breaks);
                $allDisclaimers .= $disclaimer;
                $injectedSectors[] = $sector;

                Log::channel('ai_agent')->info('disclaimer_injector.disclaimer_injected', [
                    'sector' => $sector,
                    'year_range' => "{$minYear}–{$maxYear}",
                    'breaks' => array_column($breaks, 'break_year'),
                ]);
            }
        }

        // Tambahkan semua disclaimer di akhir konten (sebelum json-chart jika ada)
        if ($allDisclaimers !== '') {
            // Sisipkan SEBELUM blok ```json-chart jika ada, atau di akhir teks
            if (str_contains($content, '```json-chart')) {
                $content = str_replace('```json-chart', $allDisclaimers . "\n\n```json-chart", $content);
            } else {
                $content .= $allDisclaimers;
            }
        }

        return $content;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // LAPISAN 2: Source Citation Injector
    // ──────────────────────────────────────────────────────────────────────────

    private function injectSourceCitation(string $content, array $toolSteps): string
    {
        // Jika tidak ada tool steps (LLM tidak memanggil tool BPS), skip
        if (empty($toolSteps)) {
            return $content;
        }

        // Cek apakah respons sudah memiliki atribusi sumber BPS
        $hasCitation = str_contains($content, 'webapi.bps.go.id')
            || str_contains($content, 'BPS WebAPI')
            || str_contains($content, 'Sumber: BPS')
            || str_contains($content, 'bps.go.id');

        if ($hasCitation) {
            return $content; // Sudah ada — tidak perlu tambah
        }

        // Ambil fetched_at dari tool step terakhir yang berhasil
        $fetchedAt = null;
        foreach (array_reverse($toolSteps) as $step) {
            $result = $step['result'] ?? [];
            if (isset($result['_meta']['fetched_at'])) {
                $fetchedAt = $result['_meta']['fetched_at'];
                break;
            }
        }

        $citationNote = "\n\n---\n📊 **Sumber Data:** BPS WebAPI v1 ([webapi.bps.go.id](https://webapi.bps.go.id))"
            . ($fetchedAt ? ' | Diambil pada: ' . date('d M Y H:i', strtotime($fetchedAt)) . ' WIB' : '')
            . "\n⚠️ *Selalu verifikasi angka penting ke situs resmi BPS untuk memastikan metodologi terkini.*";

        // Sisipkan sebelum json-chart atau di akhir
        if (str_contains($content, '```json-chart')) {
            return str_replace('```json-chart', $citationNote . "\n\n```json-chart", $content);
        }

        return $content . $citationNote;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // LAPISAN 3: Chart Annotation
    // ──────────────────────────────────────────────────────────────────────────

    private function annotateChartBreaks(string $content): string
    {
        if (! preg_match('/```json-chart\s*(.*?)\s*```/s', $content, $matches)) {
            return $content;
        }

        try {
            $chartJson = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $content; // Biarkan FactGraderService/JsonRepairer yang handle
        }

        // Deteksi tahun dari labels chart
        $labels = $chartJson['labels'] ?? $chartJson['data']['labels'] ?? [];
        $years = array_filter(array_map(fn ($l) => is_numeric($l) ? (int) $l : null, $labels));

        if (count($years) < 2) {
            return $content;
        }

        $minYear = min($years);
        $maxYear = max($years);

        // Cek semua sektor
        $annotations = $chartJson['annotations'] ?? [];
        $sectors = ['IPM', 'PDRB', 'IHK', 'Ketenagakerjaan', 'Kemiskinan'];

        foreach ($sectors as $sector) {
            if (! $this->sectorMentionedInText($sector, $content)) {
                continue;
            }
            $breaks = $this->registry->detectBreaks($sector, $minYear, $maxYear);
            foreach ($breaks as $break) {
                $annotations[] = [
                    'x' => $break->break_year,
                    'label' => "Break Metodologi {$sector} ({$break->break_year})",
                    'color' => '#F59E0B', // amber
                    'type' => 'methodology_break',
                ];
            }
        }

        if (! empty($annotations)) {
            $chartJson['annotations'] = array_values(
                array_unique($annotations, SORT_REGULAR)
            );

            $updatedChart = json_encode($chartJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $content = str_replace(
                "```json-chart\n{$matches[1]}\n```",
                "```json-chart\n{$updatedChart}\n```",
                $content
            );
        }

        return $content;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────────────────────────

    private function sectorMentionedInText(string $sector, string $text): bool
    {
        return $this->registry->isSectorMentioned($sector, $text);
    }
}
