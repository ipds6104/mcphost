# Project Folder Structure & Multimodal Image Standards

Dokumen ini mendefinisikan standar struktur direktori untuk **Standalone Core Web Portal (Laravel 13 + Vue 3 + Inertia)** yang mengkonsumsi **Remote MCP Servers eksternal** (dikembangkan di repositori terpisah), serta panduan implementasi fitur unggahan **Multiple Images (Multimodal)** sekelas Google Gemini.

---

## 1. Desain Struktur Folder: Standar Standalone Web Portal

Karena Remote MCP Server dikembangkan secara independen di repositori GitHub terpisah, repositori utama kita sepenuhnya didedikasikan untuk **Core Web Portal**. Hal ini menyederhanakan ukuran repositori, mempercepat proses *build* di Coolify, dan menjaga keamanan kode internal web portal kita.

Berikut adalah rancangan struktur folder *Clean Standalone Web Portal* kita:

```
mcphost/ (Repository Root - Standalone Web Portal)
├── app/                            # Logika Backend PHP (Laravel 13)
│   ├── Http/
│   │   ├── Controllers/            # Chat & Streaming SSE Controllers
│   │   └── Requests/               # Validasi Form Input & Berkas Gambar
│   ├── Jobs/                       # Background Jobs (AI Agentic Multi-step Process)
│   ├── Models/                     # Chat, Message, User, Regency
│   ├── Providers/                  # Service Providers (MCP Remote Client Registry)
│   └── Services/                   # Integrasi Jaringan ke Remote MCP (OAuth 2.1 Handshake)
│
├── config/                         # Konfigurasi Laravel (Horizon, Reverb, AI SDK)
├── database/                       # Migrasi Tabel Chat & Data Indikator Daerah
├── resources/                      # Vue 3 Frontend & Aset Awal
│   ├── js/
│   │   ├── Components/             # Balon Chat Gemini, Accordion Progress, Upload Previews
│   │   ├── Pages/                  # Halaman Obrolan Utama, Dashboard Analitis
│   │   └── app.js                  # Inisialisasi Inertia & Vue
│
├── public/                         # Kompilasi aset frontend hasil build
├── docker/                         # Konfigurasi deployment Coolify khusus Web Portal
│   ├── Dockerfile                  # Multi-stage production build (Node -> PHP-FPM)
│   └── entrypoint.sh               # Inisialisasi migrasi DB
│
├── docs/                           # Pusat Dokumentasi Global
│   └── standards/                  # 11 Dokumen Standar Rekayasa Proyek
│
├── pint.json                       # Konfigurasi linter PHP global (PER/PSR-12)
├── composer.json                   # Dependensi PHP (Laravel 13, Laravel AI SDK)
├── package.json                    # Dependensi Frontend (Vue 3, Inertia, TailwindCSS, Chart.js)
├── vite.config.js                  # Bundler aset frontend
└── README.md                       # Dokumentasi Induk Proyek (Main Developer Guide)
```

---

## 2. Aliran Integrasi Remote MCP & Kredensial `.env`

Laravel bertindak sebagai client yang memanggil Remote MCP Server eksternal yang dihosting di server terpisah. Koneksi ini sepenuhnya diatur melalui konfigurasi environment di file `.env`:

```env
# Kredensial Koneksi Remote MCP Server (Sains Data & PDF)
MCP_DATA_SERVER_URL=https://mcp-data.bps.go.id
MCP_DATA_CLIENT_ID=client_laravel_portal
MCP_DATA_CLIENT_SECRET=secure_oauth_secret_key_123

# Kredensial Koneksi Remote MCP Server (API Gateway Wilayah)
MCP_API_SERVER_URL=https://mcp-api.bps.go.id
MCP_API_CLIENT_ID=client_laravel_portal
MCP_API_CLIENT_SECRET=secure_oauth_secret_key_456
```

Laravel akan menggunakan kredensial ini untuk melakukan jabat tangan (*handshake*) **OAuth 2.1** untuk memperoleh JWT Access Token, kemudian membuat koneksi stateful menggunakan transpor **Streamable HTTP** dengan menyematkan header **`Mcp-Session-Id`** pada setiap request eksekusi *tool*.

---

## 3. Aliran Proses Unggahan Multiple Gambar (Multimodal)

Untuk mengimplementasikan kemampuan input multimedia (unggah banyak gambar) layaknya Gemini, alur kerja didistribusikan secara efisien antara lapisan web portal dan API LLM:

```
┌──────────────────┐               ┌──────────────────┐               ┌──────────────────┐
│ 1. VUE 3 UI      ├──────────────►│ 2. LARAVEL API   ├──────────────►│ 3. LLM API       │
│  - Select Images │ (Multipart)   │  - Store Disk    │ (Payload)     │  - Gemini /      │
│  - Show Previews │               │  - Save DB URL   │               │    Claude        │
└──────────────────┘               └──────────────────┘               └──────────────────┘
```

### A. Frontend (Vue 3 + TailwindCSS)
*   **Media Preview Thumbnails:** Di dalam bilah input obrolan, pengguna dapat menempelkan (*paste* dari clipboard) atau mengunggah banyak gambar. Gambar langsung ditampilkan sebagai kartu pratinjau kecil dengan tombol hapus (tanda silang merah halus).
*   **Multipart Upload:** Data formulir dikirim menggunakan `FormData` agar mendukung teks prompt dan lampiran berkas gambar secara bersamaan melalui Inertia Request.

### B. Backend Storage (Laravel 13)
*   **Secure File Ingestion:** Laravel memvalidasi gambar (tipe berkas, ukuran maks 5MB) dan menyimpannya di direktori penyimpanan aman (`storage/app/public/chats`).
*   **Database Schema:** Pesan obrolan (`messages`) menyimpan array JSON yang berisi path URL gambar:
    ```json
    {
        "text": "Bandingkan grafik indeks kinerja dari dua screenshot rapot daerah berikut",
        "attachments": [
            "storage/chats/img_123.png",
            "storage/chats/img_124.jpeg"
        ]
    }
    ```

### C. AI Agent Ingestion (Laravel AI SDK)
Saat mengirim permintaan ke LLM (seperti Gemini 2.0 Flash yang memiliki visi spasial luar biasa), Laravel AI SDK akan merangkai pesan menjadi multi-part konten:

```php
use Laravel\Ai\Message;
use Laravel\Ai\Parts\TextPart;
use Laravel\Ai\Parts\MediaPart;

// Rangkai payload pesan multimodal
$messageParts = [
    new TextPart($message->text)
];

foreach ($message->attachments as $attachmentPath) {
    // Ambil mime type dan base64 data gambar
    $fullPath = storage_path('app/public/' . $attachmentPath);
    $mimeType = mime_content_type($fullPath);
    $base64Data = base64_encode(file_get_contents($fullPath));

    $messageParts[] = new MediaPart(
        data: $base64Data,
        mimeType: $mimeType
    );
}

// Kirim ke LLM Gemini untuk dianalisis secara visual
$response = Ai::chat()
    ->model('gemini-2.0-flash')
    ->send(new Message($messageParts));
```
