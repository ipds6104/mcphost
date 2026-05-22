# Idiomatic Development Principles & Conventions

Dokumen ini mendefinisikan prinsip **Idiomatic (Idiomatis)** yang diterapkan di seluruh lapisan aplikasi kita. Menulis kode yang "idiomatis" berarti menggunakan fitur, gaya penulisan, dan paradigma alami yang dirancang oleh bahasa pemrograman dan kerangka kerja (*framework*) tersebut, bukan memaksakan gaya dari bahasa lain (seperti menulis PHP bergaya Java atau JavaScript).

---

## 1. Idiomatic Modern PHP (PHP 8.3+)

Modern PHP sangat berorientasi pada keamanan tipe (*type safety*), ekspresif, dan performa tinggi. Kita mematuhi konvensi alami berikut:

### A. Strict Typing
Setiap file PHP wajib diawali dengan deklarasi strict types untuk mencegah konversi tipe data yang implisit dan tidak terduga:
```php
<?php

declare(strict_types=1);
```

### B. Constructor Property Promotion
Gunakan *property promotion* pada *constructor* untuk menyederhanakan *dependency injection* dan deklarasi properti kelas:
```php
// HINDARI (Verbouse / Gaya Lama)
class RapotService {
    private DatabaseConnection $db;
    public function __construct(DatabaseConnection $db) {
        $this->db = $db;
    }
}

// GUNAKAN (Modern Idiomatis)
class RapotService {
    public function __construct(
        protected DatabaseConnection $db
    ) {}
}
```

### C. Immutability dengan Readonly Properties
Untuk objek transfer data (DTO) atau kelas konfigurasi yang nilainya tidak boleh berubah setelah instansiasi, gunakan properti atau kelas `readonly`:
```php
readonly class RegencyData {
    public function __construct(
        public string $code,
        public string $name,
        public float $score
    ) {}
}
```

### D. Ekspresi Alami & Ekspresif
*   **Match Expression:** Gunakan `match` alih-alih `switch` karena mengembalikan nilai secara langsung dan melakukan perbandingan ketat (`===`).
    ```php
    $statusDescription = match ($score) {
        5.0 => 'Optimum',
        4.0 => 'Terpadu',
        3.0 => 'Terdefinisi',
        default => 'Rintisan/Terkelola',
    };
    ```
*   **Nullsafe Operator (`?->`):** Hindari pengecekan `null` berantai yang bertele-tele.
    ```php
    $regencyName = $user?->profile?->regency?->name;
    ```
*   **Arrow Functions (`fn()`):** Gunakan untuk callback satu baris yang bersih.
    ```php
    $codes = array_map(fn(Regency $r) => $r->code, $regencies);
    ```

---

## 2. Idiomatic Laravel (The Laravel Way)

Menulis kode dengan gaya "Laravel-native" memastikan kita memanfaatkan ekosistem secara optimal tanpa menulis ulang roda yang sudah ada:

### A. Dependency Injection via Service Container
Jangan pernah melakukan instansiasi objek manual (`new Client()`) untuk kelas yang memiliki dependensi eksternal. Biarkan Laravel Service Container mengelolanya:
```php
// Di Controller atau Service
public function __construct(
    protected McpClientManager $mcpManager
) {}
```

### B. Request Validation via Form Requests
Hindari meletakkan logika validasi input yang panjang di dalam Controller. Buat kelas Form Request khusus:
```php
// Jalankan: php artisan make:request SendChatMessageRequest
public function store(SendChatMessageRequest $request)
{
    // Data dijamin sudah tervalidasi dengan aman
    $validated = $request->validated();
}
```

### C. Manfaatkan Eloquent API Secara Penuh
*   Hindari penulisan raw SQL di dalam aplikasi kecuali untuk optimasi query yang sangat ekstrem.
*   Gunakan **Relationship Eager Loading** (`with()`) untuk mencegah masalah performa *N+1 query*.
    ```php
    // Ambil data wilayah beserta data rapot tahunannya secara efisien
    $regencies = Regency::with('rapots')->get();
    ```

### D. Event-Driven Architecture
Gunakan Laravel Events dan Listeners untuk memisahkan logika utama (misal: menyimpan chat user) dengan efek samping asinkron (misal: mengirim prompt ke AI agent dan logging).

---

## 3. Idiomatic Vue 3 & Inertia.js

*   **Props Down, Emits Up:** Komunikasi antar-komponen harus mengikuti aliran data searah yang bersih. Jangan langsung mengubah *props* di dalam komponen anak.
*   **Composition API Lifecycle Hooks:** Letakkan logika efek samping (seperti inisialisasi EventSource SSE) di dalam hook `onMounted` dan pastikan dibersihkan di `onUnmounted` untuk mencegah kebocoran memori (*memory leaks*).

---

## 4. Idiomatic AI Agent & Tool Design (Laravel 13 SDK)

Dalam merancang integrasi kecerdasan buatan, kita mengikuti konvensi AI SDK yang terstruktur:
*   **Single-Responsibility Agents:** Satu Agen mewakili satu tugas analitik spesifik (e.g., `RapotComparisonAgent` fokus pada analisis perbandingan wilayah, sedangkan `StatisticalValidatorAgent` fokus pada validasi konsistensi data).
*   **Class-Based Tools:** Setiap perkakas eksternal dipisah menjadi kelas mandiri yang mengimplementasikan interface `Laravel\Ai\Contracts\Tool`, lengkap dengan *description* yang mendalam agar LLM dapat memilih *tool* yang tepat dengan akurasi 100%.
