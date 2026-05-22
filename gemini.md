# 🚀 Panduan Kepatuhan Standar Pengembangan (gemini.md)

Sebagai asisten pengembang AI (Antigravity), berkas ini adalah **memori taktis** dan **petunjuk kepatuhan** untuk mengingat standar arsitektur dan pola pengembangan sistem **MCP Host & Government Analytics Portal** berdasarkan `docs/standards/README.md`.

Setiap pengembangan lanjutan wajib mematuhi aturan ketat di bawah ini tanpa kompromi.

---

## 🗺️ Ringkasan 4 Pilar Utama Pengembangan

### 1. Pilar Rekayasa Perangkat Lunak (Software Engineering)
*   **PHP & Laravel Standards ([01](docs/standards/01_php_laravel_standards.md) & [08](docs/standards/08_idiomatic_principles.md)):**
    *   Wajib patuh pada PER Coding Style (PHP-FIG) dan format otomatis Laravel Pint.
    *   Penggunaan Laravel 13 yang idiomatis: hindari kueri mentah (*raw queries*), manfaatkan type-hinting, strict types, and custom helper classes.
    *   **Identitas Bertipe UUID:** Semua model database sensitif (seperti `chats`, `messages`, `agent_conversations`) menggunakan UUID v4 sebagai Primary Key, bukan integer berurutan, demi keamanan ID dan pencegahan enumerasi.
*   **Vue 3 & Inertia.js Standards ([06](docs/standards/06_vue_inertia_standards.md)):**
    *   Wajib menggunakan Composition API dengan sintaks `<script setup>`.
    *   Gunakan `ref()` secara konsisten untuk state reaktif.
    *   Batasi ukuran satu file Vue maksimal **300–400 baris**. Pecah ke komponen modular baru (`Components/`) jika potongan UI kompleks atau digunakan berulang.
    *   Gunakan **VueUse** (`@vueuse/core`) untuk click-outside (`onClickOutside`), auto-sync local storage (`useLocalStorage`), dan debouncing (`refDebounced`) agar kode tidak cepat membengkak (*bloating*).
    *   Gunakan **Pinia** untuk global state management terpusat (seperti tema, preferensi sidebar, auth session).
*   **Project Structure & Multimodal Standard ([11](docs/standards/11_project_folder_structure_multimodal_standards.md)):**
    *   Pemisahan folder yang bersih. Gambar dan lampiran diunggah di bawah disk publik, lalu diparsing via First-Party Laravel AI SDK.

### 2. Pilar Protokol & Komunikasi AI (AI & Communication)
*   **Model Context Protocol (MCP) ([02](docs/standards/02_mcp_standards.md) & [10](docs/standards/10_remote_mcp_security_standards.md)):**
    *   Pertukaran data berbasis spesifikasi JSON-RPC 2.0.
    *   Mekanisme streaming respon real-time via Server-Sent Events (SSE) dengan browser `EventSource API`.
    *   Pertukaran sesi stateful yang aman menggunakan parameter header `Mcp-Session-Id`.
    *   **Proxy Gateway Tanpa Hardcode:** Menggunakan proxy gateway AI hosting sendiri (`ai.dvlpid.my.id`). Base URL dikonfigurasi melalui `.env` (`OPENAI_URL`) secara dinamis, lalu diakses melalui config `config/ai.php`.

### 3. Pilar Standar Data Nasional (Satu Data Indonesia & BPS)
*   **Satu Data Indonesia & EPSS ([04](docs/standards/04_satu_data_indonesia_standards.md) & [07](docs/standards/07_sdmx_metadata_standards.md)):**
    *   Kepatuhan Metadata Statistik (MS-Ind) sesuai Perpres 39/2019.
    *   Pemetaan standar kode administrasi wilayah BPS.
    *   Penggunaan standar pertukaran data statistik global **SDMX 3.1 (ISO 17369)** berbasis format JSON.
*   **Visualisasi & Aksesibilitas ([05](docs/standards/05_accessibility_visualization_standards.md)):**
    *   Visualisasi grafik/diagram (JSON-Chart) wajib memenuhi tingkat kontras **WCAG 2.2 AA (minimal 4.5:1)**.
    *   Sediakan alternatif deskripsi visual / tabel tersembunyi bagi pengguna *screen reader*.

### 4. Pilar DevOps & Infrastruktur Cloud
*   **Coolify Production Deployment ([09](docs/standards/09_coolify_deployment_standards.md)):**
    *   Teknik Docker Multi-stage build di `/docker`.
    *   Topologi deployment 3-Resource terpisah (Web Server, Horizon Queue, dan Reverb WebSockets) melalui ekosistem Coolify GitHub App.
    *   Konfigurasi Hot Module Replacement (HMR) dan filesystem watcher polling dalam Docker local dev via `vite.config.js` untuk menjamin stabilitas hot-reload instan.

---

## 📝 Catatan Penting untuk Pengembangan Selanjutnya

1.  **Strict UUID Constraint:** Selalu gunakan `$table->uuid('id')->primary();` dalam migrasi baru untuk entitas model utama.
2.  **No AI Hardcoding:** Gunakan facade `Laravel\Ai\Ai::provider(...)` secara dinamis, mengandalkan environment variable `.env`.
3.  **Vite Hot-Reload Polling:** Jika HMR terasa lambat atau putus, pastikan setelan polling server (`usePolling: true`) aktif di `vite.config.js`.
4.  **Vue Component Discipline:** Jangan biarkan file `Show.vue` atau halaman chat utama membengkak lagi. Selalu lakukan ekstraksi sub-komponen baru (e.g., `ChatSidebar.vue`, `ChatInput.vue`, `ThinkingSteps.vue`) untuk menjaga kebersihan basis kode.

---

*Terakhir diperbarui: Mei 2026 oleh Antigravity AI*
