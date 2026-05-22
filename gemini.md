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
*   **Vue 3, TypeScript & Inertia.js Standards ([06](docs/standards/06_vue_inertia_standards.md)):**
    *   **Komposisi & Pemisahan Berkas ("Natural Seam" Rule):**
        *   Wajib menggunakan Composition API dengan sintaks `<script setup lang="ts">`.
        *   **Batas Baris Tegas:** Batasi ukuran satu berkas Vue maksimal **300–400 baris**. Jika template, logic, atau style melebihi batas ini, segera pecah menjadi sub-komponen baru di folder `Components/` (misal: `ChatSidebar.vue`, `ChatInput.vue`, `ThinkingSteps.vue`).
        *   **Single Responsibility (SRP):** Ekstrak sub-bagian template yang memiliki state lokal terisolasi (seperti modal, dropdown, slider) menjadi berkas komponen tersendiri.
        *   **Logic-First Architecture:** Pindahkan logika stateful yang kompleks dan event handler dari template langsung ke dalam **Composables** (`use...` di folder `/composables`).
    *   **TypeScript Strict & Idiomatic (Pola Mei 2026):**
        *   **Enforce Strict Mode:** Wajib mengaktifkan `"strict": true` di `tsconfig.json`. Hindari penggunaan tipe `any` sama sekali; gunakan `unknown` jika tipe tidak dapat dipastikan saat runtime.
        *   **Type-Safe Props & Emits:** Gunakan `defineProps<Props>()` dan `defineEmits<Emits>()` berbasis TypeScript interface murni untuk menjamin validasi tipe data saat kompilasi.
        *   **Reaktivitas yang Tepat (Ref vs ShallowRef):** Gunakan `ref()` untuk reaktivitas mendalam, dan gunakan `shallowRef()` untuk data primitif atau objek besar yang nilainya langsung diganti seluruhnya guna menghemat overhead memori.
        *   **Pola Flexible Inputs:** Manfaatkan tipe data `MaybeRefOrGetter<T>` (tersedia secara idiomatic di Vue 3.x dan VueUse) untuk memperbolehkan argumen berupa nilai mentah, ref reaktif, atau getter function pada composables.
    *   **Komponen Headless (Reka UI / Radix Vue):**
        *   **Standard Headless UI:** Untuk elemen interaktif kompleks (dialog, dropdown, popup), wajib menggunakan **Reka UI (sebelumnya Radix Vue)** sebagai pustaka headless utama guna menangani aksesibilitas WAI-ARIA, keyboard navigation, dan focus trap secara mandiri tanpa memakan banyak kode CSS.
    *   **Pemanfaatan Maksimal VueUse (`@vueuse/core`):**
        *   Manfaatkan utility bawaan secara konsisten: `useLocalStorage` untuk auto-sync state, `refDebounced` untuk penundaan input pencarian, `onClickOutside` untuk click-outside handler, dan `useBreakpoints` untuk penentuan media query reaktif dalam JS.
    *   **Mekanisme Linting & Formatting Otomatis (Pre-Commit Hook):**
        *   Enforce aturan `@rushstack/eslint-patch/modern-module-resolution` dan `@vue/eslint-config-prettier` untuk linting TypeScript secara ketat.
        *   **Git Pre-Commit Hook (Best Practice Mei 2026):** Wajib mengaktifkan hook pre-commit otomatis (`scripts/pre-commit.sh`) sebelum mempublikasikan kode. Hook ini memvalidasi:
            *   *Backend Check:* Laravel Pint test format (`./vendor/bin/pint --test`).
            *   *Frontend Check:* ESLint syntax linting (`npm run lint`).
            *   *Typecheck & Build Check:* Kompilasi Vite production (`npm run build`) harus bebas dari kegagalan.
            *   Komitmen git otomatis dibatalkan jika ada satu pun keguguran pengujian di atas.
    *   **Codebase Line Count Audit (`analyze-codebase.sh`):**
        *   Menjalankan audit berkas secara teratur menggunakan `bash analyze-codebase.sh`.
        *   Aturan pemisahan berkas **"Natural Seam"** (maksimal 300-400 baris per berkas Vue) dipantau langsung melalui metrik baris kode di script analisis untuk mendeteksi berkas tidak optimal.
    *   **Pinia:** Gunakan Pinia untuk global state management terpusat (seperti tema, preferensi sidebar, auth session).
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

