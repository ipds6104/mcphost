# PHP & Laravel Standards

Dokumen ini mendefinisikan standar pengodean, arsitektur, dan konvensi yang digunakan dalam pengembangan aplikasi ini, memastikan interoperabilitas, keterbacaan, dan kemudahan pemeliharaan berbasis ekosistem PHP modern dan Laravel 13.

---

## 1. PSR (PHP Standard Recommendation) & PER (PHP Extended Recommendation)

Kita secara penuh mematuhi standar terbaru dari **PHP Framework Interoperability Group (PHP-FIG)**:

### A. PSR-1: Basic Coding Standard
*   **PHP Tags:** Harus menggunakan tag panjang `<?php` atau tag cetak pendek `<?=`. Jangan menggunakan tag kustom atau ASP tags.
*   **Character Encoding:** File PHP wajib menggunakan format **UTF-8 tanpa BOM (Byte Order Mark)**.
*   **Side Effects:** File harus mendeklarasikan simbol baru (class, function, constant, dll.) atau mengeksekusi logika (*side effects*), tetapi **tidak boleh melakukan keduanya sekaligus** dalam file yang sama.
*   **Namespace & Class Names:** Class names wajib menggunakan format `StudlyCaps` (PascalCase), dan namespace wajib mengikuti standar autoloading.
*   **Class Constants:** Konstanta di dalam kelas wajib ditulis dengan huruf kapital penuh menggunakan pemisah garis bawah (e.g., `const VERSION = '1.0.0'`).
*   **Method Names:** Nama metode wajib ditulis menggunakan camelCase (e.g., `fetchRapotData()`).

### B. PSR-4: Autoloading Standard
*   Namespace dasar (*root*) untuk aplikasi Laravel dipetakan ke direktori `app/` menggunakan autoloader Composer dengan prefix `App\`.
*   Setiap bagian namespace tambahan harus cocok dengan nama subdirektori di bawah `app/` (e.g., `App\Ai\Agents\ProductWriter` harus terletak di file `app/Ai/Agents/ProductWriter.php`).

### C. PER Coding Style (Succesor of PSR-12)
Sebagai suksesor dari PSR-12, **PER Coding Style** dirancang agar dapat terus berevolusi seiring pembaharuan fitur PHP modern (PHP 8.2, 8.3, dst.):
*   **Indentation:** Wajib menggunakan **4 spasi** untuk indentasi (tidak boleh menggunakan Tab).
*   **Line Endings:** File wajib diakhiri dengan satu baris kosong (Unix LF).
*   **Keywords & Types:** Semua kata kunci reservasi PHP dan tipe data wajib ditulis dengan huruf kecil (`true`, `false`, `null`, `string`, `int`, `array`, dst.).
*   **Declare Strict Types:** Setiap file PHP berorientasi logika wajib diawali dengan deklarasi strict types:
    ```php
    <?php

    declare(strict_types=1);
    ```
*   **Modern PHP Syntax Alignment:**
    *   **Union/Intersection Types:** Ditulis rapat tanpa spasi di sekitar operator pemisah (e.g., `string|int $value`).
    *   **Attributes:** Atribut PHP modern wajib ditulis pada baris terpisah tepat sebelum elemen yang dituju (e.g., di atas class, method, atau property).
    *   **Constructor Promotion:** Direkomendasikan menggunakan constructor promotion untuk dependency injection pada class agar kode lebih ringkas.

---

## 2. Standar & Konvensi Laravel 13

Laravel memiliki standar konvensi komunitas (*Laravel Way*) yang harus dipatuhi secara ketat untuk menjaga kebersihan arsitektur:

### A. Konvensi Penamaan (Naming Conventions)
*   **Controllers:** Singular (tunggal) dengan akhiran Controller (e.g., `RapotController`, `AiChatController`).
*   **Models:** Singular (tunggal) dalam bentuk PascalCase (e.g., `Rapot`, `Regency`).
*   **Migrations:** Deskriptif dengan format snake_case jamak (e.g., `create_rapots_table`).
*   **Views & Templates:** snake_case dengan ekstensi `.blade.php` (e.g., `chat_window.blade.php`).
*   **Routes:** Menggunakan nama rute berupa kebab-case dengan dot notation untuk penamaan aksi (e.g., `Route::get('/ai-chat', ...)->name('ai-chat.index')`).

### B. Arsitektur Thin Controller, Fat Model / Service
*   **Controllers:** Hanya boleh menangani *request* HTTP, validasi dasar, dan mengembalikan respon (*response*). Controller tidak boleh mengandung logika bisnis yang rumit.
*   **Service Layer / Agents:** Logika bisnis yang kompleks, komunikasi dengan API eksternal (termasuk MCP Client), dan logika AI didelegasikan ke kelas Service atau kelas Agent khusus di bawah namespace `App\Ai\` atau `App\Services\`.
*   **Eloquent Models:** Bertanggung jawab atas query database, relasi tabel, dan manipulasi data internal (*mutators/casters*).

### C. Konvensi Laravel 13 AI SDK
*   **Agent Generation:** Gunakan Artisan command `php artisan make:agent [NamaAgent]` untuk menghasilkan boilerplate agen.
*   **Declarative Provider:** Gunakan PHP attributes untuk mendeklarasikan provider default kelas Agen secara deklaratif:
    ```php
    use Laravel\Ai\Attributes\Provider;

    #[Provider('anthropic')]
    class RapotAnalyzer implements Agent
    {
        // ...
    }
    ```
*   **Structured Output Contracts:** Jika menginginkan output JSON terstruktur dari LLM, wajib mengimplementasikan interface `HasStructuredOutput` dan mendefinisikan metode `schema()`.
