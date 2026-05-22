<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface Chat {
    id: string;
    title: string;
    is_pinned: boolean | number;
    created_at: string;
}

const props = defineProps<{
    chats: Chat[];
    currentChat: Chat | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

// State untuk pengeditan topik obrolan secara inline
const editingChatId = ref<string | null>(null);
const renameForm = useForm({
    title: '',
});

const startRename = (chat: Chat) => {
    editingChatId.value = chat.id;
    renameForm.title = chat.title;
};

const saveRename = (chat: Chat) => {
    if (!renameForm.title.trim()) return;
    renameForm.patch(route('chats.rename', chat.id), {
        onSuccess: () => {
            editingChatId.value = null;
        },
    });
};

const cancelRename = () => {
    editingChatId.value = null;
};

const togglePin = (chat: Chat) => {
    router.post(route('chats.toggle-pin', chat.id));
};

const deleteChat = (chat: Chat) => {
    if (
        confirm(
            'Apakah Anda yakin ingin menghapus sesi obrolan ini? Semua riwayat analisis di dalamnya akan hilang selamanya.',
        )
    ) {
        router.delete(route('chats.destroy', chat.id));
    }
};

// Directive lokal untuk memfokuskan input edit topik secara otomatis
const vFocus = {
    mounted: (el: HTMLInputElement) => el.focus(),
};

// State pencarian sesi obrolan
const searchQuery = ref('');
const filteredChats = computed(() => {
    if (!searchQuery.value.trim()) return props.chats;
    return props.chats.filter((c) =>
        c.title.toLowerCase().includes(searchQuery.value.toLowerCase()),
    );
});

// Rekalkulasi grup berdasarkan hasil pencarian
const filteredGroupedChats = computed(() => {
    const groups: {
        pinned: Chat[];
        today: Chat[];
        yesterday: Chat[];
        older: Chat[];
    } = {
        pinned: [],
        today: [],
        yesterday: [],
        older: [],
    };

    const todayStart = new Date();
    todayStart.setHours(0, 0, 0, 0);

    const yesterdayStart = new Date(todayStart);
    yesterdayStart.setDate(yesterdayStart.getDate() - 1);

    filteredChats.value.forEach((chat) => {
        if (chat.is_pinned) {
            groups.pinned.push(chat);
        } else {
            const chatDate = new Date(chat.created_at);
            if (chatDate >= todayStart) {
                groups.today.push(chat);
            } else if (chatDate >= yesterdayStart) {
                groups.yesterday.push(chat);
            } else {
                groups.older.push(chat);
            }
        }
    });

    return groups;
});

// State dan penanganan dropdown profil/pengaturan
const showSettingsDropdown = ref(false);
const closeSettingsDropdown = () => {
    showSettingsDropdown.value = false;
};

onMounted(() => {
    window.addEventListener('click', closeSettingsDropdown);
});

onUnmounted(() => {
    window.removeEventListener('click', closeSettingsDropdown);
});
</script>

<template>
    <div
        class="flex h-full w-full select-none flex-col bg-[#f0f4f9] dark:bg-[#0e0e10]"
    >
        <!-- Sidebar Brand Header -->
        <div class="flex items-center justify-between px-5 py-4">
            <div class="flex items-center gap-2">
                <!-- BPS Sparkle Icon -->
                <div
                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-tr from-blue-500 via-indigo-500 to-pink-500 text-white shadow-md"
                >
                    <svg
                        class="h-4 w-4 animate-pulse"
                        fill="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            d="M12 2L14.7 9.3L22 12L14.7 14.7L12 22L9.3 14.7L2 12L9.3 9.3L12 2Z"
                        />
                    </svg>
                </div>
                <span
                    class="bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text font-sans text-sm font-extrabold tracking-tight text-transparent dark:from-blue-400 dark:to-cyan-400"
                >
                    BPS MCP AI
                </span>
            </div>

            <!-- Collapse Button (Decorative / Trigger Menu) -->
            <button
                class="rounded-full p-1.5 text-gray-500 transition hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-800"
                title="Sembunyikan Sidebar"
                @click="emit('close')"
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
        </div>

        <!-- New Chat Button ("Percakapan baru" standard Gemini) -->
        <div class="px-4 py-2">
            <Link
                :href="route('dashboard')"
                class="group flex items-center gap-3 rounded-full bg-[#e3e3e3]/50 px-4 py-3 text-xs font-semibold text-gray-800 shadow-sm transition hover:bg-[#e3e3e3] dark:bg-[#1e1f20]/60 dark:text-gray-200 dark:hover:bg-[#1e1f20]"
            >
                <svg
                    class="h-4 w-4 text-gray-600 transition-transform group-hover:rotate-12 dark:text-gray-300"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                    />
                </svg>
                <span>Percakapan baru</span>
            </Link>
        </div>

        <!-- Search input ("Telusuri percakapan" standard Gemini) -->
        <div class="px-4 py-2">
            <div class="relative flex items-center">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Telusuri percakapan..."
                    class="w-full rounded-full border-0 bg-white/70 py-2 pl-9 pr-4 text-xs text-gray-800 placeholder-gray-400 shadow-sm transition focus:outline-none focus:ring-1 focus:ring-blue-500/30 dark:bg-[#1e1f20]/40 dark:text-gray-200 dark:placeholder-gray-500"
                />
                <svg
                    class="absolute left-3 h-4 w-4 text-gray-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                    />
                </svg>
            </div>
        </div>

        <!-- Scrollable Chats List -->
        <div class="scrollbar-thin flex-1 space-y-4 overflow-y-auto px-2 py-3">
            <div
                v-for="(groupChats, groupName) in filteredGroupedChats"
                :key="groupName"
            >
                <div v-if="groupChats.length > 0">
                    <!-- Group Title (Terbaru / Disematkan) -->
                    <div
                        class="mb-1 flex items-center gap-1.5 px-3 font-sans text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500"
                    >
                        <svg
                            v-if="groupName === 'pinned'"
                            class="h-3 w-3 shrink-0 text-amber-500"
                            fill="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"
                            />
                        </svg>
                        <span>
                            {{
                                groupName === 'pinned'
                                    ? 'Disematkan'
                                    : groupName === 'today'
                                      ? 'Hari Ini'
                                      : groupName === 'yesterday'
                                        ? 'Kemarin'
                                        : 'Sebelumnya'
                            }}
                        </span>
                    </div>

                    <!-- Chat Items list -->
                    <div class="space-y-0.5">
                        <div
                            v-for="chat in groupChats"
                            :key="chat.id"
                            :class="[
                                'group relative flex select-text items-center rounded-full px-3 py-2 transition-all duration-200',
                                chat.id === currentChat?.id
                                    ? 'bg-[#e3e3e3] font-semibold text-gray-900 dark:bg-[#1e1f20] dark:text-white'
                                    : 'text-gray-700 hover:bg-gray-200/50 dark:text-gray-300 dark:hover:bg-[#1e1f20]/30',
                            ]"
                        >
                            <!-- Link Sesi -->
                            <Link
                                v-if="editingChatId !== chat.id"
                                :href="route('chats.show', chat.id)"
                                class="flex min-w-0 flex-1 items-center gap-2.5 pr-10"
                            >
                                <svg
                                    :class="[
                                        'h-4 w-4 shrink-0 transition-colors',
                                        chat.id === currentChat?.id
                                            ? 'text-blue-500 dark:text-blue-400'
                                            : 'text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-200',
                                    ]"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                                    />
                                </svg>
                                <span class="truncate font-sans text-xs">
                                    {{ chat.title }}
                                </span>
                            </Link>

                            <!-- Form Edit Judul Inline -->
                            <div
                                v-else
                                class="flex min-w-0 flex-1 items-center gap-1.5 pr-2"
                            >
                                <input
                                    v-model="renameForm.title"
                                    type="text"
                                    class="w-full rounded-full border border-gray-300 bg-white px-2.5 py-0.5 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                    @keydown.enter="saveRename(chat)"
                                    @keydown.esc="cancelRename"
                                    v-focus
                                />
                                <button
                                    @click="saveRename(chat)"
                                    class="shrink-0 rounded-full p-1 text-green-600 hover:bg-green-100 dark:hover:bg-green-900/30"
                                >
                                    <svg
                                        class="h-3.5 w-3.5"
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
                                </button>
                                <button
                                    @click="cancelRename"
                                    class="shrink-0 rounded-full p-1 text-red-500 hover:bg-red-100 dark:hover:bg-red-900/30"
                                >
                                    <svg
                                        class="h-3.5 w-3.5"
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
                                </button>
                            </div>

                            <!-- Menu Tombol Aksi (Hanya muncul saat hover) -->
                            <div
                                v-if="editingChatId !== chat.id"
                                class="absolute right-2 top-1/2 flex -translate-y-1/2 items-center gap-0.5 pl-3 opacity-0 transition-opacity duration-150 group-hover:opacity-100"
                            >
                                <!-- Tombol Semat/Pin -->
                                <button
                                    @click.stop="togglePin(chat)"
                                    :class="[
                                        'shrink-0 rounded-full p-1 transition hover:bg-gray-200 dark:hover:bg-gray-800',
                                        chat.is_pinned
                                            ? 'text-amber-500'
                                            : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-200',
                                    ]"
                                    :title="
                                        chat.is_pinned
                                            ? 'Lepas Sematan'
                                            : 'Sematkan Sesi'
                                    "
                                >
                                    <svg
                                        class="h-3.5 w-3.5"
                                        fill="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"
                                        />
                                    </svg>
                                </button>

                                <!-- Tombol Rename -->
                                <button
                                    @click.stop="startRename(chat)"
                                    class="shrink-0 rounded-full p-1 text-gray-400 transition hover:bg-gray-200 hover:text-blue-500 dark:hover:bg-gray-800"
                                    title="Ubah Nama"
                                >
                                    <svg
                                        class="h-3.5 w-3.5"
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

                                <!-- Tombol Hapus -->
                                <button
                                    @click.stop="deleteChat(chat)"
                                    class="shrink-0 rounded-full p-1 text-gray-400 transition hover:bg-gray-200 hover:text-red-500 dark:hover:bg-gray-800"
                                    title="Hapus Sesi"
                                >
                                    <svg
                                        class="h-3.5 w-3.5"
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
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profil Pengguna & Pengaturan di Bagian Bawah -->
        <div
            class="border-gray-250/20 relative border-t bg-white/40 p-4 dark:border-gray-800/20 dark:bg-[#0e0e10]/60"
        >
            <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <!-- User Avatar -->
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-tr from-blue-600 to-indigo-600 text-xs font-bold text-white shadow-sm"
                    >
                        {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                    </div>
                    <!-- User Name & Email -->
                    <div class="min-w-0">
                        <p
                            class="truncate text-xs font-semibold text-gray-800 dark:text-gray-200"
                        >
                            {{ $page.props.auth.user.name }}
                        </p>
                        <p
                            class="truncate text-[10px] text-gray-400 dark:text-gray-500"
                        >
                            {{ $page.props.auth.user.email }}
                        </p>
                    </div>
                </div>

                <!-- Tombol Settings & Dropdown ke Atas -->
                <div class="relative">
                    <button
                        type="button"
                        @click.stop="
                            showSettingsDropdown = !showSettingsDropdown
                        "
                        class="rounded-full p-1.5 text-gray-500 hover:bg-gray-200 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                        title="Buka Pengaturan"
                    >
                        <svg
                            class="h-4.5 w-4.5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                            />
                        </svg>
                    </button>

                    <!-- Upward Dropdown Menu -->
                    <Transition
                        enter-active-class="transition ease-out duration-200"
                        enter-from-class="opacity-0 scale-95 translate-y-2"
                        enter-to-class="opacity-100 scale-100 translate-y-0"
                        leave-active-class="transition ease-in duration-75"
                        leave-from-class="opacity-100 scale-100 translate-y-0"
                        leave-to-class="opacity-0 scale-95 translate-y-2"
                    >
                        <div
                            v-if="showSettingsDropdown"
                            class="absolute bottom-full right-0 z-50 mb-2 w-48 rounded-xl border border-gray-200/50 bg-white py-1.5 shadow-lg ring-1 ring-black/5 dark:border-gray-800/60 dark:bg-[#1e1f20]"
                        >
                            <Link
                                :href="route('profile.edit')"
                                class="flex w-full items-center gap-2 px-3 py-2 text-left font-sans text-xs text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800/50"
                            >
                                <svg
                                    class="h-3.5 w-3.5 text-gray-400"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                    />
                                </svg>
                                <span>Pengaturan Profil</span>
                            </Link>
                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="flex w-full items-center gap-2 px-3 py-2 text-left font-sans text-xs text-red-600 hover:bg-gray-50 dark:text-red-400 dark:hover:bg-gray-800/50"
                            >
                                <svg
                                    class="h-3.5 w-3.5 text-red-400"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                                    />
                                </svg>
                                <span>Keluar Sesi</span>
                            </Link>
                        </div>
                    </Transition>
                </div>
            </div>
        </div>
    </div>
</template>

<!-- Custom styling for thin scrollbars -->
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
