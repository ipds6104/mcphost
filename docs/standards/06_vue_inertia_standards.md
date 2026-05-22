# Vue 3 & Inertia.js Development Standards

Dokumen ini mendefinisikan standar pengodean frontend dan pola integrasi menggunakan **Vue 3 (Composition API)** dan **Inertia.js** untuk membangun antarmuka pengguna (UI) analitik chat sekelas Gemini yang responsif, modular, dan berperforma tinggi.

---

## 1. Vue 3 Composition API & `<script setup>`

Semua komponen Vue 3 yang dibuat dalam proyek ini wajib mematuhi panduan gaya resmi (*Vue 3 Style Guide*) dengan prioritas tertinggi:

### A. Sintaksis Komponen `<script setup>`
Wajib menggunakan sintaksis `<script setup>` karena menghasilkan kode yang lebih ringkas, performa kompilasi yang lebih baik, dan integrasi TypeScript yang optimal.
```vue
<script setup>
import { ref, onMounted } from 'vue';

// State reaktif
const message = ref('');
</script>
```

### B. Pengelolaan State Reaktif: `ref` vs `reactive`
*   **Aturan Utama:** Gunakan `ref()` sebagai standar utama untuk mendeklarasikan semua tipe data reaktif (primitif maupun objek/array) guna menjaga konsistensi penulisan.
*   **Pengecualian:** Pustaka `reactive()` hanya boleh digunakan jika terdapat objek besar terstruktur yang perilakunya sangat bergantung pada pengelompokan properti dinamis.
*   **Akses Nilai:** Selalu ingat untuk menggunakan `.value` saat mengakses atau memodifikasi nilai `ref` di dalam blok `<script>`, tetapi **tidak perlu** menggunakan `.value` di dalam blok `<template>`.

### C. Penamaan & Penulisan Komponen
*   **Nama File Komponen:** Menggunakan format PascalCase (e.g., `ChatWindow.vue`, `DynamicChart.vue`).
*   **Pendaftaran Komponen:** Impor komponen secara langsung (di bawah `<script setup>`), Vue akan otomatis mendaftarkannya secara PascalCase.
*   **Penggunaan di Template:** Direkomendasikan menggunakan format PascalCase pada tag template di Vue 3 (e.g., `<ChatWindow />`) untuk membedakannya dengan elemen HTML bawaan.

---

## 2. Standar Integrasi Inertia.js

Inertia.js menjembatani Laravel (backend) dan Vue (frontend) tanpa memerlukan arsitektur client-side API routing (SPA tanpa REST API terpisah).

### A. Struktur Direktori Frontend (`resources/js/`)
Semua berkas frontend dikelola secara terstruktur di bawah direktori `resources/js/`:
```
resources/js/
├── Pages/        # Komponen Halaman Utama yang di-render oleh Inertia::render() (e.g., Dashboard.vue, Chat.vue)
├── Components/   # Komponen UI modular dan reusable (e.g., ChatBubble.vue, AccordionStep.vue)
├── Layouts/      # Komponen tata letak global (e.g., AppLayout.vue)
└── app.js        # File bootstrap utama Inertia & Vue
```

### B. Aliran Data Server-ke-Client (Props)
*   **Initial Render:** Backend mengembalikan data awal sebagai *props* melalui `Inertia::render('Chat', ['initialHistory' => $history])`.
*   **Navigasi Tanpa Reload:** Selalu gunakan komponen `<Link>` dari `@inertiajs/vue3` untuk navigasi antarhalaman internal guna menghindari pemuatan ulang halaman penuh (*full-page reload*).

### C. Partial Reloads (Optimasi Performa)
Saat memperbarui riwayat percakapan atau memicu aksi tertentu, gunakan fitur **Partial Reload** agar server hanya mengirimkan data yang dibutuhkan saja tanpa mengevaluasi ulang data berat lainnya:
```javascript
import { router } from '@inertiajs/vue3';

router.reload({ 
  only: ['chatHistory'], // Hanya memuat ulang properti chatHistory dari backend
  preserveState: true    // Mempertahankan state input dan posisi scroll pengguna
});
```

---

## 3. SSE (Server-Sent Events) Integration di Vue 3

Karena Inertia.js menggunakan request AJAX berbasis Axios, komunikasi streaming real-time (seperti respon ketikan AI dan status proses multi-step) dikelola menggunakan browser **EventSource API** yang berjalan berdampingan dengan Inertia.

### Skema Implementasi Koneksi SSE di Vue:
```javascript
import { ref } from 'vue';

const isProcessing = ref(false);
const currentSteps = ref([]); // Menyimpan tahapan proses tool calls
const aiResponseText = ref('');

const sendMessage = (prompt) => {
  isProcessing.value = true;
  aiResponseText.value = '';
  currentSteps.value = [];

  // 1. Jalankan inisialisasi via HTTP POST (opsional, jika butuh passing data)
  // 2. Buka aliran streaming SSE dari endpoint Laravel
  const eventSource = new EventSource(`/ai/stream?prompt=${encodeURIComponent(prompt)}`);

  eventSource.onmessage = (event) => {
    const data = JSON.parse(event.data);

    if (data.type === 'thought') {
      // Perbarui status pemikiran di akordion proses
      currentSteps.value.push({ name: data.message, status: 'running' });
    } 
    else if (data.type === 'tool_call') {
      // Perbarui status tool yang sedang dipanggil
      const step = currentSteps.value.find(s => s.name.includes(data.tool));
      if (step) step.status = 'running';
    } 
    else if (data.type === 'tool_result') {
      // Tandai tool selesai dieksekusi
      const step = currentSteps.value.find(s => s.name.includes(data.tool));
      if (step) step.status = 'success';
    } 
    else if (data.type === 'text_delta') {
      // Alirkan karakter teks jawaban AI
      aiResponseText.value += data.content;
    }
  };

  eventSource.onerror = (error) => {
    console.error('SSE Error:', error);
    eventSource.close();
    isProcessing.value = false;
  };
};
```

