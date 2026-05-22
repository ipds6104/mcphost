<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    chats: {
        type: Array,
        default: () => [],
    },
});

const isCreating = ref(false);
const newChatForm = useForm({
    title: '',
});

const createNewChat = () => {
    if (!newChatForm.title.trim()) return;
    newChatForm.post(route('chats.store'), {
        onSuccess: () => {
            newChatForm.reset();
            isCreating.value = false;
        },
    });
};
</script>

<template>
    <Head title="Government Analytics Portal" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-bold tracking-tight text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-indigo-500 dark:from-blue-400 dark:to-indigo-300">
                    Sistem Pembinaan Statistik Sektoral (SPESIAL)
                </h2>
                <button
                    @click="isCreating = true"
                    class="px-4 py-2 text-sm font-semibold text-white transition duration-200 transform rounded-lg shadow bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Mulai Analisis Baru
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Modal Buat Obrolan Baru -->
                <div
                    v-if="isCreating"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
                >
                    <div class="w-full max-w-md p-6 bg-white rounded-2xl shadow-xl dark:bg-gray-800 border border-gray-150 dark:border-gray-700 animate-in fade-in zoom-in-95 duration-200">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">
                            Buat Sesi Analisis Data
                        </h3>
                        <form @submit.prevent="createNewChat">
                            <div class="mb-4">
                                <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">
                                    Topik Analisis / Nama Sesi
                                </label>
                                <input
                                    v-model="newChatForm.title"
                                    type="text"
                                    placeholder="Contoh: Analisis IPKP Kab. Mempawah 2025"
                                    class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150"
                                    required
                                />
                            </div>
                            <div class="flex justify-end gap-3">
                                <button
                                    type="button"
                                    @click="isCreating = false"
                                    class="px-4 py-2 text-sm font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 dark:text-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 rounded-lg transition"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    :disabled="newChatForm.processing"
                                    class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow transition disabled:opacity-50"
                                >
                                    Mulai Sesi
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Konten Utama: Daftar Chat Terakhir -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Penjelasan Fitur -->
                    <div class="md:col-span-1 space-y-6">
                        <div class="p-6 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-800/50 rounded-2xl border border-blue-100/50 dark:border-gray-700/50">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-3">
                                Selamat Datang di BPS Portal
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                                Asisten analitis statistik sektoral Anda untuk koordinasi Pembinaan Statistik Sektoral (PSS) Kabupaten Mempawah dengan integrasi <strong>Model Context Protocol (MCP)</strong>.
                            </p>
                            <div class="mt-4 pt-4 border-t border-blue-200/30 dark:border-gray-700 flex flex-col gap-2">
                                <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                                    <span class="w-2 h-2 rounded-full bg-green-500"></span>
                                    Remote MCP Server: Connected
                                </div>
                                <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                    Laravel Reverb: Real-time Active
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- List Obrolan -->
                    <div class="md:col-span-2">
                        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700/50 overflow-hidden">
                            <div class="p-6 border-b border-gray-150 dark:border-gray-700 flex justify-between items-center">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                                    Riwayat Analisis Statistik
                                </h3>
                                <span class="text-xs text-gray-500 font-medium">
                                    {{ chats.length }} Sesi Aktif
                                </span>
                            </div>

                            <div v-if="chats.length === 0" class="p-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                                <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-1">
                                    Belum Ada Sesi Analisis
                                </h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400 max-w-sm mx-auto mb-4">
                                    Mulai buat sesi analisis pertama Anda untuk memetakan dan mengukur evaluasi pembangunan statistik sektoral di wilayah Mempawah.
                                </p>
                                <button
                                    @click="isCreating = true"
                                    class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow transition"
                                >
                                    Buat Sesi Sekarang
                                </button>
                            </div>

                            <div v-else class="divide-y divide-gray-100 dark:divide-gray-700">
                                <Link
                                    v-for="chat in chats"
                                    :key="chat.id"
                                    :href="route('chats.show', chat.id)"
                                    class="flex items-center justify-between p-6 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition group"
                                >
                                    <div class="flex-1 min-w-0 pr-4">
                                        <h4 class="text-sm font-bold text-gray-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition truncate mb-1">
                                            {{ chat.title }}
                                        </h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Dibuat pada {{ new Date(chat.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-3 shrink-0">
                                        <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 px-2.5 py-1 rounded-full font-medium">
                                            BPS Mempawah
                                        </span>
                                        <svg class="h-5 w-5 text-gray-400 group-hover:text-blue-500 group-hover:translate-x-1 transition transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
