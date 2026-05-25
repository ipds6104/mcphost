<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\OffTopicQueryException;
use App\Exceptions\SecurityException;

class LlmInputValidator
{
    /**
     * Pola Regex OWASP LLM Top 10 untuk mendeteksi upaya Jailbreaking & Prompt Injection.
     *
     * @var array<string>
     */
    protected static array $injectionPatterns = [
        '/(?:ignore|ignroe|lupakan).*(?:instructions|directives|rules|prompt)/i',
        '/(?:reveal|output|print|show|expose|what is|tell me).*(?:system\s+)?(?:prompt|instructions|directives|rules|tokens)/i',
        '/(?:system|systme|hidden)\s+(?:prompt|instruction|directive|rule|token)/i',
        '/(?:bypass|by-pass)\s+(?:system|security|safeguards|filters)/i',
        '/(?:acting|act)\s+as\s+(?:a|an)?\s*(?:developer|admin|root|superuser|dev|dan)/i',
        '/(?:pretend|be|pretend\s+you\s+are)\s+(?:a|an|to\s+be)?\s*(?:developer|admin|root|superuser|dev|dan)/i',
        '/jailbreak/i',
        '/dan\s+mulai\s+sekarang\s+kamu/i',
        '/lupakan\s+instruksi/i',
        '/override\s+(?:system|prompt|safety)/i',
        '/developer\s+mode/i',
    ];

    /**
     * Kata kunci domain bisnis (statistik, wilayah, pembangunan) untuk Off-Topic Filtering.
     *
     * @var array<string>
     */
    protected static array $allowedKeywords = [
        'statistik', 'data', 'bps', 'wilayah', 'kabupaten', 'kota',
        'daerah', 'indikator', 'ekonomi', 'pembangunan', 'sosial',
        'komparasi', 'perbandingan', 'mempawah', 'sleman', 'kutai',
        'jakarta', 'ipkp', 'ips', 'epss', 'ikp', 'ipm', 'pdrb', 'tren',
        'kesehatan', 'pendidikan', 'kerja', 'pencari', 'industri', 'kematian', 'spm',
        'neonatal', 'dinas', 'sektoral', 'dprd', 'kb', 'lansia', 'komplikasi',
        'analisis', 'laporan', 'regional', 'provinsi', 'kinerja',
        'halo', 'hai', 'pagi', 'siang', 'sore', 'malam', 'tanya',
    ];

    /**
     * Memvalidasi prompt pengguna sebelum masuk ke orkestrasi LLM.
     *
     * @throws OffTopicQueryException
     */
    public static function validate(string $prompt): string
    {
        $cleanPrompt = trim($prompt);

        // 1. Length Constraint (Mencegah spam/token exhaustion)
        if (strlen($cleanPrompt) > 1500) {
            throw new \InvalidArgumentException('Prompt Anda terlalu panjang (maksimal 1.500 karakter).');
        }

        if (empty($cleanPrompt)) {
            throw new \InvalidArgumentException('Prompt tidak boleh kosong.');
        }

        // 2. PII Filter (Scrubbing e-mail dan nomor ponsel secara dinamis)
        $cleanPrompt = self::scrubPii($cleanPrompt);

        // 3. Jailbreak / Prompt Injection Scanner
        foreach (self::$injectionPatterns as $pattern) {
            if (preg_match($pattern, $cleanPrompt)) {
                throw new SecurityException('Upaya manipulasi prompt terdeteksi. Permintaan Anda ditolak.');
            }
        }

        // 4. Off-Topic Dialogue Rails (Short-circuit filter)
        if (! self::isQueryOnTopic($cleanPrompt)) {
            throw new OffTopicQueryException(
                'Maaf, asisten analitis ini secara eksklusif hanya melayani konsultasi data statistik dasar BPS, statistik sektoral daerah, komparasi daerah, dan pembangunan regional.'
            );
        }

        return $cleanPrompt;
    }

    /**
     * Menyaring dan menyensor PII (Personal Identifiable Information).
     */
    protected static function scrubPii(string $text): string
    {
        // Sensor e-mail
        $text = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[EMAIL_REDACTED]', $text);

        // Sensor nomor telepon/ponsel (Format Indonesia +62 atau 08)
        $text = preg_replace('/(\+62|62|0)8[1-9][0-9]{6,10}/', '[PHONE_REDACTED]', $text);

        return $text;
    }

    /**
     * Memeriksa relevansi semantik prompt terhadap domain analitik statistik.
     */
    protected static function isQueryOnTopic(string $text): bool
    {
        $lowercaseText = strtolower($text);

        // Jika prompt sangat pendek (sapaan seperti "halo", "hai"), izinkan lolos
        if (strlen($lowercaseText) < 10) {
            return true;
        }

        // Cari setidaknya satu kata kunci yang cocok dengan domain statistik/pemerintahan
        foreach (self::$allowedKeywords as $keyword) {
            if (str_contains($lowercaseText, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