---

## 4. Best Practice Penggunaan Vue 3 & Composition API

Untuk memastikan kode kita rapi, terstruktur, dan tidak cepat membengkak dalam satu file, setiap developer wajib mengikuti kaidah-kaidah berikut:

### A. Modularisasi Logika dengan Composables (VueUse / Custom Composable)
*   **Hindari "Fat" Components:** Batasi ukuran satu berkas komponen Vue maksimal **300–400 baris**. Jika file Vue Anda melebihi batas ini, itu adalah sinyal kuat untuk memecah logika atau template.
*   **Gunakan VueUse:** Manfaatkan pustaka `@vueuse/core` untuk interaksi sensor, state sinkronisasi ke local storage, event resize, clipboard, dan deteksi click-outside.
*   **Ekstraksi custom composable:** Jika suatu state dan logikanya dapat digunakan kembali (misalnya logika tracking scroll, timer, pencarian, input keyboard), pindahkan ke custom composable di folder `resources/js/Composables/` (e.g., `useChatScroll.js`).

### B. Arsitektur Komponen Bersih
*   **Props Down, Events Up:** Komponen anak (*child*) tidak boleh memutasi props secara langsung. Selalu gunakan `emit` untuk memberi tahu komponen induk (*parent*) tentang perubahan state.
*   **Reactive Refs Terkelompok:** Deklarasikan state secara teratur. Kelompokkan state UI (seperti `isLoading`, `isSidebarOpen`) dan state domain (seperti `messages`, `currentChat`) secara terpisah untuk memudahkan pemeliharaan.

---

## 5. Kapan Harus Memecah Komponen Baru?

Jangan biarkan satu file menampung seluruh antarmuka yang kompleks. Lakukan ekstraksi komponen baru (*refactoring*) jika memenuhi satu atau lebih kondisi berikut:

1.  **Reusability (Dapat Digunakan Kembali):** 
    Jika potongan UI tersebut digunakan di lebih dari satu tempat (misalnya tombol aksi chat, avatar pengguna, status badge, input field).
2.  **Complexity (Kerumitan Tinggi):** 
    Jika suatu bagian UI memiliki logika internal, state lokal, atau style yang rumit (misalnya: akordion langkah berpikir agen, panel visualisasi diagram, inline rename editor).
3.  **Readability (Kemudahan Membaca):** 
    Jika pembacaan visual template utama menjadi sulit karena terlalu banyak nested tag HTML (misalnya: memindahkan elemen sidebar list item ke `ChatSidebarItem.vue` dan kotak input chat ke `ChatInput.vue`).
4.  **Performance Boundaries:** 
    Bagian UI yang sangat sering ter-update secara dinamis (seperti cursor mengetik AI) sebaiknya berada di komponen kecilnya sendiri untuk membatasi cakupan re-rendering virtual DOM Vue.

---

## 6. Integrasi Pinia (State Management) & VueUse

### A. Penggunaan Pinia untuk Global State
Gunakan Pinia untuk menyimpan state global yang diakses oleh banyak halaman atau komponen yang letaknya berjauhan, seperti data autentikasi user aktif, preferensi tema (dark/light mode), atau daftar chat history global di sidebar.

**Contoh Struktur Store (`resources/js/Stores/chatStore.js`):**
```javascript
import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export const useChatStore = defineStore('chat', () => {
    // State
    const chats = ref([]);
    const activeChatId = ref(null);

    // Getters / Computed
    const activeChat = computed(() => chats.value.find(c => c.id === activeChatId.value));

    // Actions
    function setChats(newChats) {
        chats.value = newChats;
    }
    
    function updateChatTitle(id, newTitle) {
        const chat = chats.value.find(c => c.id === id);
        if (chat) chat.title = newTitle;
    }

    return { chats, activeChatId, activeChat, setChats, updateChatTitle };
});
```

### B. Penggunaan VueUse untuk Menghindari Bloating Kode
Dibandingkan menulis event listener manual yang melelahkan dan rentan bocor memori (*memory leak*), gunakan utility super ringkas dari VueUse:

*   **Penyimpanan Otomatis Ke LocalStorage (`useLocalStorage`):**
    ```javascript
    import { useLocalStorage } from '@vueuse/core';
    const darkTheme = useLocalStorage('theme_dark', false);
    ```
*   **Deteksi Click-Outside untuk Menutup Dropdown (`onClickOutside`):**
    ```javascript
    import { ref } from 'vue';
    import { onClickOutside } from '@vueuse/core';
    
    const dropdownRef = ref(null);
    const isOpen = ref(false);
    
    onClickOutside(dropdownRef, () => isOpen.value = false);
    ```
*   **Debouncing Input Pencarian (`refDebounced`):**
    ```javascript
    import { ref } from 'vue';
    import { refDebounced } from '@vueuse/core';
    
    const searchQuery = ref('');
    const debouncedSearch = refDebounced(searchQuery, 300); // Otomatis ter-debounce 300ms
    ```

Dengan menerapkan pemisahan tugas (*separation of concerns*) menggunakan **Composables**, **Pinia Stores**, dan **VueUse**, kode aplikasi BPS SPESIAL AI kita akan tetap sangat ringan, modular, berkinerja premium, dan mudah dipelihara!
