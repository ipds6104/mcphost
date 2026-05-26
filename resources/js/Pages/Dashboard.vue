<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
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
                <h2
                    class="bg-gradient-to-r from-blue-600 to-indigo-500 bg-clip-text text-2xl font-bold tracking-tight text-transparent dark:from-blue-400 dark:to-indigo-300"
                >
                    Sistem Pembinaan Statistik Sektoral (SPESIAL)
                </h2>
                <button
                    @click="isCreating = true"
                    class="transform rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow transition duration-200 hover:-translate-y-0.5 hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
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
                    <div
                        class="border-gray-150 animate-in fade-in zoom-in-95 w-full max-w-md rounded-2xl border bg-white p-6 shadow-xl duration-200 dark:border-gray-700 dark:bg-gray-800"
                    >
                        <h3
                            class="mb-4 text-lg font-bold text-gray-900 dark:text-white"
                        >
                            Buat Sesi Analisis Data
                        </h3>
                        <form @submit.prevent="createNewChat">
                            <div class="mb-4">
                                <label
                                    class="mb-2 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                >
                                    Topik Analisis / Nama Sesi
                                </label>
                                <input
                                    v-model="newChatForm.title"
                                    type="text"
                                    placeholder="Contoh: Analisis IPS Kab. Mempawah 2025"
                                    class="w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 text-gray-900 transition duration-150 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    required
                                />
                            </div>
                            <div class="flex justify-end gap-3">
                                <button
                                    type="button"
                                    @click="isCreating = false"
                                    class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    :disabled="newChatForm.processing"
                                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow transition hover:bg-blue-700 disabled:opacity-50"
                                >
                                    Mulai Sesi
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Konten Utama: Daftar Chat Terakhir -->
                <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
                    <!-- Penjelasan Fitur -->
                    <div class="space-y-6 md:col-span-1">
                        <div
                            class="rounded-2xl border border-blue-100/50 bg-gradient-to-br from-blue-50 to-indigo-50 p-6 dark:border-gray-700/50 dark:from-gray-800 dark:to-gray-800/50"
                        >
                            <h3
                                class="mb-3 text-lg font-bold text-gray-900 dark:text-white"
                            >
                                Selamat Datang di BPS Portal
                            </h3>
                            <p
                                class="text-sm leading-relaxed text-gray-600 dark:text-gray-300"
                            >
                                Asisten analitis data dasar & sektoral Anda
                                untuk koordinasi Pembinaan Statistik Sektoral
                                (PSS) Kabupaten Mempawah dengan integrasi
                                <strong>Model Context Protocol (MCP)</strong>.
                            </p>
                            <div
                                class="mt-4 flex flex-col gap-2 border-t border-blue-200/30 pt-4 dark:border-gray-700"
                            >
                                <div
                                    class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400"
                                >
                                    <span
                                        class="h-2 w-2 rounded-full bg-green-500"
                                    ></span>
                                    Remote MCP Server: Connected
                                </div>
                                <div
                                    class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400"
                                >
                                    <span
                                        class="h-2 w-2 rounded-full bg-blue-500"
                                    ></span>
                                    Laravel Reverb: Real-time Active
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- List Obrolan -->
                    <div class="md:col-span-2">
                        <div
                            class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-700/50 dark:bg-gray-800"
                        >
                            <div
                                class="border-gray-150 flex items-center justify-between border-b p-6 dark:border-gray-700"
                            >
                                <h3
                                    class="text-lg font-bold text-gray-900 dark:text-white"
                                >
                                    Riwayat Analisis Statistik
                                </h3>
                                <span class="text-xs font-medium text-gray-500">
                                    {{ chats.length }} Sesi Aktif
                                </span>
                            </div>

                            <div
                                v-if="chats.length === 0"
                                class="p-12 text-center"
                            >
                                <svg
                                    class="mx-auto mb-4 h-12 w-12 text-gray-400 dark:text-gray-600"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.5"
                                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                                    />
                                </svg>
                                <h4
                                    class="mb-1 text-sm font-semibold text-gray-900 dark:text-white"
                                >
                                    Belum Ada Sesi Analisis
                                </h4>
                                <p
                                    class="mx-auto mb-4 max-w-sm text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Mulai buat sesi analisis pertama Anda untuk
                                    memetakan dan mengukur evaluasi pembangunan
                                    statistik sektoral di wilayah Mempawah.
                                </p>
                                <button
                                    @click="isCreating = true"
                                    class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white shadow transition hover:bg-blue-700"
                                >
                                    Buat Sesi Sekarang
                                </button>
                            </div>

                            <div
                                v-else
                                class="divide-y divide-gray-100 dark:divide-gray-700"
                            >
                                <Link
                                    v-for="chat in chats"
                                    :key="chat.id"
                                    :href="route('chats.show', chat.id)"
                                    class="group flex items-center justify-between p-6 transition hover:bg-gray-50 dark:hover:bg-gray-700/30"
                                >
                                    <div class="min-w-0 flex-1 pr-4">
                                        <h4
                                            class="mb-1 truncate text-sm font-bold text-gray-900 transition group-hover:text-blue-600 dark:text-white dark:group-hover:text-blue-400"
                                        >
                                            {{ chat.title }}
                                        </h4>
                                        <p
                                            class="text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            Dibuat pada
                                            {{
                                                new Date(
                                                    chat.created_at,
                                                ).toLocaleDateString('id-ID', {
                                                    day: 'numeric',
                                                    month: 'long',
                                                    year: 'numeric',
                                                })
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        class="flex shrink-0 items-center gap-3"
                                    >
                                        <span
                                            class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                                        >
                                            BPS Mempawah
                                        </span>
                                        <svg
                                            class="h-5 w-5 transform text-gray-400 transition group-hover:translate-x-1 group-hover:text-blue-500"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M9 5l7 7-7 7"
                                            />
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
