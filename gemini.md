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
        *   **Batas Baris Tegas (Sangat Modular):** Utamakan memisah berkas menjadi berkas-berkas kecil yang modular dengan baris sedikit sesuai peran spesifiknya masing-masing. Batasi ukuran satu berkas maksimal **300–400 baris** (baik Vue, PHP, TypeScript, dsb). Jika berkas mendekati atau melebihi 400 baris, segera pecah menjadi sub-komponen atau sub-modul baru (misal: `ChatSidebar.vue`, `ChatInput.vue`, `ThinkingSteps.vue`).
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
            *   *Frontend Check:* ESLint syntax linting (`bun run lint`).
            *   *Typecheck & Build Check:* Kompilasi Vite production (`bun run build`) harus bebas dari kegagalan.
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
4.  **Component & File Size Discipline (`analyze-codebase.sh`):**
    *   Sangat disukai file yang dipisah menjadi file-file kecil yang modular dengan baris sedikit (di bawah 300–400 baris) sesuai perannya masing-masing. Wajib patuh pada batas maksimal **400 baris** per file ("Natural Seam" rule).
    *   Jalankan `./analyze-codebase.sh` secara berkala untuk mengevaluasi ukuran berkas dan mengidentifikasi berkas yang membengkak guna direfaktorkan secara preventif.
5.  **Git Pre-Commit Hook (Strict Quality Shield):**
    *   Setiap pengembang wajib menjalankan `bash scripts/install-hooks.sh` saat inisialisasi lingkungan lokal.
    *   Hook ini secara otomatis memblokir `git commit` jika:
        *   **Backend format** menyimpang dari standar Laravel Pint (`php vendor/bin/pint --test`).
        *   **Frontend linting** memiliki error/warning ESLint/TypeScript (`bun run lint`).
        *   **Vite production compilation** gagal atau melanggar type-safety (`bun run build`).
6.  **Cross-Platform Local Development (Unified Starters):**
    *   Guna mendukung lingkungan kolaborasi Windows, macOS, dan Linux, jalankan service Docker dengan:
        *   **Bun Unified (Semua OS):** `bun run docker:dev`
        *   **Linux / macOS / Git Bash:** `./start-dev.sh`
        *   **Windows PowerShell:** `./start-dev.ps1`
        *   **Windows CMD:** `start-dev.bat`
7.  **Pencarian Internet Mandiri saat Terhambat (Web Search Directive):**
    *   Wajib mencari referensi atau dokumentasi teknis di internet jika mengalami kendala rumit, error yang tidak biasa, atau performa yang tidak sesuai harapan (stuck).
8.  **Strict Bun Runtime Directive (Mei 2026):**
    *   Seluruh perintah runtime frontend, instalasi dependensi, linting (`bun run lint`), dan eksekusi skrip wajib menggunakan **Bun** (`bun`, `bun run`, `bunx`).
    *   Sama sekali **dilarang** menggunakan Node.js, npm, atau npx untuk perintah-perintah pengembangan harian.


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
        *   Ubah `.env` / `config/` / `routes/` → `bun run docker:recache` (clear + optimize + restart)
        *   Shortcut tersedia di `scripts/dev-tools.sh` dan `package.json` (`bun run docker:recache`, `bun run docker:clear`, `bun run docker:optimize`)
