# Remote MCP & Streamable HTTP Security Standards

Dokumen ini mendefinisikan standar keamanan, arsitektur transport, dan protokol otentikasi terbaru (berlaku per **Mei 2026**) untuk mengintegrasikan **Remote MCP Servers** (yang didevelop dan dihosting di server terpisah) dengan **Laravel 13 Client**.

---

## 1. Evolusi Transport: Migrasi ke Streamable HTTP (Standar Terbaru 2026)

Komunikasi antara Laravel Client dan Remote MCP Server tidak lagi menggunakan transport STDIO lokal, dan transport HTTP+SSE lawas telah didepresiasi. Kita secara penuh menerapkan standar **Streamable HTTP**:

*   **Stateful Session Management:** Komunikasi diperlakukan sebagai sesi HTTP stateful yang aman. Client wajib mengirimkan header **`Mcp-Session-Id`** (menggunakan format UUIDv4 yang acak dan non-deterministik) di setiap request untuk menjaga integritas sesi.
*   **WAF & Load Balancer Friendly:** Arsitektur Streamable HTTP dirancang agar kompatibel dengan infrastruktur keamanan modern (seperti Cloudflare, Web Application Firewalls (WAF), dan Load Balancer) yang biasanya memblokir atau memutus koneksi SSE mentah yang menggantung lama.
*   **Enkripsi Transit Mandatori (TLS):** Semua komunikasi wajib berjalan di atas protokol **HTTPS (TLS 1.3)** dengan sertifikat SSL yang valid untuk mencegah serangan *Man-in-the-Middle* (MitM).

---

## 2. Protokol Otentikasi & Otorisasi (OAuth 2.1)

Karena Remote MCP Server dihosting di server eksternal terpisah yang menyimpan data strategis negara/daerah, akses tidak boleh dibbiarkan terbuka. Otentikasi ketat wajib diterapkan:

```
┌─────────────────┐    1. Request Token (Client Credentials)    ┌──────────────────────────┐
│   LARAVEL 13    ├─────────────────────────────────────────────►   REMOTE MCP SERVER      │
│  (MCP Client)   ◄─────────────────────────────────────────────┤     (OAuth 2.1)          │
│                 │          2. Access Token (JWT)              │                          │
│                 ├─────────────────────────────────────────────►                          │
│                 │   3. Send Streamable HTTP + Mcp-Session-Id  │                          │
└─────────────────┘                                             └──────────────────────────┘
```

1.  **OAuth 2.1 Client Credentials Flow:**
    Laravel 13 bertindak sebagai *Confidential Client*. Sebelum melakukan *handshake* MCP, Laravel wajib meminta token otentikasi menggunakan metode *Client Credentials* dengan *Client ID* dan *Client Secret* yang disimpan di `.env` secara aman.
2.  **Scope-Based Authorization:**
    Token JWT yang diterbitkan harus memiliki *scopes* terbatas (e.g., `scopes: ["tools:read", "tools:execute:fetch_rapot"]`). Remote MCP Server akan menolak eksekusi jika token tidak memiliki otorisasi scope yang sesuai.
3.  **Origin & DNS Rebinding Protection:**
    Remote MCP Server wajib memvalidasi header `Origin` dan menerapkan *domain whitelist* yang ketat, hanya memperbolehkan koneksi masuk yang berasal dari alamat IP/domain server Laravel 13 Anda.

---

## 3. Pembagian Peran Tech Stack (Decoupled Infrastructure)

Dengan memindahkan MCP Server ke server terpisah, peran masing-masing komponen di dalam arsitektur kita menjadi sangat optimal:

### A. Server Web Utama (Laravel 13 + Vue 3 di Coolify)
*   **Peran:** Bertindak sebagai **Gateway Keamanan Utama** bagi pengguna akhir. User tidak pernah berkomunikasi langsung dengan Remote MCP Server.
*   **Tugas:** 
    *   Menerima chat dari user, menyimpannya di DB, lalu menaruh pekerjaan di Redis queue.
    *   Mengamankan kredensial OAuth 2.1 (Client Secret) agar tidak bocor ke sisi browser/frontend.
    *   Mengirimkan instruksi pemicu (*trigger*) ke Remote MCP Server dan menyiarkan (*broadcast*) status progres secara real-time via Laravel Reverb ke Vue 3 UI.

### B. Server Analitik Terpisah (Python / Node.js MCP Server)
*   **Peran:** Bertindak sebagai **Mesin Data Terisolasi** (Isolated Analytics Engine).
*   **Tugas:**
    *   Melakukan kalkulasi statistik yang berat, parsing data rapot multi-wilayah dari file PDF, dan kueri database internal regional.
    *   Menerapkan *Input Sanitization* yang sangat ketat pada argumen *tool* yang dikirim oleh LLM guna mencegah serangan *Prompt Injection* (seperti meloloskan kueri berbahaya untuk meretas database regional).
    *   Mengembalikan data bersih berformat JSON terstruktur kepada Laravel.

---

## 4. Keuntungan Infrastruktur Terpisah (Remote MCP)

1.  **Keamanan Sandboxing Maksimal:**
    Jika terjadi eksploitasi keamanan di server web publik Laravel, peretas **tidak dapat** mengakses dokumen rapot mentah atau database rapot internal secara langsung karena mereka terhalang oleh otentikasi OAuth 2.1 dan TLS di Remote MCP Server.
2.  **Optimasi Biaya & Resource VPS:**
    *   *Server Laravel:* Menggunakan VPS standar berbiaya murah yang dioptimalkan untuk kecepatan koneksi HTTP/WebSocket (I/O optimized).
    *   *Server MCP Python:* Menggunakan VPS terpisah dengan CPU tinggi atau RAM besar (Compute optimized) yang hanya menyala atau aktif saat melakukan kalkulasi data berat.
3.  **Kemudahan Kolaborasi Tim Dev:**
    Remote MCP Server dapat dikembangkan oleh tim data statistik BPS menggunakan lingkungan kerja mandiri, dan cukup mempublikasikan dokumentasi endpoint API MCP mereka agar langsung bisa dikonsumsi oleh Laravel.