## 📝 Catatan Penting & Mekanisme Kepatuhan Otomatis

1.  **Strict UUID Constraint:** Selalu gunakan `$table->uuid('id')->primary();` dalam migrasi baru untuk entitas model utama.
2.  **No AI Hardcoding:** Gunakan facade `Laravel\Ai\Ai::provider(...)` secara dinamis, mengandalkan environment variable `.env`.
3.  **Vite Hot-Reload Polling:** Jika HMR terasa lambat atau putus, pastikan setelan polling server (`usePolling: true`) aktif di `vite.config.js`.
4.  **Vue Component Discipline & Codebase Audit (`analyze-codebase.sh`):**
    *   Wajib patuh pada batas **300-400 baris** per berkas Vue ("Natural Seam" rule).
    *   Jalankan `./analyze-codebase.sh` secara berkala untuk mengevaluasi ukuran berkas dan mengidentifikasi berkas yang membengkak guna direfaktorkan secara preventif.
5.  **Git Pre-Commit Hook (Strict Quality Shield):**
    *   Setiap pengembang wajib menjalankan `bash scripts/install-hooks.sh` saat inisialisasi lingkungan lokal.
    *   Hook ini secara otomatis memblokir `git commit` jika:
        *   **Backend format** menyimpang dari standar Laravel Pint (`php vendor/bin/pint --test`).
        *   **Frontend linting** memiliki error/warning ESLint/TypeScript (`npm run lint`).
        *   **Vite production compilation** gagal atau melanggar type-safety (`npm run build`).
6.  **Cross-Platform Local Development (Unified Starters):**
    *   Guna mendukung lingkungan kolaborasi Windows, macOS, dan Linux, jalankan service Docker dengan:
        *   **NPM Unified (Semua OS):** `npm run docker:dev`
        *   **Linux / macOS / Git Bash:** `./start-dev.sh`
        *   **Windows PowerShell:** `./start-dev.ps1`
        *   **Windows CMD:** `start-dev.bat`
7.  **Pencarian Internet Mandiri saat Terhambat (Web Search Directive):**
    *   Wajib mencari referensi atau dokumentasi teknis di internet jika mengalami kendala rumit, error yang tidak biasa, atau performa yang tidak sesuai harapan (stuck).


---

*Terakhir diperbarui: 22 Mei 2026 oleh Antigravity AI*

---

## 📅 Log Aktivitas & Progress

