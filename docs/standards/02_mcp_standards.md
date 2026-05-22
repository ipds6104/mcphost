# Model Context Protocol (MCP) Standard

Dokumen ini menjelaskan standar dan spesifikasi teknis **Model Context Protocol (MCP)**, sebuah protokol terbuka yang dikembangkan oleh Anthropic untuk menghubungkan aplikasi kecerdasan buatan (AI) dengan perkakas (*tools*), sumber data (*resources*), dan panduan percakapan (*prompts*) eksternal secara aman dan terstandar.

---

## 1. Protokol Komunikasi (JSON-RPC 2.0)

Semua pertukaran pesan di dalam MCP wajib mematuhi standar spesifikasi **JSON-RPC 2.0**. Struktur pesan dibagi menjadi tiga tipe utama:

### A. Requests (Permintaan)
Pesan yang dikirim oleh Client ke Server untuk memicu sebuah aksi dan menuntut respon balik.
```json
{
  "jsonrpc": "2.0",
  "method": "tools/call",
  "params": {
    "name": "fetch_rapot_data",
    "arguments": {
      "regency_code": "32.73",
      "year": 2024
    }
  },
  "id": 1
}
```

### B. Responses (Tanggapan)
Pesan balik yang dikirim oleh Server kepada Client setelah menerima Request yang valid.
```json
{
  "jsonrpc": "2.0",
  "result": {
    "content": [
      {
        "type": "text",
        "text": "{\"regency\":\"Kota Bandung\",\"year\":2024,\"score\":85.5}"
      }
    ]
  },
  "id": 1
}
```

### C. Notifications (Pemberitahuan)
Pesan satu arah yang tidak memerlukan respon balik (misal: log atau perubahan status).
```json
{
  "jsonrpc": "2.0",
  "method": "notifications/initialized",
  "params": {}
}
```

---

## 2. Lapisan Transport (Transport Layer)

MCP mendefinisikan dua metode transport utama untuk komunikasi antara Client dan Server:

### A. STDIO (Standard Input / Output)
Digunakan ketika MCP Server berjalan sebagai proses lokal di server yang sama dengan PHP/Laravel Client.
*   **Mekanisme:** Client meluncurkan subproses server (e.g., via `proc_open` di PHP) dan mengirimkan pesan JSON-RPC melalui `stdin` dan membaca respon melalui `stdout`. Pesan dipisahkan oleh karakter baris baru (`\n`).
*   **Karakteristik:** Sangat cepat, aman secara lokal, dan ideal untuk perkakas pengembangan lokal (*local tools*).

### B. HTTP dengan Server-Sent Events (SSE)
Digunakan ketika MCP Server berjalan secara terpisah (misalnya sebagai kontainer Docker mikro, server cloud, atau microservices eksternal).
*   **Mekanisme:** Client mengirimkan request mutasi melalui endpoint HTTP POST, sementara pembaharuan real-time dari Server dikirim kembali ke Client secara asinkron menggunakan koneksi HTTP persisten berbasis Server-Sent Events (SSE).
*   **Karakteristik:** Sangat cocok untuk arsitektur terdistribusi dan *multi-container application*.

---

## 3. Struktur Konten MCP (Schemas)

MCP membagi kemampuan server menjadi tiga kategori fungsional utama yang dideklarasikan oleh server kepada client:

### A. Tools (Perkakas Aksi)
Perkakas yang dapat dipicu secara aktif oleh model AI untuk melakukan eksekusi kode, manipulasi data, atau query. Setiap tool wajib dideklarasikan bersama **JSON Schema** agar LLM dapat memahami parameter yang dibutuhkan.
*   **Schema Deklarasi:**
    ```json
    {
      "name": "fetch_rapot_data",
      "description": "Mengambil nilai rapot kinerja pemerintah daerah.",
      "inputSchema": {
        "type": "object",
        "properties": {
          "regency_code": { "type": "string", "description": "Kode wilayah kabupaten/kota" },
          "year": { "type": "integer" }
        },
        "required": ["regency_code", "year"]
      }
    }
    ```

### B. Resources (Sumber Daya Data)
Data statis atau dinamis yang disediakan oleh server untuk dibaca oleh model AI sebagai referensi tambahan (konteks).
*   **URI-Based:** Resources diidentifikasi dengan skema URI unik (e.g., `db://orders/32.73` atau `file:///docs/standard.pdf`).
*   **MIME-Types:** Mendukung transfer data berformat teks (`text/plain`, `application/json`) maupun biner (`application/pdf`, `image/png`).

### C. Prompts (Template Instruksi)
Template prompt terstruktur yang siap pakai untuk membantu pengguna menyusun instruksi berkualitas tinggi secara instan.
*   *Contoh:* Prompt `compare-regencies` yang meminta parameter nama-nama kabupaten untuk langsung diformat menjadi templat perintah komparasi strategis.

---

## 4. Model Keamanan & Isolasi (Security & Sandbox)

Dalam mengintegrasikan MCP Client ke dalam Laravel 13, aspek keamanan berikut wajib diperhatikan:

1.  **Isolasi Proses (Process Isolation):**
    Jika MCP Server dijalankan secara lokal via STDIO, pastikan subproses berjalan dengan hak akses terbatas (*least privilege*). Jangan pernah menjalankan subproses MCP dengan hak akses root atau administrator sistem.
2.  **Validasi Input (Input Sanitization):**
    Sebelum memberikan argumen dari LLM ke metode penanganan tool, Laravel harus memastikan struktur data sesuai dengan *JSON Schema* deklarasi tool tersebut untuk mencegah serangan injeksi (seperti SQL Injection atau OS Command Injection di dalam tool).
3.  **Authentication Proxy:**
    Jika mengakses remote MCP Server via HTTP/SSE, Laravel Client wajib menyertakan token otentikasi (seperti Bearer Token atau API Key khusus) untuk menjaga kerahasiaan komunikasi data lintas server.
