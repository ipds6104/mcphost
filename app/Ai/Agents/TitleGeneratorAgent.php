<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

class TitleGeneratorAgent implements Agent
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return implode("\n", [
            'Anda adalah spesialis pembuat judul percakapan yang sangat handal.',
            'Tugas Anda adalah membaca pesan pertama pengguna dan merumuskan judul percakapan yang singkat, elegan, padat, dan representatif dalam Bahasa Indonesia sepanjang 3 hingga 5 kata.',
            'ATURAN UTAMA:',
            '- Output HANYA berupa judul itu sendiri.',
            '- JANGAN gunakan tanda kutip ( tunggal maupun ganda ).',
            '- JANGAN gunakan tanda titik di akhir judul.',
            '- JANGAN sertakan kata pengantar seperti "Judul: ...", "Berikut adalah ...", atau penjelasan apa pun.',
            '- Fokuskan pada esensi topik analitik yang dibahas (misal: "Analisis IPS Sleman 2023" atau "Komparasi Kinerja Mempawah").',
        ]);
    }
}
