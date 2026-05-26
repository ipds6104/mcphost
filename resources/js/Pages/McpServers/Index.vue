<script setup lang="ts">
import ChatSidebar from '@/Components/Chat/ChatSidebar.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, ref, watch } from 'vue';

interface Chat {
    id: string;
    title: string;
    is_pinned: boolean | number;
    created_at: string;
}

interface McpServer {
    id: string;
    name: string;
    transport: string;
    url: string;
    token: string | null;
    is_global: boolean;
    is_active: boolean;
    created_at: string;
}

const props = defineProps<{
    chats: Chat[];
    mcpServers: McpServer[];
    disableBuiltinMcp: boolean;
}>();

const disableBuiltinMcpState = ref(props.disableBuiltinMcp);

watch(
    () => props.disableBuiltinMcp,
    (newVal) => {
        disableBuiltinMcpState.value = newVal;
    },
);

const toggleBuiltinMcp = () => {
    disableBuiltinMcpState.value = !disableBuiltinMcpState.value;
    router.post(
        route('mcp-servers.toggle-builtin'),
        {
            disable_builtin: disableBuiltinMcpState.value,
        },
        {
            preserveScroll: true,
            onError: () => {
                disableBuiltinMcpState.value = !disableBuiltinMcpState.value;
            },
        },
    );
};

const isSidebarOpen = ref(true);
onMounted(() => {
    if (window.innerWidth < 768) {
        isSidebarOpen.value = false;
    }
});

// State untuk Modal & Pengeditan
const isCreating = ref(false);
const editingServer = ref<McpServer | null>(null);

// Form untuk Tambah Server
const createForm = useForm({
    name: '',
    url: '',
    token: '',
    is_active: true,
});

// Form untuk Edit Server
const editForm = useForm({
    name: '',
    url: '',
    token: '',
    is_active: true,
});

// Menguji Koneksi State
const testingServerId = ref<string | null>(null);
const testResult = ref<{
    success: boolean;
    message: string;
    details: string;
    tools?: string[];
} | null>(null);
const isTestModalOpen = ref(false);

const openCreateModal = () => {
    createForm.reset();
    isCreating.value = true;
};

const openEditModal = (server: McpServer) => {
    editingServer.value = server;
    editForm.name = server.name;
    editForm.url = server.url;
    editForm.token = server.token || '';
    editForm.is_active = server.is_active;
};

const submitCreate = () => {
    createForm.post(route('mcp-servers.store'), {
        onSuccess: () => {
            createForm.reset();
            isCreating.value = false;
        },
    });
};

const submitEdit = () => {
    if (!editingServer.value) return;
    editForm.put(route('mcp-servers.update', editingServer.value.id), {
        onSuccess: () => {
            editingServer.value = null;
        },
    });
};

const toggleActive = (server: McpServer) => {
    router.patch(
        route('mcp-servers.toggle', server.id),
        {},
        {
            preserveScroll: true,
        },
    );
};

const deleteServer = (server: McpServer) => {
    if (
        confirm(
            `Apakah Anda yakin ingin menghapus server MCP "${server.name}"? Asisten AI tidak akan lagi memiliki akses ke tools di dalam server ini.`,
        )
    ) {
        router.delete(route('mcp-servers.destroy', server.id), {
            preserveScroll: true,
        });
    }
};

const testConnection = async (server: McpServer) => {
    testingServerId.value = server.id;
    testResult.value = null;

    try {
        const response = await axios.post(route('mcp-servers.test', server.id));
        testResult.value = {
            success: response.data.success,
            message: response.data.message,
            details: response.data.details,
            tools: response.data.tools,
        };
    } catch (error: unknown) {
        const err = error as { response?: { data?: { details?: string } } };
        testResult.value = {
            success: false,
            message: 'Koneksi Gagal!',
            details:
                err.response?.data?.details ||
                'Terjadi kesalahan jaringan atau waktu habis saat menghubungi server MCP.',
        };
    } finally {
        testingServerId.value = null;
        isTestModalOpen.value = true;
    }
};
</script>

