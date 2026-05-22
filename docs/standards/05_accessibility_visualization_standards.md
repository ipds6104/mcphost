# Accessibility & Data Visualization Standards

Dokumen ini mendefinisikan standar aksesibilitas web berdasarkan pedoman internasional **W3C Web Content Accessibility Guidelines (WCAG) 2.2** khusus untuk visualisasi data, pembuatan grafik interaktif, dan rendering tabel dinamis pada antarmuka pengguna (UI) obrolan AI mirip Gemini.

---

## 1. W3C WCAG 2.2 Principles for Data Visualization

Untuk memastikan visualisasi data (grafik komparatif wilayah dan tahun) dapat diakses oleh semua pengguna, termasuk penyandang disabilitas (seperti buta warna atau pengguna *screen reader*), kita menerapkan tiga aturan WCAG 2.2 berikut:

### A. Kontras Warna (Contrast Ratio) - WCAG Success Criterion 1.4.11 (Non-Text Contrast)
*   Semua elemen visual penting dalam grafik (batang bar chart, garis line chart, batas lingkaran pie chart) wajib memiliki rasio kontras minimal **3:1** terhadap warna latar belakang sekitarnya.
*   Teks label grafik, legenda, dan angka pada sumbu koordinat wajib memenuhi kriteria kontras minimal **4.5:1** terhadap warna latar belakang untuk tingkat keterbacaan tinggi.

### B. Penggunaan Warna Sebagai Satu-satunya Informasi - WCAG Success Criterion 1.4.1
*   **Aturan:** Warna **tidak boleh** digunakan sebagai satu-satunya indikator visual untuk menyampaikan informasi, membedakan data, atau menunjukkan aksi.
*   **Penerapan pada Grafik:**
    *   **Line Chart:** Gunakan gaya garis yang berbeda (e.g., garis putus-putus `--`, garis titik-titik `...`, atau ketebalan berbeda) selain perbedaan warna.
    *   **Bar Chart:** Gunakan pola arsiran (*pattern fill*) atau tambahkan label angka nilai secara langsung di atas setiap batang grafik agar pengguna dengan buta warna total tetap dapat membedakan nilai antar wilayah.

---

## 2. Palet Warna Aksesibel (Accessible Color Palettes)

Kita menggunakan palet warna terkurasi dengan kontras yang teruji untuk memvisualisasikan data komparatif kabupaten/kota:

| Kategori Data | Nama Warna | Kode HEX | Rasio Kontras (Dark/Light Mode) |
| :--- | :--- | :--- | :--- |
| **Kabupaten A (Utama)**| Royal Blue | `#2563EB` (Blue 600) | > 4.5:1 terhadap putih |
| **Kabupaten B (Komparasi)**| Emerald Green| `#059669` (Emerald 600)| > 4.5:1 terhadap putih |
| **Kabupaten C (Komparasi)**| Dark Amber | `#D97706` (Amber 600) | > 4.5:1 terhadap putih |
| **Garis Kisi (Gridlines)**| Cool Gray | `#E5E7EB` (Gray 200) | 1.5:1 (hanya sebagai dekoratif) |

*   **Dark Mode Support:** Saat beralih ke mode gelap (dark mode), palet warna grafik wajib menyesuaikan saturasi (menggunakan gradasi warna pastel yang lebih terang, e.g., Blue 400 `#60A5FA` dan Emerald 400 `#34D399`) agar tetap nyaman dipandang mata dan memiliki kontras yang cukup terhadap latar belakang gelap gulita.

---

## 3. Aksesibilitas Pembaca Layar (Screen Reader Accessibility)

Grafik visual interaktif (Canvas/SVG) sering kali tidak dapat dibaca oleh alat bantu pembaca layar (*screen reader*). Oleh karena itu, kita mewajibkan implementasi teknik aksesibilitas berikut di frontend:

### A. Tabel Data Alternatif (Accessible Data Table)
Setiap kali grafik interaktif dirender di UI chat, sistem wajib menyertakan atau menyembunyikan tabel data teks terstruktur secara semantik di belakang layar (menggunakan kelas CSS *screen-reader-only* / `sr-only`), sehingga pengguna tunanetra tetap dapat membaca angka komparatif secara berurutan menggunakan pembaca layar.
```html
<div class="sr-only">
  <table>
    <caption>Komparasi Nilai Rapot 2023-2025</caption>
    <thead>
      <tr>
        <th>Kabupaten</th>
        <th>2023</th>
        <th>2024</th>
        <th>2025</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Kabupaten A</td>
        <td>72.5</td>
        <td>78.2</td>
        <td>81.0</td>
      </tr>
    </tbody>
  </table>
</div>
```

### B. Atribut ARIA (Accessible Rich Internet Applications)
*   Elemen canvas grafik wajib diberi atribut `role="img"` dan `aria-label` yang berisi deskripsi singkat dari grafik tersebut.
*   *Contoh:* `<canvas id="comparisonChart" role="img" aria-label="Grafik batang komparasi nilai rapot Kabupaten A, B, dan C dari tahun 2023 hingga 2025. Nilai tertinggi diraih oleh Kabupaten A di tahun 2025 dengan nilai 81.0"></canvas>`.
