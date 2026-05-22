# 12. Multistep MCP & Long-Running Chat UX Standards

Dokumen ini mendefinisikan standar emas (*Golden Standards*) desain antarmuka pengguna (UI) dan pengalaman pengguna (UX) untuk menampilkan proses eksekusi agen AI yang bertahap (*multi-step*) dan panggilan perkakas (**Model Context Protocol / MCP**) yang memerlukan waktu pemrosesan lama.

---

## 🗺️ Latar Belakang & Filosofi UX

Aplikasi kecerdasan buatan modern (seperti Google Gemini dan Claude) sering kali harus memanggil berbagai layanan eksternal atau melakukan analisis kompleks yang membutuhkan waktu 5 hingga 30+ detik. Jika antarmuka pengguna membeku (*freeze*) atau hanya menampilkan indikator berputar (*spinner*) tanpa konteks, pengguna akan mengira sistem mengalami masalah/error.

Prinsip utama standar ini adalah **Progressive Disclosure (Penyungkapan Bertahap)**: memberikan transparansi aktivitas agen AI secara real-time demi menjaga keterlibatan (*engagement*) dan kepercayaan pengguna.

---

## 🎨 5 Pilar Desain Multistep UX

### 1. Progressive Disclosure (Thinking Accordions)
Semua panggilan tool MCP wajib dibungkus dalam kontainer akordion lipat (*collapsible accordion*).
* **Perilaku Default:** 
  * Langkah yang sedang berjalan ditampilkan secara detail atau terekspansi (*expanded*).
  * Langkah yang sudah selesai akan ciut (*collapsed*) untuk menghemat ruang vertikal, namun tetap dapat diklik secara manual.
* **Tingkat Detail:** Pengguna tingkat lanjut dapat melihat output respon API atau kueri mentah dengan membuka akordion.

### 2. Live Micro-Indicators (Indikator Status Reaktif)
Setiap baris langkah wajib menampilkan jenis aktivitas dan status eksekusinya menggunakan animasi reaktif:
* **Running (Proses):** Titik biru dengan animasi denyut gelombang (`animate-ping`) di luarnya.
* **Success (Berhasil):** Lingkaran hijau dengan centang tebal (`check-circle`).
* **Failed (Gagal):** Lingkaran merah dengan silang tebal (`x-circle`).
* **Format Penulisan:** Nama perkakas wajib diletakkan dalam tag `code` monospaced tebal (e.g. `fetch_rapot_data`).

### 3. Aurora Sparkle Shimmer (Pulsing Skeleton)
Gunakan efek kerlipan gradien (*aurora shimmering pulse*) di bawah pesan yang sedang dimuat, bukan spinner tradisional:
* **Pola:** Meniru struktur paragraf atau elemen bagan yang akan dirender kelak.
* **Estetika:** Memadukan warna korporat yang harmonis (biru muda BPS, ungu lembut, merah muda Aurora) dengan animasi memudar reaktif (*pulsing*).

### 4. Scroll Lock Recognition (Pencegahan Pemotongan Bacaan)
Ketika langkah-langkah baru dimuat secara asinkron dari WebSockets/Echo:
* **Auto-Scroll:** Viewport otomatis gulir ke bawah agar langkah terbaru selalu terlihat.
* **Scroll-Lock:** Jika koordinat scroll mendeteksi bahwa pengguna sengaja menggulir ke atas untuk membaca riwayat, hentikan sementara pengguliran otomatis agar tulisan tidak bergeser secara paksa dari layar.

### 5. Pretty-Formatted JSON Drawer
Semua data mentah yang dikembalikan oleh perkakas MCP wajib diformat dengan struktur rapi (`JSON.stringify(data, null, 2)`) di dalam elemen `<pre>` berlatar belakang gelap transparan untuk kemudahan audit data statistik.

---

## 🖥️ Standar Komponen Vue 3 (Strongly Typed)

Komponen visual akordion langkah berpikir wajib diekstraksi ke berkas tersendiri (`ThinkingSteps.vue`) untuk menjaga kebersihan berkas induk dan mematuhi batas ukuran **"Natural Seam"** (< 400 baris).

### Kerangka Interface TypeScript (`AgentStep`):
```typescript
interface AgentStep {
    step: number;
    tool: string;
    status: 'running' | 'success' | 'failed';
    result?: unknown;
}
```

### Panduan Visual Tailwind CSS:
* **Kontainer Akordion:** `rounded-xl border border-gray-200/50 bg-white/40 dark:border-gray-800/60 dark:bg-[#1e1f20]/20`
* **Animasi Denyut:** `animate-ping rounded-full bg-blue-400 opacity-75`
* **JSON Preformatted:** `max-h-60 overflow-x-auto border-t bg-gray-50/50 p-3 font-mono text-[10px]`

---

> [!IMPORTANT]
> Semua komponen visual langkah berpikir yang melanggar prinsip di atas atau melampaui batas baris kode wajib direfaktorkan secara preventif. Kepatuhan visualisasi ini diuji langsung melalui pemeriksaan kontras warna WCAG 2.2 AA.
