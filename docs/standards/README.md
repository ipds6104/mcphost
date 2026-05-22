# 📚 Indeks Standar Pengembangan Proyek (Standards Catalogue)

Selamat datang di direktori standar pengembangan proyek. Direktori ini berfungsi sebagai **single source of truth** untuk semua kebijakan teknis, konvensi penulisan kode, arsitektur, integrasi data, aksesibilitas, orkestrasi deployment, protokol keamanan remote, dan manajemen aset multimedia yang diterapkan di seluruh proyek **MCP Host & Government Analytics Portal**.

Tujuan dari standarisasi ini adalah untuk mengurangi gesekan kognitif (*cognitive friction*), menjamin keamanan dan performa tinggi, serta memastikan sistem siap diintegrasikan dengan standar data pemerintah nasional maupun internasional, siap dihosting di ekosistem modern seperti Coolify dengan server terpisah yang aman, serta mendukung input multimedia multimodal.

---

## 🗺️ Peta Navigasi Standar (Standards Map)

Direktori ini dibagi menjadi empat pilar utama pengembangan:

### 1. Pilar Rekayasa Perangkat Lunak (Software Engineering Standards)
*   **[01. PHP & Laravel Standards](01_php_laravel_standards.md)**
    *   *Fokus:* Kepatuhan PSR-1, PSR-4, PER Coding Style (PHP-FIG), dan konvensi penamaan arsitektur *Laravel-native*.
*   **[06. Vue 3 & Inertia.js Development Standards](06_vue_inertia_standards.md)**
    *   *Fokus:* Standar *frontend* berbasis Composition API dengan `<script setup>`, tata kelola state reaktif `ref`, manajemen aliran data Inertia, dan integrasi EventSource SSE di Vue.
*   **[08. Idiomatic Development Principles & Conventions](08_idiomatic_principles.md)**
    *   *Fokus:* Praktik terbaik menulis kode secara alami (*idiomatis*) pada PHP modern, arsitektur Laravel, Vue 3, dan desain Agen AI.
*   **[11. Project Folder Structure & Multimodal Image Standards](11_project_folder_structure_multimodal_standards.md)**
    *   *Fokus:* **(Terbaru - Mei 2026)** Pola arsitektur direktori standalone Laravel + Vue 3, konfigurasi environment `.env` untuk integrasi MCP eksternal, alur penyerapan multi-image (Gemini-style), dan parsing multimodal via Laravel AI SDK.

### 2. Pilar Protokol & Komunikasi AI (AI Protocol & Communication)
*   **[02. Model Context Protocol (MCP) Standard](02_mcp_standards.md)**
    *   *Fokus:* Standar integrasi protokol MCP berbasis JSON-RPC 2.0, mekanisme transport STDIO & SSE, dan isolasi keamanan *sandbox*.
*   **[03. Data Exchange & Communication Standards](03_data_exchange_standards.md)**
    *   *Fokus:* Spesifikasi aliran data real-time W3C Server-Sent Events (SSE) dan standarisasi skema pertukaran data grafik (JSON Charting Schema) ke frontend.
*   **[10. Remote MCP & Streamable HTTP Security Standards](10_remote_mcp_security_standards.md)**
    *   *Fokus:* Standar transpor Streamable HTTP stateful, pertukaran sesi via `Mcp-Session-Id`, pengamanan remote server via OAuth 2.1, enkripsi TLS 1.3, dan validasi Origin.

### 3. Pilar Standar Data & Statistik Nasional/Global (Data & Statistical Standards)
*   **[04. Satu Data Indonesia & Standar Statistik BPS](04_satu_data_indonesia_standards.md)**
    *   *Fokus:* Kepatuhan prinsip Perpres 39/2019 (Satu Data Indonesia), integrasi Metadata Statistik (MS-Ind), pemetaan Kode Wilayah BPS vs Kemendagri, dan standar tingkat kematangan EPSS.
*   **[07. SDMX 3.1 & OECD Statistical Metadata Standards](07_sdmx_metadata_standards.md)**
    *   *Fokus:* Kepatuhan standar internasional pertukaran data statistik ISO 17369 (SDMX 3.1), struktur DSD (Dimensions, Measures, Attributes), dan pemanfaatan format SDMX-JSON.
*   **[05. Accessibility & Data Visualization Standards](05_accessibility_visualization_standards.md)**
    *   *Fokus:* Keterbacaan dan aksesibilitas visualisasi grafik berbasis standar W3C WCAG 2.2 (kontras warna 4.5:1, pola non-warna, alternatif tabel tersembunyi bagi pembaca layar).

### 4. Pilar DevOps & Infrastruktur Cloud (DevOps & Infrastructure)
*   **[09. Coolify Production Deployment Standards](09_coolify_deployment_standards.md)**
    *   *Fokus:* Pola struktur direktori `/docker`, teknik Docker Multi-stage build, konfigurasi entrypoint aman, dan topologi deployment 3-Resource terpisah (Web, Horizon, Reverb) melalui Coolify GitHub App.

---

## 🛠️ Penegakan Standar Secara Otomatis (Standards Enforcement)

Untuk memastikan seluruh pengembang mematuhi standar ini secara otomatis sebelum masuk ke repositori utama, proyek ini menerapkan perkakas kontrol kualitas pada repositori root:

1.  **PHP Code Quality (Laravel Pint):**
    Menggunakan **Laravel Pint** dengan preset `laravel` atau `psr12` (dideklarasikan di `pint.json` di root proyek) untuk memformat otomatis berkas PHP agar mematuhi *PER Coding Style*.
2.  **Frontend Quality (Prettier & ESLint):**
    Menggunakan **ESLint** dengan plugin `eslint-plugin-vue` dan **Prettier** untuk memformat otomatis kode Vue 3 dan JavaScript.
3.  **Git Hooks (Husky & lint-staged):**
    Menolak *commit* secara otomatis jika terdapat kode yang melanggar standar format atau memiliki kesalahan sintaksis yang terdeteksi oleh linter.