<template>
    <Head title="Kelola Server MCP BPS" />

    <AuthenticatedLayout hideNav>
        <div
            class="flex h-screen overflow-hidden bg-[#f0f4f9] font-sans dark:bg-[#131314]"
        >
            <!-- Sidebar Sesi Kiri -->
            <div
                class="fixed inset-y-0 left-0 z-40 flex shrink-0 transition-all duration-300 ease-in-out md:relative md:z-0 md:flex md:overflow-hidden md:shadow-none"
                :class="
                    isSidebarOpen
                        ? 'w-80 translate-x-0 shadow-2xl'
                        : 'w-80 -translate-x-full overflow-hidden md:w-0 md:translate-x-0'
                "
            >
                <div class="h-full w-80 shrink-0">
                    <ChatSidebar
                        :chats="chats"
                        :currentChat="null"
                        @close="isSidebarOpen = false"
                    />
                </div>
            </div>

            <!-- Mobile Sidebar Backdrop Overlay -->
            <div
                v-if="isSidebarOpen"
                class="fixed inset-0 z-30 bg-black/40 backdrop-blur-sm transition-opacity duration-300 md:hidden"
                @click="isSidebarOpen = false"
            />

            <!-- Viewport Konten Utama -->
            <div
                class="flex min-w-0 flex-1 flex-col overflow-y-auto bg-[#f0f4f9] dark:bg-[#131314]"
            >
                <!-- Header Atas Translucent -->
                <div
                    class="border-gray-250/20 z-10 flex h-16 shrink-0 select-none items-center justify-between border-b bg-gradient-to-b from-white/90 via-white/50 to-white/20 px-6 backdrop-blur-md dark:border-gray-800/20 dark:from-[#131314]/90 dark:via-[#131314]/50 dark:to-[#131314]/20"
                >
                    <div class="flex items-center gap-3">
                        <!-- Hamburger Menu Button -->
                        <button
                            v-if="!isSidebarOpen"
                            type="button"
                            @click="isSidebarOpen = true"
                            class="dark:hover:bg-gray-850/50 mr-1 rounded-full p-1.5 text-gray-500 transition hover:bg-gray-200/50"
                            title="Buka menu obrolan"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 8h16M4 16h12"
                                />
                            </svg>
                        </button>
                        <div>
                            <h2
                                class="font-sans text-xs font-extrabold uppercase tracking-wide text-gray-800 dark:text-white"
                            >
                                KELOLA SERVER MODEL CONTEXT PROTOCOL (MCP)
                            </h2>
                        </div>
                    </div>

                    <button
                        @click="openCreateModal"
                        class="transform rounded-full bg-gradient-to-tr from-blue-600 via-indigo-600 to-indigo-700 px-4 py-1.5 text-xs font-bold text-white shadow-md transition duration-200 hover:-translate-y-0.5 hover:shadow-lg focus:outline-none"
                    >
                        + Daftarkan Server Baru
                    </button>
                </div>

                <!-- Konten Inti Manajemen -->
                <div class="mx-auto w-full max-w-6xl space-y-6 p-6 md:p-8">
                    <!-- Penjelasan Fitur Box Premium -->
                    <div
                        class="rounded-2xl border border-blue-100/50 bg-gradient-to-br from-blue-50/60 to-indigo-50/40 p-6 shadow-sm dark:border-gray-800/50 dark:from-[#1e1f20]/50 dark:to-[#1e1f20]/20"
                    >
                        <h3
                            class="mb-2 flex items-center gap-2 text-sm font-bold text-gray-800 dark:text-gray-200"
                        >
                            <span
                                class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-500/10 text-blue-500"
                                >ℹ️</span
                            >
                            Tentang Server MCP Universal
                        </h3>
                        <p
                            class="text-xs leading-relaxed text-gray-600 dark:text-gray-400"
                        >
                            Model Context Protocol (MCP) memungkinkan agen AI
                            (asisten chatbot) menggunakan *tools* pengolah data
                            secara eksternal. Anda dapat mendaftarkan, mengedit,
                            dan menguji server SSE (Server-Sent Events) MCP
                            secara dinamis. Asisten obrolan akan langsung
                            mendeteksi perkakas dari seluruh server MCP
                            berstatus <strong>Aktif</strong>.
                        </p>
                    </div>

                    <!-- Mode Eksklusif MCP Dinamis Card (Premium Glassmorphic) -->
                    <div
                        class="flex flex-col gap-4 rounded-2xl border border-indigo-100/50 bg-gradient-to-r from-indigo-500/10 to-blue-500/5 p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-indigo-900/30 dark:from-indigo-950/20 dark:to-blue-950/10"
                    >
                        <div class="space-y-1">
                            <h4
                                class="flex items-center gap-1.5 text-xs font-extrabold uppercase tracking-wider text-indigo-600 dark:text-indigo-400"
                            >
                                🚀 Mode Pengujian Server MCP Dinamis Eksklusif
                            </h4>
                            <p
                                class="max-w-2xl text-[11px] leading-relaxed text-gray-600 dark:text-gray-400"
                            >
                                Aktifkan mode ini untuk menonaktifkan klien
                                bawaan (built-in) secara penuh. Asisten AI akan
                                100% bergantung pada Server MCP Universal
                                eksternal yang Anda daftarkan di bawah ini.
                                Sangat ideal untuk proses isolasi dan pengujian
                                MCP server pihak ketiga!
                            </p>
                        </div>
                        <div
                            class="flex shrink-0 items-center gap-3 self-start rounded-xl border border-gray-200/50 bg-white/40 px-4 py-2.5 backdrop-blur-sm sm:self-auto dark:border-gray-800/40 dark:bg-black/25"
                        >
                            <span
                                class="text-xs font-bold text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    disableBuiltinMcpState
                                        ? 'Mode Eksklusif Aktif'
                                        : 'Mode Eksklusif Nonaktif'
                                }}
                            </span>
                            <button
                                @click="toggleBuiltinMcp"
                                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                :class="
                                    disableBuiltinMcpState
                                        ? 'bg-indigo-600'
                                        : 'bg-gray-300 dark:bg-gray-700'
                                "
                            >
                                <span
                                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                    :class="
                                        disableBuiltinMcpState
                                            ? 'translate-x-5'
                                            : 'translate-x-0'
                                    "
                                />
                            </button>
                        </div>
                    </div>

                    <!-- Tabel Daftar Server MCP -->
                    <div
                        class="overflow-hidden rounded-2xl border border-gray-200/50 bg-white shadow-sm dark:border-gray-800/50 dark:bg-[#1e1f20]"
                    >
                        <div
                            class="flex items-center justify-between border-b border-gray-100 p-5 dark:border-gray-800/80"
                        >
                            <h3
                                class="text-xs font-extrabold uppercase tracking-wider text-gray-400"
                            >
                                Daftar Server MCP Terdaftar
                            </h3>
                            <span
                                class="rounded-full bg-gray-100 px-2.5 py-0.5 text-[10px] font-bold text-gray-500 dark:bg-gray-800 dark:text-gray-400"
                            >
                                {{ mcpServers.length }} Total Server
                            </span>
                        </div>

                        <!-- Empty State -->
                        <div
                            v-if="mcpServers.length === 0"
                            class="p-12 text-center"
                        >
                            <svg
                                class="mx-auto mb-4 h-12 w-12 text-gray-300 dark:text-gray-700"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.5"
                                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                                />
                            </svg>
                            <h4
                                class="mb-1 text-sm font-bold text-gray-800 dark:text-gray-200"
                            >
                                Belum Ada Server MCP
                            </h4>
                            <p
                                class="mx-auto mb-4 max-w-sm text-xs text-gray-500"
                            >
                                Daftarkan koneksi Server MCP SSE pertama Anda
                                untuk memperkaya kemampuan analitik asisten AI.
                            </p>
                            <button
                                @click="openCreateModal"
                                class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow hover:bg-blue-700"
                            >
                                Mulai Daftarkan
                            </button>
                        </div>

                        <!-- Data Table -->
                        <div v-else class="overflow-x-auto">
                            <table class="w-full border-collapse text-left">
                                <thead>
                                    <tr
                                        class="border-b border-gray-100 bg-gray-50/50 dark:border-gray-800 dark:bg-[#151617]"
                                    >
                                        <th
                                            class="p-4 text-[10px] font-bold uppercase tracking-wider text-gray-400"
                                        >
                                            Status
                                        </th>
                                        <th
                                            class="p-4 text-[10px] font-bold uppercase tracking-wider text-gray-400"
                                        >
                                            Nama Server
                                        </th>
                                        <th
                                            class="p-4 text-[10px] font-bold uppercase tracking-wider text-gray-400"
                                        >
                                            Transport / URL
                                        </th>
                                        <th
                                            class="p-4 text-[10px] font-bold uppercase tracking-wider text-gray-400"
                                        >
                                            Token Akses
                                        </th>
                                        <th
                                            class="p-4 text-center text-[10px] font-bold uppercase tracking-wider text-gray-400"
                                        >
                                            Aksi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="divide-y divide-gray-100 dark:divide-gray-800/80"
                                >
                                    <tr
                                        v-for="server in mcpServers"
                                        :key="server.id"
                                        class="transition duration-150 hover:bg-gray-50/30 dark:hover:bg-[#252627]/30"
                                    >
                                        <!-- Status Active Toggle Column -->
                                        <td class="p-4 align-middle">
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <button
                                                    @click="
                                                        toggleActive(server)
                                                    "
                                                    class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                                    :class="
                                                        server.is_active
                                                            ? 'bg-green-500'
                                                            : 'bg-gray-300 dark:bg-gray-700'
                                                    "
                                                >
                                                    <span
                                                        class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                                        :class="
                                                            server.is_active
                                                                ? 'translate-x-4'
                                                                : 'translate-x-0'
                                                        "
                                                    />
                                                </button>
                                                <!-- Vibrant Neon Indicator Bulb -->
                                                <span
                                                    class="relative flex h-2 w-2"
                                                >
                                                    <span
                                                        v-if="server.is_active"
                                                        class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"
                                                    ></span>
                                                    <span
                                                        class="relative inline-flex h-2 w-2 rounded-full"
                                                        :class="
                                                            server.is_active
                                                                ? 'bg-green-500'
                                                                : 'bg-red-500'
                                                        "
                                                    ></span>
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Name Column -->
                                        <td class="p-4 align-middle">
                                            <div
                                                class="font-sans text-xs font-bold text-gray-800 dark:text-gray-200"
                                            >
                                                {{ server.name }}
                                            </div>
                                            <div
                                                class="text-[10px] text-gray-400 dark:text-gray-500"
                                            >
                                                {{
                                                    server.is_global
                                                        ? 'Global Server'
                                                        : 'User Server'
                                                }}
                                            </div>
                                        </td>

                                        <!-- Transport & URL Column -->
                                        <td
                                            class="p-4 align-middle font-mono text-[11px] text-gray-600 dark:text-gray-400"
                                        >
                                            <div
                                                class="flex items-center gap-1.5"
                                            >
                                                <span
                                                    class="rounded bg-blue-50 px-1.5 py-0.5 text-[9px] font-bold uppercase text-blue-600 dark:bg-blue-950/40 dark:text-blue-400"
                                                >
                                                    {{ server.transport }}
                                                </span>
                                                <span
                                                    class="max-w-xs truncate"
                                                    :title="server.url"
                                                >
                                                    {{ server.url }}
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Access Token Column -->
                                        <td class="p-4 align-middle">
                                            <span
                                                v-if="server.token"
                                                class="rounded bg-gray-50 px-2 py-0.5 font-mono text-xs text-gray-500 dark:bg-gray-800 dark:text-gray-500"
                                                title="Token tersembunyi demi keamanan"
                                            >
                                                ••••••••••••
                                            </span>
                                            <span
                                                v-else
                                                class="text-[10px] italic text-gray-400 dark:text-gray-600"
                                            >
                                                Tanpa Token
                                            </span>
                                        </td>

                                        <!-- Action Column -->
                                        <td
                                            class="p-4 text-center align-middle"
                                        >
                                            <div
                                                class="flex items-center justify-center gap-2"
                                            >
                                                <!-- Test Connection Action -->
                                                <button
                                                    @click="
                                                        testConnection(server)
                                                    "
                                                    :disabled="
                                                        testingServerId !== null
                                                    "
                                                    class="inline-flex items-center gap-1 rounded bg-blue-50 px-2.5 py-1 text-[10px] font-extrabold text-blue-600 transition hover:bg-blue-100 dark:bg-blue-950/30 dark:text-blue-400 dark:hover:bg-blue-900/30"
                                                    title="Uji Koneksi SSE Server"
                                                >
                                                    <svg
                                                        v-if="
                                                            testingServerId ===
                                                            server.id
                                                        "
                                                        class="h-3.5 w-3.5 animate-spin"
                                                        fill="none"
                                                        viewBox="0 0 24 24"
                                                    >
                                                        <circle
                                                            class="opacity-25"
                                                            cx="12"
                                                            cy="12"
                                                            r="10"
                                                            stroke="currentColor"
                                                            stroke-width="4"
                                                        ></circle>
                                                        <path
                                                            class="opacity-75"
                                                            fill="currentColor"
                                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                                        ></path>
                                                    </svg>
                                                    <span>{{
                                                        testingServerId ===
                                                        server.id
                                                            ? 'Menguji...'
                                                            : 'Uji Koneksi'
                                                    }}</span>
                                                </button>

                                                <!-- Edit Server Action -->
                                                <button
                                                    @click="
                                                        openEditModal(server)
                                                    "
                                                    class="rounded p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                                                    title="Edit Server"
                                                >
                                                    <svg
                                                        class="h-4 w-4"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        viewBox="0 0 24 24"
                                                    >
                                                        <path
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                                                        />
                                                    </svg>
                                                </button>

                                                <!-- Delete Action -->
                                                <button
                                                    @click="
                                                        deleteServer(server)
                                                    "
                                                    class="rounded p-1 text-gray-400 transition hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-950/20 dark:hover:text-red-400"
                                                    title="Hapus Server"
                                                >
                                                    <svg
                                                        class="h-4 w-4"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        viewBox="0 0 24 24"
                                                    >
                                                        <path
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                                        />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- MODAL: DAFTAR SERVER MCP BARU -->
    <div
        v-if="isCreating"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
    >
        <div
            class="border-gray-150 animate-in fade-in zoom-in-95 w-full max-w-md rounded-2xl border bg-white p-6 shadow-2xl duration-200 dark:border-gray-700 dark:bg-gray-800"
        >
            <h3 class="mb-4 text-base font-bold text-gray-900 dark:text-white">
                Daftarkan Server MCP Baru
            </h3>
            <form @submit.prevent="submitCreate">
                <div class="mb-5 space-y-4">
                    <div>
                        <label
                            class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500"
                            >Nama Server</label
                        >
                        <input
                            v-model="createForm.name"
                            type="text"
                            placeholder="Contoh: BPS Agentic Docker"
                            class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            required
                        />
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500"
                            >SSE URL Endpoint</label
                        >
                        <input
                            v-model="createForm.url"
                            type="url"
                            placeholder="Contoh: http://bps-mcp-server:3000/sse"
                            class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 font-mono text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            required
                        />
                        <span class="mt-1 block text-[9px] text-gray-400"
                            >Untuk Docker internal gunakan format:
                            <code
                                >http://[container-name]:[port]/sse</code
                            ></span
                        >
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500"
                            >Token Akses (Opsional)</label
                        >
                        <input
                            v-model="createForm.token"
                            type="text"
                            placeholder="Masukkan token akses jika server terproteksi"
                            class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 font-mono text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        />
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <input
                            id="create_is_active"
                            type="checkbox"
                            v-model="createForm.is_active"
                            class="h-4 w-4 rounded border-gray-300 bg-gray-50 text-blue-600 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900"
                        />
                        <label
                            for="create_is_active"
                            class="select-none text-xs font-semibold text-gray-700 dark:text-gray-300"
                            >Aktifkan server ini seketika</label
                        >
                    </div>
                </div>

                <div
                    class="flex justify-end gap-3 border-t border-gray-100 pt-4 dark:border-gray-700/80"
                >
                    <button
                        type="button"
                        @click="isCreating = false"
                        class="dark:bg-gray-750 rounded-lg bg-gray-100 px-4 py-2 text-xs font-semibold text-gray-600 transition hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="createForm.processing"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow hover:bg-blue-700 disabled:opacity-50"
                    >
                        Daftarkan Server
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: EDIT SERVER MCP -->
    <div
        v-if="editingServer !== null"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
    >
        <div
            class="border-gray-150 animate-in fade-in zoom-in-95 w-full max-w-md rounded-2xl border bg-white p-6 shadow-2xl duration-200 dark:border-gray-700 dark:bg-gray-800"
        >
            <h3 class="mb-4 text-base font-bold text-gray-900 dark:text-white">
                Edit Konfigurasi Server MCP
            </h3>
            <form @submit.prevent="submitEdit">
                <div class="mb-5 space-y-4">
                    <div>
                        <label
                            class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500"
                            >Nama Server</label
                        >
                        <input
                            v-model="editForm.name"
                            type="text"
                            placeholder="Contoh: BPS Agentic Docker"
                            class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            required
                        />
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500"
                            >SSE URL Endpoint</label
                        >
                        <input
                            v-model="editForm.url"
                            type="url"
                            placeholder="Contoh: http://bps-mcp-server:3000/sse"
                            class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 font-mono text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            required
                        />
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500"
                            >Token Akses (Opsional)</label
                        >
                        <input
                            v-model="editForm.token"
                            type="text"
                            placeholder="Masukkan token akses jika server terproteksi"
                            class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 font-mono text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        />
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <input
                            id="edit_is_active"
                            type="checkbox"
                            v-model="editForm.is_active"
                            class="h-4 w-4 rounded border-gray-300 bg-gray-50 text-blue-600 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900"
                        />
                        <label
                            for="edit_is_active"
                            class="select-none text-xs font-semibold text-gray-700 dark:text-gray-300"
                            >Aktifkan server ini</label
                        >
                    </div>
                </div>

                <div
                    class="flex justify-end gap-3 border-t border-gray-100 pt-4 dark:border-gray-700/80"
                >
                    <button
                        type="button"
                        @click="editingServer = null"
                        class="dark:bg-gray-750 rounded-lg bg-gray-100 px-4 py-2 text-xs font-semibold text-gray-600 transition hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="editForm.processing"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow hover:bg-blue-700 disabled:opacity-50"
                    >
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: HASIL UJI KONEKSI (TEST CONNECTION RESULTS) -->
    <div
        v-if="isTestModalOpen && testResult !== null"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
    >
        <div
            class="border-gray-150 animate-in fade-in zoom-in-95 w-full max-w-md rounded-2xl border bg-white p-6 shadow-2xl duration-200 dark:border-gray-700 dark:bg-gray-800"
        >
            <div class="mb-4 text-center">
                <!-- Big Success/Failed Icon with pulse glow -->
                <div
                    class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full shadow-md"
                    :class="
                        testResult.success
                            ? 'bg-green-100 text-green-600 dark:bg-green-950/40 dark:text-green-400'
                            : 'bg-red-100 text-red-600 dark:bg-red-950/40 dark:text-red-400'
                    "
                >
                    <svg
                        v-if="testResult.success"
                        class="h-6 w-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2.5"
                            d="M5 13l4 4L19 7"
                        />
                    </svg>
                    <svg
                        v-else
                        class="h-6 w-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2.5"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">
                    {{ testResult.message }}
                </h3>
                <p class="mt-1 text-xs leading-relaxed text-gray-500">
                    {{ testResult.details }}
                </p>
            </div>

            <!-- List of Tools if successful -->
            <div
                v-if="
                    testResult.success &&
                    testResult.tools &&
                    testResult.tools.length > 0
                "
                class="mb-5 rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-800/80 dark:bg-gray-900"
            >
                <div
                    class="mb-2 flex items-center justify-between text-[10px] font-bold uppercase tracking-wider text-gray-400"
                >
                    <span>🛠️ Perkakas yang Terdeteksi (Tools)</span>
                    <span
                        class="rounded bg-green-100 px-2 py-0.5 text-[9px] font-extrabold text-green-700 dark:bg-green-950/60 dark:text-green-400"
                        >{{ testResult.tools.length }} Tools</span
                    >
                </div>
                <div
                    class="scrollbar-thin max-h-36 space-y-1 overflow-y-auto pr-1"
                >
                    <div
                        v-for="tool in testResult.tools"
                        :key="tool"
                        class="flex items-center gap-1.5 font-mono text-xs text-gray-700 dark:text-gray-300"
                    >
                        <span class="text-green-500">•</span>
                        <span>{{ tool }}</span>
                    </div>
                </div>
            </div>

            <div
                class="flex justify-end border-t border-gray-100 pt-4 dark:border-gray-700/80"
            >
                <button
                    type="button"
                    @click="isTestModalOpen = false"
                    class="dark:bg-gray-750 w-full rounded-lg bg-gray-100 px-4 py-2.5 text-xs font-bold text-gray-700 transition hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700"
                >
                    Selesai
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.scrollbar-thin::-webkit-scrollbar {
    width: 4px;
}
.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
    background: rgba(156, 163, 175, 0.2);
    border-radius: 9999px;
}
.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background: rgba(156, 163, 175, 0.4);
}
</style>