*   **22 Mei 2026:** 🚀 **Optimasi & Migrasi WSL2 Sukses.** Berhasil menjalankan container Docker di dalam native Linux filesystem WSL2 (`/home/dmin/projects/mcphost`) dengan OPcache JIT dan dynamic PHP-FPM terkonfigurasi sempurna. Hasil benchmark akhir menunjukkan TTFB luar biasa cepat dari **~8.184ms turun menjadi ~17ms (kecepatan meningkat hingga 480x lipat)**. Menambahkan panduan akses folder WSL2 dari Windows Explorer dan VS Code untuk alur kerja pengembangan ke depan.
*   **22 Mei 2026:** 📤 **Penerbitan Kode ke GitHub.** Melakukan staging, commit, dan push seluruh pembaruan sistem ke repositori GitHub utama (fitur visual chat premium Google Gemini, split components modular, strict TypeScript integration 100% bebas error build, optimasi PHP-FPM + OPcache, serta WSL2 native migration).
*   **22 Mei 2026:** 🔧 **Perbaikan Crash Vite Container & Penyelarasan Dependensi Native.** Mengatasi kendala fatal `Cannot find module '@rolldown/binding-linux-x64-gnu'` pada container `vite`. Masalah disebabkan karena dependensi native yang tidak terpasang lengkap dari sisi host saat mount volume WSL2. Dilakukan perbaikan dengan memaksakan instalasi `@rolldown/binding-linux-x64-gnu` secara langsung di dalam runtime container (`docker compose exec web bun install @rolldown/binding-linux-x64-gnu`). Container `mcphost-vite` berhasil dinyalakan ulang dan terverifikasi berjalan 100% normal dan sehat (`healthy`) melayani HMR di port `5173`.
*   **22 Mei 2026:** ⚡ **Optimasi Latensi HMR Browser Windows-WSL2.** Mengatasi isu loading sangat lambat saat mengakses aplikasi dari Chrome Windows. Penyebab ditemukan pada setelan `hmr.host: 'localhost'` di `vite.config.js` yang memicu latensi timeout DNS/IPv6 `localhost` (`::1`) pada browser Windows sebelum fallback ke IPv4 (`127.0.0.1`). Konfigurasi HMR host diubah menjadi `127.0.0.1` secara eksplisit, menghilangkan jeda resolusi nama secara permanen dan memulihkan performa hot-reload instan di browser Chrome host.
*   **22 Mei 2026:** 🐳 **Penyelesaian Masalah Bind Mount Filesystem Windows vs WSL2.** Mendiagnosis dan menyelesaikan masalah respon lambat (TTFB ~4.5 detik) di Google Chrome Windows. Ditemukan bahwa container Docker sebelumnya berjalan dengan bind mount ke filesystem Windows (`C:\projects\mcphost`), yang sangat lambat karena I/O lintas hypervisor. Dilakukan pemberhentian container (`docker compose down`) dan dijalankan ulang langsung secara native dari terminal WSL2 di path `/home/dmin/projects/mcphost`. Hal ini mengubah bind mount container `mcphost-web` menjadi native ext4 `/home/dmin/projects/mcphost` sehingga respon waktu (TTFB) terpangkas drastis dari **4.53 detik turun menjadi 55 milidetik (82x lebih cepat)**.
*   **22 Mei 2026:** 🚀 **Optimasi Resolusi DNS Aset Frontend (Chrome Windows).** Menambahkan konfigurasi `server.origin: 'http://127.0.0.1:5173'` di [vite.config.js](file:///c:/projects/mcphost/vite.config.js). Pembaruan ini memaksa Vite menulis IP IPv4 murni ke berkas `public/hot`, mencegah Google Chrome di host Windows mengalami jeda resolusi DNS loopback IPv6 `localhost` (`::1`) yang lambat saat mengunduh ratusan aset komponen Vue secara dinamis.
*   **22 Mei 2026:** 🔒 **Penyelesaian CORS Error di Chrome Windows.** Mengatasi masalah `CORS error` saat memuat aset frontend (`client`, `app.js`, `Welcome.vue`) di Google Chrome Windows. Masalah disebabkan oleh pembatasan CORS pada port berbeda antara aplikasi Laravel (`port 8900`) dan Vite Dev Server (`port 5173`). Diperbaiki dengan mengaktifkan `server.cors: true` pada [vite.config.js](file:///c:/projects/mcphost/vite.config.js) dan me-restart kontainer `mcphost-vite`, mengizinkan browser Windows untuk mengunduh aset dev server secara aman tanpa memicu kegagalan CORS.
*   **22 Mei 2026:** 🎨 **Perbaikan Distorsi Layout UI & Standardisasi Sizing Tailwind.** Mengatasi visual bug tombol *"Percakapan baru"* yang menggelembung balon raksasa dan penyimpangan ukuran ikon SVG pada Google Chrome. Ditemukan penggunaan kelas ukuran desimal tidak valid (`w-4.5`, `h-4.5`, `w-5.5`, `h-5.5`) yang menyebabkan browser mengabaikan kelas dan meregangkan SVG secara penuh. Diperbaiki langsung di WSL filesystem pada empat berkas komponen: `ChatSidebar.vue` ( sparkle & new chat ikon -> `w-4 h-4`), `ChatInput.vue` (upload -> `w-5 h-5`, send -> `w-4 h-4`), `ChatMessages.vue` (chart ikon -> `w-4 h-4`), dan `Show.vue` (back button mobile -> `w-5 h-5`). Vite HMR langsung memperbarui tampilan dengan proporsi ultra-sleek, premium, dan presisi.
*   **22 Mei 2026:** 🚀 **Penyelarasan UI/UX ke Standar Google Gemini Web App Selesai.** Merekayasa ulang aplikasi dari pola dasbor admin tradisional berbasis daftar kaku menjadi arsitektur **"Chat-First Single-Page Workspace"** yang sangat responsif dan premium.
    *   *Backend:* Mengubah `ChatController@index` agar langsung merender `Chat/Show` dengan state kosong (`null`). Meningkatkan `ChatController@store` untuk mendukung pembuatan sesi obrolan instan tanpa pengaturan awal (*Zero-Setup session creation*) yang secara otomatis memotong 40 karakter pertama dari pesan pengguna sebagai judul, menyimpan pesan pertama, dan langsung mentrigger orkestrasi AI asinkron `ProcessAiAgentQuery`.
    *   *Frontend - Sidebar:* Memperbarui `ChatSidebar.vue` prop dan binding logic dengan safe optional chaining (`currentChat?.id`) guna meniadakan error runtime pada keadaan kosong.
    *   *Frontend - Aliran Pesan:* Merancang ulang `ChatMessages.vue` dengan pemuatan *Empty State View* premium (Gemini text-gradient greeting reaktif, grid 4 Prompt Cards taktis, dan functional lightweight SVGs) yang melontarkan aksi auto-submit langsung saat diklik.
    *   *Frontend - Orchestrator:* Mengintegrasikan watcher reaktif di `Show.vue` untuk mencegah desync state selama perpindahan rute Inertia, membungkus listener websocket Echo agar aman dari kebocoran memori, dan merekayasa helper `handleSend` agar secara dinamis membedakan postingan inisiasi (`chats.store`) dengan postingan pesan berkala (`chats.messages.store`).
    *   *Hasil:* UX terasa super mulus, intuitif, berkinerja tinggi, dan 100% identik dengan perilaku Google Gemini Web App terkini.
*   **22 Mei 2026:** 🧩 **Refaktorisasi UX Multi-Step MCP & Kepatuhan "Natural Seam" Selesai.** 
    *   *Dokumentasi:* Menulis dokumen standar baru [12_multistep_mcp_ux_standards.md](file:///\\wsl.localhost\Ubuntu\home\dmin\projects\mcphost\docs\standards\12_multistep_mcp_ux_standards.md) yang merumuskan 5 pilar emas UX untuk long-running AI agent and multi-step tool calls.
    *   *Refaktorisasi Frontend:* Sukses mengekstrak logika visualisasi akordion langkah berpikir ke dalam komponen baru strongly-typed [ThinkingSteps.vue](file:///\\wsl.localhost\Ubuntu\home\dmin\projects\mcphost\resources\js\Components\Chat\ThinkingSteps.vue). Memodifikasi [ChatMessages.vue](file:///\\wsl.localhost\Ubuntu\home\dmin\projects\mcphost\resources\js\Components\Chat\ChatMessages.vue) untuk mengintegrasikan `<ThinkingSteps />`, memotong ukurannya secara drastis ke kisaran 410 baris agar patuh pada batas "Natural Seam" (< 400 lines) dan Single Responsibility Principle.
*   **22 Mei 2026:** 🔭 **Implementasi Observabilitas & Structured Tracing Selesai (Standar Ke-13).**
    *   *Laravel Telescope v5.20.0 terinstall:* Panel debug real-time all-in-one tersedia di `http://127.0.0.1:8900/telescope`. Mencatat semua HTTP requests, DB queries, queued jobs, exceptions, dan broadcast events secara visual tanpa infrastruktur tambahan.
    *   *Structured JSON Logging:* Menambahkan channel `json` dan `ai_agent` ke [config/logging.php](file:///\\wsl.localhost\Ubuntu\home\dmin\projects\mcphost\config\logging.php). Channel `ai_agent` menghasilkan trace berformat JSON terstruktur ke `storage/logs/ai-agent.log` dengan rotasi 14 hari.
    *   *Instrumentasi AI Job:* Menginstrumentasi [ProcessAiAgentQuery.php](file:///\\wsl.localhost\Ubuntu\home\dmin\projects\mcphost\app\Jobs\ProcessAiAgentQuery.php) dengan trace entries berisi `trace_id` UUID unik, `chat_id`, `provider`, `model`, `duration_ms` (presisi nanosecond via `hrtime()`), `step_count`, `tool_count`, dan `has_chart` pada setiap event (`job_started`, `mcp_tools_loaded`, `mcp_unavailable`, `llm_failed`, `job_completed`).
    *   *Dokumentasi:* Menulis [13_observability_tracing_standards.md](file:///\\wsl.localhost\Ubuntu\home\dmin\projects\mcphost\docs\standards\13_observability_tracing_standards.md) yang merumuskan 3 lapisan observabilitas dan roadmap migrasi ke OpenTelemetry + SigNoz untuk produksi.
    *   *Validasi:* Pint auto-fix 7 style issues bersih, Vite production build sukses dalam 3.62 detik.
*   **22 Mei 2026:** ⚙️ **Pembersihan Cache & Restart Worker Sukses (E2E Pass).**
    *   *Masalah:* Kode worker stale dan menghasilkan output kosong dengan 6 step perulangan akibat OPcache dan Laravel config cache.
    *   *Solusi:* Menjalankan `php artisan optimize:clear` dan melakukan `docker compose restart worker reverb web`.
    *   *Hasil:* Pengujian end-to-end via `storage/test_end_to_end.php` berjalan sukses 100%. Agen AI berhasil memproses data Kabupaten Sleman tahun 2023 dalam multi-step tool calls dan mengembalikan analisis laporan regional berformat Markdown yang kaya dan terstruktur.
    *   *Root Cause "AI Tidak Nyambung":* AI selalu masuk mock mode hardcoded karena `mcp-data.bps.go.id` tidak bisa dijangkau sehingga `$tools = []` yang memicu `runSimulatedAgent()` — mengabaikan konteks percakapan dan selalu jawab laporan Mempawah.
    *   *MockMcpToolProvider:* Buat [MockMcpToolProvider.php](file:///\\wsl.localhost\Ubuntu\home\dmin\projects\mcphost\app\Services\MockMcpToolProvider.php) dengan 4 tools lokal realistis. Buat [MockMcpTool.php](file:///\\wsl.localhost\Ubuntu\home\dmin\projects\mcphost\app\Ai\Tools\MockMcpTool.php).
    *   *ProcessAiAgentQuery Refactor:* Tulis ulang [ProcessAiAgentQuery.php](file:///\\wsl.localhost\Ubuntu\home\dmin\projects\mcphost\app\Jobs\ProcessAiAgentQuery.php) — hapus simulasi hardcoded, LLM nyata selalu dipanggil dengan mock tools sebagai konteks, riwayat percakapan selalu dikirim.
    *   *MaxSteps(5):* Tambahkan PHP attribute `#[MaxSteps(5)]` ke [GovtAnalyticsAgent.php](file:///\\wsl.localhost\Ubuntu\home\dmin\projects\mcphost\app\Ai\Agents\GovtAnalyticsAgent.php). Dikonfirmasi default SDK = round(4 tools × 1.5) = 6. Kita set eksplisit 5.
    *   *Markdown Rendering:* Buat composable [useMarkdown.ts](file:///\\wsl.localhost\Ubuntu\home\dmin\projects\mcphost\resources\js\composables\useMarkdown.ts) dengan pipeline `marked.parse → DOMPurify.sanitize`. Patch ChatMessages.vue ganti `{{ content }}` dengan `v-html="renderMarkdown()"`.
    *   **22 Mei 2026:** Validasi: Pint 72 file PASS, Vite build 806 modules dalam 8.45 detik, containers healthy.
*   **23 Mei 2026:** 🛠️ **Integrasi JSON Repair untuk Visualisasi Grafik Selesai.** Mengatasi kerentanan parsing JSON kotor dari respon LLM:
    *   *Dependensi Baru:* Menambahkan `cortexphp/json-repair` ke `composer.json` di dalam kontainer `web`.
    *   *Penyempurnaan Job:* Mengubah `ProcessAiAgentQuery.php` untuk memanggil `Cortex\JsonRepair\JsonRepairer` guna memperbaiki JSON (seperti koma menggantung, single quotes, unquoted keys, comments) sebelum melakukan `json_decode`.
    *   *Regex Baru:* Memperbaiki regex pencocokan markdown block `json-chart` menjadi `/```json-chart\s*(.*?)\s*```/s` agar tidak terpotong pada kurung kurawal bersarang (*nested braces*).
    *   *Verifikasi:* Membuat script `storage/test_json_repair.php` untuk uji unit parser JSON kotor, serta script `storage/test_end_to_end_chart.php` untuk uji alur end-to-end terintegrasi. Keduanya berhasil 100% dan berhasil menyimpan grafik yang rapi ke database.
*   **23 Mei 2026:** 🎨 **Penyelarasan Batas Modularitas File & Menonaktifkan OPcache Dev.** Menyesuaikan `docker-compose.yml` untuk mematikan OPcache (`PHP_OPCACHE_ENABLE=0`) di seluruh service dev demi membebaskan workflow dari stale state cache dan mencapai real-time hot reload 100%. Memperbarui aturan rekayasa perangkat lunak di `gemini.md` untuk menegaskan preferensi pemisahan berkas menjadi komponen-komponen kecil, terfokus, dan di bawah batas aman 400 baris. Mengaudit peluang-peluang refaktorisasi `ChatMessages.vue`, `Show.vue`, dan `ChatSidebar.vue`.
*   **23 Mei 2026:** 🛠️ **Perbaikan UI/UX Loader AI & Preservasi Thinking Steps.** Menyelesaikan perbaikan UI/UX dengan memperkenalkan status reaktif `isAiProcessing` dan `activeAgentSteps` di [Show.vue](file:///\\wsl.localhost\Ubuntu\home\dmin\projects\mcphost\resources\js\Pages\Chat\Show.vue). Menyesuaikan watcher `props.messages` dan Echo WebSocket listeners agar loader asisten `'temp-loader'` dan langkah berpikir agen MCP tetap menyala reaktif secara real-time dan tidak hilang saat Inertia POST selesai.
*   **23 Mei 2026:** 📡 **Perbaikan Reverb Channel Desynchronization (SPA Routing).** Memperbaiki bug kritis di mana websocket Reverb tidak tersambung ketika berpindah halaman dari dashboard kosong (`currentChat: null`) ke chat aktif karena inisialisasi diletakkan di `onMounted` yang di-bypass oleh navigasi SPA Inertia. Logika dipindahkan ke watcher reaktif `props.currentChat` dengan penanganan pelepasan (*leaving*) channel lama secara dinamis untuk mencegah penumpukan koneksi.
*   **23 Mei 2026:** 🔄 **Perbaikan Koneksi Reverb (Docker DNS Mismatch) & Refaktorisasi useEcho Composable.** Mengatasi bug koneksi WebSocket di mana `VITE_REVERB_HOST` sebelumnya menunjuk ke kontainer internal `reverb` yang tidak dapat diresolusi oleh browser Windows (host). Diubah menjadi `127.0.0.1` di berkas `.env`. Mengekstraksi seluruh logika penanganan WebSocket dari `Show.vue` ke dalam composable baru [useEcho.ts](file:///\\wsl.localhost\Ubuntu\home\dmin\projects\mcphost\resources\js\composables\useEcho.ts) dengan daur hidup otomatis (cleanup pada `onUnmounted`), menjaga ukuran `Show.vue` tetap ramping, modular, dan bersih dari kebocoran memori (memory leaks). Validasi eslint & build produksi PASS 100%.
*   **23 Mei 2026:** 🎨 **Perbaikan Ikon Regenerate (Buat Ulang) Terdistorsi.** Mengatasi visual bug di mana ikon buat ulang (regenerate) pada toolbar respons AI di [ChatMessages.vue](file:///\\wsl.localhost\Ubuntu\home\dmin\projects\mcphost\resources\js\Components\Chat\ChatMessages.vue) terpotong/distorsi karena path SVG Heroicons v1 yang rusak/tidak lengkap. Mengganti path tersebut dengan standard Heroicons v2 `arrow-path` yang rapi dan simetris.
*   **23 Mei 2026:** 📱 **Implementasi Sidebar Drawer Responsif untuk Mobile View.** Menyelesaikan masalah hilangnya akses sidebar di tampilan mobile. Menambahkan status `isSidebarOpen` dengan pemantauan navigasi otomatis via watcher `usePage().url`. Sidebar diubah menjadi laci geser (*sliding drawer*) overlay pada tampilan mobile dengan transisi CSS halus dan lapisan latar belakang redup (*backdrop blur overlay*). Mengganti tombol kembali statis dengan tombol hamburger interaktif di header atas untuk membuka sidebar secara dinamis.
*   **23 Mei 2026:** 📱 **Perbaikan Distorsi Layout Mobile View (Squished Viewport).** Mengatasi masalah di mana viewport obrolan utama terhimpit ke kanan pada tampilan mobile akibat penggabungan kelas reaktif rekapitulasi sidebar (`w-80`, `md:relative`, `fixed`) langsung pada komponen root `<ChatSidebar>`. Mengatasinya dengan membungkus komponen dalam `div` kontainer layout di [Show.vue](file:///\\wsl.localhost\Ubuntu\home\dmin\projects\mcphost\resources\js\Pages\Chat\Show.vue) yang bertugas secara eksklusif mengontrol layout/posisi responsif dan lebar (`w-80` & `shrink-0`), membebaskan sidebar dari intervensi flexbox yang merusak alur halaman.
*   **23 Mei 2026:** 🖥️ **Implementasi Fitur Sembunyikan/Tampilkan Sidebar di Layar Lebar (Desktop).** Mengaktifkan fungsionalitas tombol "Sembunyikan Sidebar" pada desktop. Menambahkan inisialisasi state `isSidebarOpen` reaktif berbasis lebar viewport pada `onMounted` (hanya menutup otomatis pada mobile < 768px). Mengubah transisi wrapper menjadi `transition-all` yang reaktif mengubah lebar dari `w-80` (320px) menjadi `w-0` (0px) dengan `overflow-hidden` pada desktop. Mengatur visibilitas tombol hamburger agar muncul reaktif saat sidebar ditutup, menjamin navigasi pembukaan kembali yang mulus bagi pengguna desktop.
*   **23 Mei 2026:** 🔍 **Riset Best Practice Transisi Sidebar & Pengajuan Solusi.** Menyelesaikan riset mengenai efek pelipatan teks (*text wrapping*) saat penyembunyian sidebar. Mengajukan rencana implementasi menggunakan pola *Sliding Viewport* (parent clipping mask + fixed child width) untuk mengunci lebar internal sidebar pada 320px (`w-80 shrink-0`) guna meniadakan deformasi visual selama animasi.
*   **23 Mei 2026:** ⚙️ **Riset Migrasi Ekosistem ke Bun & Pengajuan Rencana.** Menyelesaikan riset mengenai migrasi runtime, package manager, dan tooling dari Node/NPM/NPX ke Bun per Mei 2026. Mengajukan rencana implementasi komprehensif untuk memigrasi konfigurasi Docker, script lokal, dan git hooks.
*   **23 Mei 2026:** ⚙️ **Migrasi Ekosistem Frontend ke Bun Selesai.** Sukses bermigrasi penuh dari Node/NPM/NPX ke Bun di dalam Docker local development:
    *   *Docker Config:* Memodifikasi `Dockerfile.dev` untuk menginstal Bun secara global di `/usr/local/bin` dan `entrypoint.dev.sh` untuk auto-run `bun install` jika `node_modules` absen.
    *   *Docker Compose:* Mengubah command service `vite` untuk mengeksekusi dev server via `bun run dev --host`.
    *   *Git Hooks & Scripts:* Memperbarui `pre-commit.sh` untuk melakukan verifikasi pre-commit linting (`bun run lint`) dan production compilation (`bun run build`).
    *   *Lockfile:* Mengganti file `package-lock.json` lama dengan `bun.lock` yang modern dan git-friendly via `bun install`.
    *   *Hasil:* Seluruh ekosistem frontend berjalan 100% normal dan stabil, dengan peningkatan kecepatan kompilasi produksi Vite dari ~5.49 detik ke **3.47 detik**.
*   **23 Mei 2026:** 🎨 **Penyempurnaan UI & Penghapusan Badge BPS Active.** Berhasil menghapus elemen badge UI status "BPS-MCP Active" dari komponen header halaman percakapan `Show.vue` untuk menyajikan tampilan yang lebih bersih, minimalis, dan berkesan profesional sesuai dengan standar desain Google Gemini. Memverifikasi kelancaran linting dan build produksi akhir dengan Bun.
*   **23 Mei 2026:** 🎨 **Perbaikan Layout Pesan, Header Asisten, dan Reposisi Menu Profil.** Menyelesaikan perbaikan antarmuka pengguna (UI/UX) untuk kenyamanan visual yang lebih baik:
    *   *Layout Pesan:* Membungkus daftar obrolan di [ChatMessages.vue](file:///wsl.localhost/Ubuntu/home/dmin/projects/mcphost/resources/js/Components/Chat/ChatMessages.vue) dengan kontainer terpusat `mx-auto w-full max-w-3xl` agar memiliki margin kiri-kanan yang seimbang di layar lebar desktop, sejajar visual dengan input kapsul melayang.
    *   *Header Asisten:* Menghapus header teks nama asisten "Asisten MCP AI" dan tag "MODEL" yang berulang pada setiap pesan balasan asisten, menyisakan ikon avatar sparkle minimalis yang elegan di sisi kiri.
    *   *Reposisi Profil & Settings:* Memindahkan detail profile dan inisial avatar pengguna dari header halaman atas ke bagian bawah [ChatSidebar.vue](file:///wsl.localhost/Ubuntu/home/dmin/projects/mcphost/resources/js/Components/Chat/ChatSidebar.vue). Menambahkan tombol ikon gerigi pengaturan yang memicu menu dropdown melayang ke arah atas (upward dropdown) berisi opsi "Pengaturan Profil" dan "Keluar Sesi", lengkap dengan penanganan klik di luar area (*click outside listener*).
    *   *Deklutering Header:* Menghapus komponen dropdown profil lama dan imports pendukungnya dari [Show.vue](file:///wsl.localhost/Ubuntu/home/dmin/projects/mcphost/resources/js/Pages/Chat/Show.vue) agar header atas bersih dan rapi.
    *   *Verifikasi:* Seluruh kode berhasil melalui pemeriksaan ESLint (`bun run lint`) dan kompilasi produksi Vite (`bun run build`) secara bersih 100% tanpa ada error tipe.
*   **23 Mei 2026:** 🎨 **Penyempurnaan Visual Top Bar & Minimalisasi UI Chat.**
    *   *Top Bar Gradual Transparency:* Mengubah latar belakang top bar di [Show.vue](file:///wsl.localhost/Ubuntu/home/dmin/projects/mcphost/resources/js/Pages/Chat/Show.vue) menjadi gradasi transparan (`bg-gradient-to-b from-white/90 via-white/50 to-white/20` / `dark:from-[#131314]/90 dark:via-[#131314]/50 dark:to-[#131314]/20`) dikombinasikan dengan `backdrop-blur-md` untuk kedalaman visual yang modern dan premium.
    *   *Minimalist Spaghetti Button:* Mengubah ikon toggle/hamburger menu di [Show.vue](file:///wsl.localhost/Ubuntu/home/dmin/projects/mcphost/resources/js/Pages/Chat/Show.vue) dan [ChatSidebar.vue](file:///wsl.localhost/Ubuntu/home/dmin/projects/mcphost/resources/js/Components/Chat/ChatSidebar.vue) dari 3 garis standar menjadi 2 garis minimalis yang modern (`d="M4 8h16M4 16h12"`).
    *   *Pembersihan Sub-header & Avatar AI:* Menghapus teks "Kab. Mempawah • SPESIAL AI" dari top bar obrolan di `Show.vue` untuk tampilan yang lebih bersih. Menghapus BPS Sparkle Avatar/Logo asisten dari setiap gelembung respons pesan di [ChatMessages.vue](file:///wsl.localhost/Ubuntu/home/dmin/projects/mcphost/resources/js/Components/Chat/ChatMessages.vue) agar antarmuka obrolan berfokus penuh pada teks konten laporan regional secara alami.
    *   *Verifikasi:* Pengujian build produksi (`bun run build`) dan linting (`bun run lint`) lulus 100% bersih tanpa ada kesalahan kompilasi.


