# Data Exchange & Communication Standards

Dokumen ini menetapkan standar pertukaran data (*data exchange*) dan komunikasi antara Laravel backend dan Frontend client. Standar ini mencakup spesifikasi real-time streaming, struktur data terstruktur, format data visualisasi grafik, dan validasi skema data.

---

## 1. W3C Server-Sent Events (SSE) Specification

Untuk mengalirkan status proses multi-step (*thought process*, *tool call*, *tool result*, dan *text streaming*) dari Laravel ke Frontend, kita menggunakan standar **W3C Server-Sent Events (SSE)**.

### A. Protokol Header HTTP
Agar koneksi SSE berjalan lancar tanpa terputus atau tertahan di server (buffering), Laravel wajib menyertakan header berikut pada `StreamedResponse`:
*   `Content-Type: text/event-stream` $\rightarrow$ Mendefinisikan tipe konten streaming.
*   `Cache-Control: no-cache` $\rightarrow$ Mencegah proxy atau browser menyimpan cache aliran data.
*   `Connection: keep-alive` $\rightarrow$ Menjaga koneksi tetap terbuka secara persisten.
*   `X-Accel-Buffering: no` $\rightarrow$ **Krusial untuk Nginx** agar segera meneruskan chunk data tanpa ditahan dalam buffer internal.

### B. Format Pesan SSE
Setiap baris data wajib berformat `data: [JSON-String]\n\n` dan dipisahkan dengan baris kosong ganda agar EventSource API di browser dapat mengidentifikasi event secara benar.
```text
data: {"type":"thought","message":"Sedang mengambil rapot..."}

data: {"type":"tool_call","tool":"fetch_data","arguments":{}}

data: {"type":"text_delta","content":"Berikut hasilnya:"}
```

---

## 2. JSON-RPC 2.0 (Spesifikasi Eksternal)

Meskipun komunikasi internal backend-frontend menggunakan SSE, integrasi eksternal antara Laravel Client dan MCP Server harus secara ketat mematuhi spesifikasi **JSON-RPC 2.0**:
*   Harus menyertakan bidang `jsonrpc` dengan nilai `"2.0"`.
*   Semua *request* dua arah wajib menyertakan bidang `id` (integer atau string).
*   *Notification* (pesan satu arah) dilarang menyertakan bidang `id`.
*   Objek kesalahan (*error object*) wajib memiliki bidang `code`, `message`, dan opsional `data`.

---

## 3. JSON Schema Standard

Untuk mendefinisikan skema input tool AI dan struktur JSON yang valid, kita mematuhi spesifikasi **JSON Schema (Draft 7 / Draft 2020-12)**.
*   Semua parameter input wajib memiliki tipe data yang eksplisit (`string`, `number`, `integer`, `boolean`, `array`, atau `object`).
*   Daftar parameter wajib wajib dideklarasikan di dalam array `required` pada tingkat objek induk.
*   Gunakan properti `description` secara mendalam untuk membantu LLM mengerti validitas nilai masukan.
```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "type": "object",
  "properties": {
    "year": {
      "type": "integer",
      "minimum": 2020,
      "maximum": 2026,
      "description": "Tahun evaluasi kinerja rapot."
    }
  },
  "required": ["year"]
}
```

---

## 4. Standar Skema Visualisasi Data (Charting Data Schema)

Agar frontend dapat langsung merender data statistik menjadi grafik interaktif (menggunakan Chart.js, ApexCharts, atau Recharts) tanpa perlu parsing logika secara manual, Laravel wajib mengirim data visualisasi dengan format standar berikut jika mendeteksi *payload* grafik:

### Skema Struktur JSON Visualisasi:
```json
{
  "type": "chart_data",
  "chartType": "bar",
  "title": "Komparasi Nilai Rapot Pemerintah Daerah (2023-2025)",
  "labels": ["2023", "2024", "2025"],
  "datasets": [
    {
      "label": "Kabupaten A",
      "data": [72.5, 78.2, 81.0],
      "backgroundColor": "rgba(59, 130, 246, 0.5)",
      "borderColor": "rgb(59, 130, 246)"
    },
    {
      "label": "Kabupaten B",
      "data": [68.0, 74.5, 79.1],
      "backgroundColor": "rgba(16, 185, 129, 0.5)",
      "borderColor": "rgb(16, 185, 129)"
    }
  ],
  "options": {
    "responsive": true,
    "scales": {
      "y": {
        "min": 0,
        "max": 100
      }
    }
  }
}
```

### Konvensi Charting:
1.  **`chartType`**: Nilai wajib berupa enum: `["bar", "line", "pie", "radar", "doughnut"]`.
2.  **`labels`**: Array string yang mendefinisikan label sumbu X (atau kategori pada pie chart).
3.  **`datasets`**: Array objek data yang berisi nilai numerik (`data`), nama dataset (`label`), dan opsional detail warna representatif agar konsisten dengan tema UI.
4.  **`options`**: Konfigurasi opsional untuk menyesuaikan batasan sumbu, legenda, dan responsivitas.
