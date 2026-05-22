# SDMX 3.1 & OECD Statistical Metadata Standards

Dokumen ini menjelaskan standar pertukaran dan tata kelola metadata statistik berdasarkan framework global **SDMX (Statistical Data and Metadata eXchange) Versi 3.1** yang disponsori oleh organisasi internasional terkemuka (**OECD**, **PBB**, **IMF**, **Bank Dunia**, **Eurostat**, **BIS**) dan diimplementasikan secara aktif oleh **Badan Pusat Statistik (BPS)** dalam menyelaraskan Satu Data Indonesia.

---

## 1. Apa itu SDMX?

**SDMX (ISO 17369)** adalah standar internasional terpadu yang dirancang untuk memfasilitasi pertukaran data statistik dan metadata secara efisien, terstruktur, dan interoperabel lintas kementerian, lembaga, organisasi global, dan pemerintah daerah.

Standardisasi ini membagi konsep data menjadi dua bagian utama:
*   **Data Kuantitatif:** Angka statistik riil (e.g., nilai Indeks Kematangan = `3.5`).
*   **Metadata Kualitatif:** Konsep penjelasan, deskripsi metodologi, definisi variabel, cakupan wilayah, dan akurasi data yang menyertainya.

---

## 2. Fitur Utama & Pembaharuan di SDMX 3.1 (Rilis Mei 2025)

Versi **SDMX 3.1** merupakan standar teranyar yang menghadirkan pembaharuan krusial dari versi 3.0 sebelumnya untuk mendukung era data modern:

1.  **Dukungan Horizontally Complex DSDs:** Memungkinkan pendefinisian Data Structure Definitions (DSD) yang lebih fleksibel dan kompleks untuk mendukung komparasi data lintas domain yang sangat beragam.
2.  **Dukungan Microdata:** Tidak hanya untuk data agregat, SDMX 3.1 dirancang tangguh untuk mengelola pertukaran data level mikro (individu/satuan terkecil) secara terstruktur.
3.  **Modernisasi Format JSON (SDMX-JSON 2.0):** Format JSON dioptimalkan secara drastis untuk konsumsi aplikasi web modern (seperti Vue 3 backend API), meminimalkan ukuran payload pertukaran data dibandingkan format XML lama.
4.  **Geospatial Integration:** Pemetaan dimensi geografis yang lebih matang, memudahkan visualisasi data berbasis GIS (Sistem Informasi Geografis).

---

## 3. Komponen Inti SDMX (Core Concepts)

Untuk mengimplementasikan SDMX 3.1 pada data Rapot Pemerintah Daerah, kita wajib mendefinisikan komponen terstruktur berikut:

```
                  ┌─────────────────────────────────┐
                  │   DATA SET (Rapot Kabupaten)    │
                  └────────────────┬────────────────┘
                                   │
         ┌─────────────────────────┼─────────────────────────┐
         ▼                         ▼                         ▼
┌──────────────────┐      ┌──────────────────┐      ┌──────────────────┐
│    DIMENSIONS    │      │    MEASURES      │      │    ATTRIBUTES    │
│ (Aspek Struktur) │      │  (Angka Riil)    │      │ (Keterangan/SOP) │
└──────────────────┘      └──────────────────┘      └──────────────────┘
```

### A. Dimensions (Sumbu Sruktural)
Menentukan struktur kunci unik dari setiap titik data. Contoh dimensi untuk data Rapot Kabupaten:
*   `FREQ`: Frekuensi pengumpulan data (e.g., `A` = Annual/Tahunan).
*   `REF_AREA`: Wilayah referensi (e.g., Kode wilayah Kabupaten BPS `6104`).
*   `INDICATOR`: Nama indikator statistik (e.g., `KEMATANGAN_KUALITAS_DATA`).
*   `TIME_PERIOD`: Periode waktu (e.g., `2024`).

### B. Measures (Pengukuran)
Nilai numerik atau pengamatan aktual dari indikator yang diukur.
*   *Contoh:* Nilai `3.0` pada dimensi di atas.

### C. Attributes (Atribut Deskriptif)
Metadata tambahan yang memperjelas nilai pengukuran, seperti status observasi atau metodologi yang digunakan.
*   *Contoh:* `OBS_STATUS` (`A` = Normal/Official, `E` = Estimated/Estimasi), `UNIT` (`INDEX` = Skala Kematangan).

---

## 4. Format Pertukaran Data di Web Modern

Dalam arsitektur Laravel 13 dan Vue 3, kita meninggalkan format lama XML (`SDMX-ML`) dan beralih menggunakan standar **`SDMX-JSON`** untuk pertukaran data internal.

### Contoh Representasi SDMX-JSON 3.1:
```json
{
  "meta": {
    "schema": "https://metadata.sdmx.org/schemas/v3.1/sdmx-json",
    "id": "RAPOT_KOMPARASI_2024",
    "prepared": "2026-05-22T16:21:00Z",
    "sender": { "id": "BPS_Mempawah" }
  },
  "data": {
    "dataSets": [
      {
        "action": "Information",
        "observations": {
          "0:0:0:0": [3.5, {"OBS_STATUS": "A"}],
          "0:1:0:0": [3.0, {"OBS_STATUS": "A"}]
        }
      }
    ],
    "structure": {
      "dimensions": {
        "observation": [
          { "id": "REF_AREA", "values": [{ "id": "6104", "name": "Kab. Mempawah" }, { "id": "6102", "name": "Kab. Landak" }] },
          { "id": "INDICATOR", "values": [{ "id": "QUAL_DATA", "name": "Penjaminan Kualitas Data" }] },
          { "id": "TIME_PERIOD", "values": [{ "id": "2024" }] },
          { "id": "FREQ", "values": [{ "id": "A", "name": "Annual" }] }
        ]
      },
      "attributes": {
        "observation": [
          { "id": "OBS_STATUS", "values": [{ "id": "A", "name": "Official Value" }] }
        ]
      }
    }
  }
}
```

---

## 5. Implementasi SDMX oleh BPS di Satu Data Indonesia (SDI)

Dalam kebijakan Satu Data Indonesia (Perpres 39/2019), BPS selaku Pembina Data Statistik mendorong adopsi metadata SDMX untuk:
1.  **Standardisasi Kamus Data:** Setiap pemerintah daerah memiliki kamus istilah indikator makro dan sektoral yang seragam, menghindari definisi ganda.
2.  **Harmonisasi Lintas Instansi:** Memudahkan komparasi otomatis antara data daerah (Rapot Kabupaten) dengan target RPJMD tingkat Provinsi dan RPJMN tingkat Nasional.
3.  **Keterbukaan Publik:** Menyediakan data yang terstruktur rapi untuk didiseminasi melalui Portal Satu Data tingkat nasional (data.go.id) maupun daerah.