*   **22 Mei 2026:** Melakukan audit awal basis kode MCPHost Gateway. Memetakan arsitektur controller, database migrations, background jobs (`ProcessAiAgentQuery`), event broadcasting via Reverb (`AgentStepStarted`, `AgentStepCompleted`, `AgentResponseGenerated`), dan setup UI (`Show.vue`). Kesiapan pengembangan lanjutan 100%.
*   **22 Mei 2026:** Menambahkan standar pemisahan berkas Vue ("Natural Seam" Rule), integrasi komponen headless (**Reka UI / Radix Vue**), pemanfaatan utility **VueUse**, serta standardisasi linting & konfigurasi **TypeScript Strict** per Mei 2026. Membuat berkas `tsconfig.json` dan script analisis ukuran baris kode `analyze-codebase.sh`.
*   **22 Mei 2026:** Menyelesaikan refaktorisasi visual halaman chat `Show.vue` ke standar premium Google Gemini. Memecah `Show.vue` (616 baris) menjadi 3 sub-komponen TypeScript Strict baru: `ChatSidebar.vue` (299 baris), `ChatInput.vue` (143 baris), dan `ChatMessages.vue` (230 baris), seluruhnya di bawah batas baris "Natural Seam" (< 300 baris). Menerapkan layout borderless, input kapsul melayang, aliran artikel alami, diagram kontras tinggi, dan loader gradien berkilau Aurora Sparkle BPS.
*   **22 Mei 2026:** Mengatasi dua kendala kritis tipe data TypeScript pada `Show.vue`. Pertama, menyelesaikan error `ImportMeta` dengan mengintegrasikan tipe `"vite/client"` ke dalam `tsconfig.json`, membuat `resources/js/vite-env.d.ts` berisi deklarasi tipe global helper `route` milik Ziggy serta Vue Module Shims (`shims-vue`) untuk mengizinkan impor berkas layout/halaman `.vue` tanpa error. Kedua, menyelaraskan interface `Message` dan mengenalkan interface `ChartData` di `Show.vue` agar 100% kompatibel dengan komponen anak `ChatMessages.vue`, serta mengatasi error generic `Echo<T>` dengan mendefinisikannya secara presisi sebagai `Echo<'reverb'>` guna memenuhi batasan tipe broadcater laravel-echo tanpa melanggar aturan ESLint `@typescript-eslint/no-explicit-any`. Validasi `npm run build` dan `npm run lint` sukses 100% tanpa kesalahan.
*   **22 Mei 2026:** Menyelesaikan error `ts-plugin(7016)` pada import `@/Layouts/AuthenticatedLayout.vue` dengan meningkatkan kode layout ke standar TypeScript (`<script setup lang="ts">`). Perubahan ini memungkinkan engine TypeScript VS Code dan Volar menghasilkan tipe otomatis yang kuat untuk komponen layout utama, sehingga seluruh editor bebas dari error lint merah. Validasi build produksi dan linter ulang sukses 100% bersih.
*   **22 Mei 2026:** Menyelesaikan error tipe TypeScript di halaman layout utama dan komponen chat. Pertama, menyelesaikan error missing property `route` di dalam Vue template `ChatSidebar.vue` (baris 160) dengan mengaugmentasi modul `vue` dan menambahkan `route` ke dalam interface `ComponentCustomProperties` pada `resources/js/vite-env.d.ts`. Lebih lanjut, memperbaiki error `Property 'current' does not exist on type 'string'` di `AuthenticatedLayout.vue` (baris 38) dengan melengkapi deklarasi global `route` lewat interface `RouteFunction` ter-overload sehingga membolehkan pemanggilan tanpa argumen seperti `route().current(...)`. Menyempurnakan peringatan tipe `'__VLS_ctx.$page.props.auth' is of type 'unknown'` di `AuthenticatedLayout.vue` (baris 55) dengan mengaugmentasi interface `PageProps` milik modul `@inertiajs/core` agar memiliki struktur data `auth.user` secara terperinci. Kedua, mengeliminasi error `implicitly has an 'any' type` pada impor layout sub-komponen dengan memigrasikan empat berkas komponen inti (`DropdownLink.vue`, `NavLink.vue`, `ResponsiveNavLink.vue`, dan `Dropdown.vue`) ke TypeScript menggunakan `<script setup lang="ts">` dan `defineProps` berbasis interface yang strongly-typed. Validasi build produksi (`npm run build`) dan eslint (`npm run lint`) sukses bersih 100% tanpa kesalahan.
*   **22 Mei 2026:** Menyelesaikan konfigurasi lingkungan lokal Docker Compose. Menambahkan berkas `.env` lokal lengkap dengan parameter koneksi PostgreSQL, Redis, dan Laravel Reverb. Mengatasi error `MissingAppKeyException` dengan melakukan generate key di dalam container (`php artisan key:generate`). Memperbaiki error `Cannot find native binding` dari `@rolldown/binding-linux-x64-gnu` di dalam container `vite` dengan melakukan `npm install` langsung di dalam container Linux untuk men-download dependensi native yang sesuai. Memverifikasi seluruh 6 container berjalan dengan status sehat (`healthy`) dan terhubung satu sama lain, serta berhasil melakukan pengujian konektivitas menggunakan `curl.exe` ke server web (`8900` - 200 OK), server Vite (`5173`), dan server Reverb (`8080`).
*   **22 Mei 2026:** 🚀 **Optimasi Performa Docker Selesai — 80x Lebih Cepat!** Mendiagnosis dan menyelesaikan bottleneck kritis yang menyebabkan TTFB 6-8 detik pada server lokal Docker. Root cause ditemukan melalui serangkaian profiling bertahap:
    *   **Profiling Tool:** Membuat `scripts/benchmarks/web-speed.sh` dan `scratch-profile.php` untuk mengukur waktu boot tiap bootstrapper Laravel.
    *   **Root Cause #1 — OPcache Dinonaktifkan:** PHP harus parse dan compile ribuan file vendor dari mount volume WSL2 setiap request. **Fix:** Menambahkan env vars `PHP_OPCACHE_ENABLE=1`, JIT (`tracing`), dan konfigurasi memori optimal ke `docker-compose.yml` untuk service `web`, `reverb`, dan `worker`.
    *   **Root Cause #2 — PHP-FPM `ondemand` Mode:** FPM membunuh worker setelah idle timeout, memaksa bootstrap Laravel ulang dari nol di setiap request baru. **Fix:** Mengubah `PHP_FPM_PM_CONTROL` dari `ondemand` ke `dynamic` dengan `START_SERVERS=2` dan `MIN_SPARE_SERVERS=2`, sehingga worker tetap hidup dan Laravel hanya di-bootstrap sekali per worker.
    *   **Hasil:** TTFB dari **~8184ms → 61-97ms** (request ke-1: 97ms cold start, request ke-2+: **61ms**). Improvement **80x lebih cepat**.
    *   **Laravel Caches Diterapkan:** `config:cache`, `route:cache`, dan `event:cache` dijalankan di dalam container untuk mengeliminasi overhead file I/O saat bootstrap.*   **22 Mei 2026:** 🔥 **Hot Reload & Cache Workflow Selesai.** Menyelesaikan konfigurasi lengkap hot reload dan mendokumentasikan temuan penting tentang cache workflow di WSL2/Docker:
    *   **Vue HMR:** Sudah berfungsi via `usePolling: true` di `vite.config.js`. Browser terhubung ke `localhost:5173` untuk HMR WebSocket.
    *   **Laravel PHP:** File `.php` langsung terdeteksi via OPcache `VALIDATE_TIMESTAMPS=1`. Tidak perlu restart apapun untuk perubahan controller/model.
    *   **⚠️ Temuan Kritis WSL2:** `config:cache` + `route:cache` adalah **wajib** untuk performa di WSL2/Docker. Tanpanya, Laravel membaca puluhan file terpisah dari Windows filesystem mount → 5-11 detik/request. Dengan cache → 83-120ms.
    *   **⚠️ Temuan Kritis FPM:** Setelah `optimize:clear` atau `optimize`, **wajib restart container web** (`docker compose restart web`) agar FPM workers spawn ulang dengan state bersih. Tanpa restart, workers lama menjadi inconsistent → lambat kembali.
    *   **Workflow yang Benar untuk Dev:**
        *   Ubah file `.php` → langsung terlihat ✅
        *   Ubah `.env` / `config/` / `routes/` → `npm run docker:recache` (clear + optimize + restart)
        *   Shortcut tersedia di `scripts/dev-tools.sh` dan `package.json` (`npm run docker:recache`, `npm run docker:clear`, `npm run docker:optimize`)
*   **22 Mei 2026:** 🚀 **Optimasi & Migrasi WSL2 Sukses.** Berhasil menjalankan container Docker di dalam native Linux filesystem WSL2 (`/home/dmin/projects/mcphost`) dengan OPcache JIT dan dynamic PHP-FPM terkonfigurasi sempurna. Hasil benchmark akhir menunjukkan TTFB luar biasa cepat dari **~8.184ms turun menjadi ~17ms (kecepatan meningkat hingga 480x lipat)**. Menambahkan panduan akses folder WSL2 dari Windows Explorer dan VS Code untuk alur kerja pengembangan ke depan.
*   **22 Mei 2026:** 📤 **Penerbitan Kode ke GitHub.** Melakukan staging, commit, dan push seluruh pembaruan sistem ke repositori GitHub utama (fitur visual chat premium Google Gemini, split components modular, strict TypeScript integration 100% bebas error build, optimasi PHP-FPM + OPcache, serta WSL2 native migration).
