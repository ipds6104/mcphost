<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * BpsMethodologyBreaksSeeder
 *
 * Mengisi tabel bps_methodology_breaks dengan semua perubahan metodologi BPS
 * yang diketahui menyebabkan diskontinuitas data series (series breaks).
 *
 * Data ini adalah "knowledge base" statis yang digunakan oleh MethodologyRegistryService
 * untuk mendeteksi perbandingan lintas metodologi yang tidak valid dan menginjeksi
 * disclaimer secara otomatis ke respons LLM.
 *
 * Sumber: Berita Resmi Statistik (BRS) BPS RI, publikasi resmi BPS.
 */
class BpsMethodologyBreaksSeeder extends Seeder
{
    public function run(): void
    {
        $breaks = [
            // ── IPM (Indeks Pembangunan Manusia) ──────────────────────────────────
            [
                'sector' => 'IPM',
                'break_year' => 2014,
                'break_type' => 'formula_change',
                'old_version' => 'IPM Metode Lama (2005): AMH + Rata-rata Aritmatik, PNB Tahun Dasar 2005',
                'new_version' => 'IPM Metode Baru (2014): RLS menggantikan AMH, Rata-rata Geometrik, PNB Tahun Dasar 2011',
                'description' => 'BPS mengganti Angka Melek Huruf (AMH) dengan Rata-rata Lama Sekolah (RLS) dan Harapan Lama Sekolah (HLS) sebagai komponen pengetahuan. Formula agregasi berubah dari rata-rata aritmatik ke rata-rata geometrik untuk menghilangkan efek kompensasi antar-dimensi. Angka IPM metode baru secara sistematis lebih rendah dibanding metode lama.',
                'brs_reference' => 'Publikasi BPS: Indeks Pembangunan Manusia 2014 (Metode Baru)',
                'is_backcasted' => true,
                'safe_comparison_start_year' => 2010,
            ],
            [
                'sector' => 'IPM',
                'break_year' => 2023,
                'break_type' => 'component_revision',
                'old_version' => 'IPM 2022: Komponen standar hidup layak menggunakan PPP 2017',
                'new_version' => 'IPM 2023: Revisi komponen Pengeluaran Per Kapita Disesuaikan (PPP diperbarui)',
                'description' => 'BPS merevisi komponen Standar Hidup Layak dalam penghitungan IPM menggunakan data Susenas terbaru dan paritas daya beli (PPP) yang diperbarui. Angka IPM 2023 ke atas menggunakan metodologi yang direvisi dan dapat berbeda dari proyeksi linear data sebelumnya.',
                'brs_reference' => 'BRS BPS No. 67/12/Th. XXV, 1 Desember 2023',
                'is_backcasted' => false,
                'safe_comparison_start_year' => 2023,
            ],

            // ── PDRB / PDB (Pertumbuhan Ekonomi) ─────────────────────────────────
            [
                'sector' => 'PDRB',
                'break_year' => 2010,
                'break_type' => 'base_year_change',
                'old_version' => 'PDRB ADHK Tahun Dasar 2000',
                'new_version' => 'PDRB ADHK Tahun Dasar 2010 (adopsi SNA 2008)',
                'description' => 'Pergeseran tahun dasar PDRB dari 2000 ke 2010 sekaligus adopsi System of National Accounts (SNA) 2008. Paket harga dasar, bobot sektoral, dan cakupan wilayah berubah secara substansial. Data PDRB ADHK sebelum dan sesudah 2010 tidak dapat disambung langsung.',
                'brs_reference' => 'Publikasi BPS: PDB Indonesia Triwulanan 2010 (Tahun Dasar 2010)',
                'is_backcasted' => true,
                'safe_comparison_start_year' => 2010,
            ],

            // ── IHK / Inflasi ─────────────────────────────────────────────────────
            [
                'sector' => 'IHK',
                'break_year' => 2022,
                'break_type' => 'base_year_change',
                'old_version' => 'IHK Tahun Dasar 2018=100 (SBH 2018)',
                'new_version' => 'IHK Tahun Dasar 2022=100 (SBH 2022)',
                'description' => 'Pemutakhiran Survei Biaya Hidup (SBH) 2022 menghasilkan diagram timbang baru, menambah komoditas modern (paket data internet, layanan streaming, dll.), dan memperluas cakupan kota dari 90 menjadi 150 kota. Indeks inflasi 2022=100 tidak dapat dibandingkan langsung dengan 2018=100.',
                'brs_reference' => 'BRS BPS: Inflasi IHK 2022 dengan Tahun Dasar Baru 2022=100',
                'is_backcasted' => false,
                'safe_comparison_start_year' => 2022,
            ],

            // ── Ketenagakerjaan / KBLI ────────────────────────────────────────────
            [
                'sector' => 'Ketenagakerjaan',
                'break_year' => 2025,
                'break_type' => 'classification_update',
                'old_version' => 'KBLI 2020 (Klasifikasi Baku Lapangan Usaha Indonesia 2020)',
                'new_version' => 'KBLI 2025 (Perka BPS No. 7 Tahun 2025)',
                'description' => 'KBLI 2025 memisahkan secara tegas sektor Ekonomi Digital, Konten Kreator, Aset Kripto, dan Green Economy dari kategori umum di KBLI 2020. Pergeseran batas antar-sektor usaha ini menyebabkan runtunan data sektoral masa lalu terputus dan tidak dapat dibandingkan langsung dengan data post-2025.',
                'brs_reference' => 'Peraturan Kepala BPS No. 7 Tahun 2025 tentang KBLI 2025',
                'is_backcasted' => false,
                'safe_comparison_start_year' => 2025,
            ],

            // ── Kemiskinan ────────────────────────────────────────────────────────
            [
                'sector' => 'Kemiskinan',
                'break_year' => 2023,
                'break_type' => 'component_revision',
                'old_version' => 'Garis Kemiskinan (GK) berbasis komoditas Susenas 2021',
                'new_version' => 'Garis Kemiskinan (GK) berbasis komoditas Susenas 2023 yang diperbarui',
                'description' => 'BPS memperbarui paket komoditas pembentuk Garis Kemiskinan (GK) berdasarkan hasil Susenas terbaru untuk menyesuaikan pola konsumsi riil masyarakat. Perubahan bobot komoditas (termasuk masuknya rokok kretek filter sebagai salah satu penyumbang terbesar) dapat mempengaruhi naik-turunnya jumlah penduduk miskin secara statistik, bukan semata karena perubahan kondisi ekonomi.',
                'brs_reference' => 'BRS BPS: Profil Kemiskinan di Indonesia September 2023',
                'is_backcasted' => false,
                'safe_comparison_start_year' => 2023,
            ],
        ];

        foreach ($breaks as $break) {
            DB::table('bps_methodology_breaks')->updateOrInsert(
                ['sector' => $break['sector'], 'break_year' => $break['break_year']],
                array_merge($break, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('✅ BpsMethodologyBreaksSeeder: ' . count($breaks) . ' methodology breaks seeded.');
    }
}
