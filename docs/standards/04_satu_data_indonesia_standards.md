# Satu Data Indonesia & Standar Statistik Nasional (BPS)

Dokumen ini mendefinisikan standar integrasi, tata kelola, dan interoperabilitas data statistik pemerintah daerah berdasarkan **Peraturan Presiden (Perpres) Nomor 39 Tahun 2019 tentang Satu Data Indonesia (SDI)** dan standar metodologi **Badan Pusat Statistik (BPS)**.

---

## 1. Prinsip Satu Data Indonesia (SDI)

Berdasarkan Pasal 11 Perpres 39/2019, setiap data yang dihasilkan oleh instansi pemerintah wajib memenuhi 4 prinsip Satu Data Indonesia:

```
┌────────────────────────────────────────────────────────┐
│              PRINSIP SATU DATA INDONESIA               │
├──────────────┬──────────────┬──────────────┬───────────┤
│   Memiliki   │   Memenuhi   │  Memenuhi   │ Menggunakan │
│ Standar Data │   Metadata   │ Interopera-  │    Kode     │
│              │  Statistik   │   bilitas    │  Referensi │
└──────────────┴──────────────┴──────────────┴───────────┘
```

---

## 2. Standar Data Statistik Sektoral
Standar data menetapkan konsep, definisi, cakupan, klasifikasi, ukuran, dan satuan yang seragam untuk setiap variabel data rujukan pemerintah:
*   **Konsep & Definisi:** Deskripsi esensi variabel (e.g., *"Indeks Kematangan Penjaminan Kualitas Data"* didefinisikan sebagai tingkat kepatuhan produsen data terhadap standar penjaminan kualitas statistik).
*   **Satuan Ukuran:** Satuan baku untuk nilai indikator (e.g., persen (`%`), indeks (skala `1-5`), atau jumlah jiwa).
*   **Klasifikasi:** Pengelompokan data terstandar (e.g., Klasifikasi Baku Lapangan Usaha Indonesia - KBLI, Klasifikasi Jabatan, dll.).

---

## 3. Standar Metadata Statistik (MS-Ind)

Untuk memastikan data dapat dipahami dan tidak disalahartikan, setiap dataset wajib dilengkapi dengan **Metadata** yang merujuk pada standar **Metadata Statistik Indonesia (MS-Ind)** yang diatur oleh BPS selaku Pembina Data Statistik Nasional:

Setiap metadata wajib memuat elemen terstruktur berikut:
1.  **Metadata Kegiatan:** Menjelaskan penyelenggara, latar belakang, tujuan, dan metodologi pengumpulan data (e.g., survei, kompilasi produk administrasi).
2.  **Metadata Variabel:** Menjelaskan definisi teknis variabel, konsep, tipe data, dan formula perhitungan variabel tersebut.
3.  **Metadata Indikator:** Informasi mengenai hasil akhir yang dihasilkan (e.g., formula pembentuk indikator komposit seperti Indeks Pembangunan Manusia atau Rapot Kematangan Penjaminan Kualitas).

---

## 4. Interoperabilitas Data Pemerintah
Interoperabilitas adalah kemampuan sistem data untuk saling berkomunikasi, bertukar data, dan menggunakan kembali informasi yang dipertukarkan secara mulus.
*   **Format Terbuka:** Data harus disajikan dalam format terbuka (*open formats*) yang dapat dibaca mesin (*machine-readable*), seperti **JSON**, **XML**, atau **CSV** (bukan file biner tertutup seperti XLS terenkripsi atau PDF hasil pemindaian gambar).
*   **API Interoperabilitas:** Akses data difasilitasi melalui arsitektur RESTful API atau JSON-RPC yang aman untuk integrasi lintas instansi pusat dan daerah.

---

## 5. Standar Kode Referensi Wilayah (BPS vs Kemendagri)

Integrasi data antar wilayah sering kali terkendala karena adanya dua standar pengkodean wilayah di Indonesia. Sistem kita wajib menyediakan tabel relasi (*mapping relation*) di antara keduanya:

| Tingkatan Wilayah | Kode BPS (Kerja Statistik) | Kode Kemendagri (Administrasi) | Contoh (Kab. Mempawah) |
| :--- | :--- | :--- | :--- |
| **Provinsi** | 2 Digit (Numerik) | 2 Digit dengan titik | BPS: `61` \| Kemendagri: `61` (Kalbar) |
| **Kabupaten/Kota**| 4 Digit (Numerik) | 5 Digit (Numerik dengan titik) | BPS: `6104` \| Kemendagri: `61.02` |
| **Kecamatan** | 7 Digit (Numerik) | 8 Digit (Numerik dengan titik) | BPS: `6104010` \| Kemendagri: `61.02.01` |

### Kebijakan Penggunaan:
*   Semua pencarian data strategis berbasis wilayah harus dapat memetakan parameter masukan dari LLM (baik yang menyebutkan nama nama resmi Kemendagri maupun BPS) ke dalam kode internal sistem melalui **tabel relasi kode wilayah**.

---

## 6. Standar Penjaminan Kualitas Data & Konsistensi Statistik (BPS EPSS)

Sejalan dengan standar **Evaluasi Penjaminan Kualitas Statistik Sektoral (EPSS)** yang dikembangkan oleh BPS, aplikasi ini mendukung penilaian dan visualisasi tingkat kematangan data melalui dua SOP utama:

### A. SOP Penjaminan Kualitas Data (Tingkat Kematangan 1 - 5)
Mengukur seberapa tertata pengelolaan kualitas data dari tahap awal hingga akhir:
*   **Tahap Rintisan (Level 1):** Penjaminan kualitas dilakukan secara ad-hoc tanpa standar tertulis.
*   **Tahap Terkelola (Level 2):** Terdapat SOP penjaminan kualitas data tetapi baru diterapkan di sebagian kecil unit kerja.
*   **Tahap Terdefinisi (Level 3):** SOP penjaminan kualitas data telah didefinisikan secara formal dan diterapkan di seluruh unit produsen data.
*   **Tahap Terpadu (Level 4):** Evaluasi kualitas data dilakukan secara otomatis terpadu menggunakan metrik terukur.
*   **Tahap Optimum (Level 5):** Penjaminan kualitas dievaluasi secara terus menerus untuk peningkatan berkelanjutan secara otomatis.

### B. SOP Konsistensi Statistik
Menjamin konsistensi metodologi dan hasil data statistik sektoral di tingkat kabupaten/kota agar dapat diperbandingkan lintas waktu (*time-series*) dan lintas wilayah (*cross-section*) tanpa ada anomali atau kontradiksi data.
*   Aplikasi AI akan memantau indikator konsistensi untuk memastikan visualisasi tren data tahun 2023, 2024, dan 2025 tidak mengalami anomali metodologis yang merusak validitas analisis komparatif pemerintah daerah.
