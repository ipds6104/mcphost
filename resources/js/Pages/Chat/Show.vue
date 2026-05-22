<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { nextTick, onMounted, onUnmounted, ref, computed } from 'vue';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const props = defineProps({
    chats: {
        type: Array,
        default: () => [],
    },
    currentChat: {
        type: Object,
        required: true,
    },
    messages: {
        type: Array,
        default: () => [],
    },
});

// State untuk pengeditan topik obrolan secara inline
const editingChatId = ref(null);
const renameForm = useForm({
    title: '',
});

const startRename = (chat) => {
    editingChatId.value = chat.id;
    renameForm.title = chat.title;
};

const saveRename = (chat) => {
    if (!renameForm.title.trim()) return;
    renameForm.patch(route('chats.rename', chat.id), {
        onSuccess: () => {
            editingChatId.value = null;
        }
    });
};

const cancelRename = () => {
    editingChatId.value = null;
};

const togglePin = (chat) => {
    router.post(route('chats.toggle-pin', chat.id));
};

const deleteChat = (chat) => {
    if (confirm('Apakah Anda yakin ingin menghapus sesi obrolan ini? Semua riwayat analisis di dalamnya akan hilang selamanya.')) {
        router.delete(route('chats.destroy', chat.id));
    }
};

// Pengelompokkan obrolan secara kronologis dan berdasar sematan (pinned)
const groupedChats = computed(() => {
    const groups = {
        pinned: [],
        today: [],
        yesterday: [],
        older: []
    };

    const todayStart = new Date();
    todayStart.setHours(0, 0, 0, 0);

    const yesterdayStart = new Date(todayStart);
    yesterdayStart.setDate(yesterdayStart.getDate() - 1);

    props.chats.forEach(chat => {
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

// Directive lokal untuk memfokuskan input edit topik secara otomatis
const vFocus = {
    mounted: (el) => el.focus()
};

const localMessages = ref([...props.messages]);
const imagePreviews = ref([]);
const fileInput = ref(null);
const messageContainer = ref(null);
const activeStepDetail = ref(null);

const form = useForm({
    content: '',
    images: [],
});

const triggerImageUpload = () => {
    fileInput.value.click();
};

const handleImageChange = (e) => {
    const files = Array.from(e.target.files);
    files.forEach((file) => {
        if (!file.type.startsWith('image/')) return;
        form.images.push(file);

        const reader = new FileReader();
        reader.onload = (event) => {
            imagePreviews.value.push({
                file,
                url: event.target.result,
            });
        };
        reader.readAsDataURL(file);
    });
};

const removeImagePreview = (index) => {
    form.images.splice(index, 1);
    imagePreviews.value.splice(index, 1);
};

const scrollToBottom = () => {
    nextTick(() => {
        if (messageContainer.value) {
            messageContainer.value.scrollTop = messageContainer.value.scrollHeight;
        }
    });
};

const sendMessage = () => {
    if (!form.content.trim() && form.images.length === 0) return;

    // Tambahkan pesan user secara instan untuk UX instan yang responsif
    const tempUserMsgId = Date.now();
    localMessages.value.push({
        id: tempUserMsgId,
        role: 'user',
        content: form.content,
        attachments: imagePreviews.value.map(p => p.url),
        created_at: new Date().toISOString(),
    });

    // Tampilkan loading spinner asisten
    const tempAssistantMsgId = tempUserMsgId + 1;
    localMessages.value.push({
        id: tempAssistantMsgId,
        role: 'assistant',
        content: '',
        is_loading: true,
        agent_steps: [],
        created_at: new Date().toISOString(),
    });

    scrollToBottom();

    // Kirim request ke backend
    form.post(route('chats.messages.store', props.currentChat.id), {
        onSuccess: () => {
            form.reset();
            imagePreviews.value = [];
        },
        onError: () => {
            // Hapus pesan jika gagal
            localMessages.value = localMessages.value.filter(m => m.id !== tempUserMsgId && m.id !== tempAssistantMsgId);
        }
    });
};

// Hubungkan ke Laravel Reverb secara 100% self-hosted
let echoInstance = null;

onMounted(() => {
    scrollToBottom();

    window.Pusher = Pusher;

    echoInstance = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    echoInstance.private(`chats.${props.currentChat.id}`)
        .listen('AgentStepStarted', (event) => {
            // Perbarui step started di loading message
            const loadingMsg = localMessages.value.find(m => m.is_loading === true);
            if (loadingMsg) {
                if (!loadingMsg.agent_steps) {
                    loadingMsg.agent_steps = [];
                }
                loadingMsg.agent_steps.push({
                    step: event.stepIndex,
                    tool: event.toolName,
                    status: 'running',
                });
                scrollToBottom();
            }
        })
        .listen('AgentStepCompleted', (event) => {
            // Perbarui step completed di loading message
            const loadingMsg = localMessages.value.find(m => m.is_loading === true);
            if (loadingMsg) {
                const step = loadingMsg.agent_steps.find(s => s.step === event.stepIndex);
                if (step) {
                    step.status = 'success';
                    step.result = event.result;
                }
            }
        })
        .listen('AgentResponseGenerated', (event) => {
            // Ganti loading message dengan response asisten final
            const loadingIdx = localMessages.value.findIndex(m => m.is_loading === true);
            if (loadingIdx !== -1) {
                localMessages.value.splice(loadingIdx, 1);
            }

            localMessages.value.push({
                id: event.messageId,
                role: 'assistant',
                content: event.content,
                chart_data: event.chartData,
                agent_steps: localMessages.value[loadingIdx]?.agent_steps ?? [],
                created_at: new Date().toISOString(),
            });

            scrollToBottom();
        });
});

onUnmounted(() => {
    if (echoInstance) {
        echoInstance.leave(`chats.${props.currentChat.id}`);
    }
});
</script>

<template>
    <Head :title="currentChat.title" />

    <AuthenticatedLayout>
        <div class="h-[calc(100vh-64px)] flex bg-gray-50 dark:bg-gray-900 overflow-hidden">
            <!-- Sidebar Sesi Kiri -->
            <div class="w-80 border-r border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-850 flex flex-col shrink-0 hidden md:flex">
                <div class="p-4 border-b border-gray-150 dark:border-gray-800 flex justify-between items-center">
                    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                        Sesi Analisis
                    </h3>
                    <Link
                        :href="route('dashboard')"
                        class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg text-gray-500 transition"
                        title="Buat Sesi Baru"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </Link>
                </div>
                <div class="flex-1 overflow-y-auto p-3 space-y-4">
                    <!-- Render Grouped Chats -->
                    <div v-for="(groupChats, groupName) in groupedChats" :key="groupName">
                        <div v-if="groupChats.length > 0">
                            <!-- Group Title -->
                            <div class="px-3 mb-1.5 text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider flex items-center gap-1.5">
                                <svg v-if="groupName === 'pinned'" class="w-3 h-3 text-amber-500 shrink-0 animate-bounce" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/>
                                </svg>
                                <span>
                                    {{ 
                                        groupName === 'pinned' ? 'Disematkan' : 
                                        groupName === 'today' ? 'Hari Ini' : 
                                        groupName === 'yesterday' ? 'Kemarin' : 'Sebelumnya'
                                    }}
                                </span>
                            </div>

                            <!-- Grouped Chat Items -->
                            <div class="space-y-1">
                                <div
                                    v-for="chat in groupChats"
                                    :key="chat.id"
                                    :class="[
                                        'flex items-center px-3 py-2.5 rounded-xl transition text-left group relative',
                                        chat.id === currentChat.id
                                            ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 font-semibold'
                                            : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800'
                                    ]"
                                >
                                    <!-- Link Sesi -->
                                    <Link
                                        v-if="editingChatId !== chat.id"
                                        :href="route('chats.show', chat.id)"
                                        class="flex items-center gap-2 flex-1 min-w-0 pr-8"
                                    >
                                        <svg
                                            :class="[
                                                'w-4 h-4 shrink-0',
                                                chat.id === currentChat.id ? 'text-blue-500' : 'text-gray-400 group-hover:text-blue-500'
                                            ]"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                        </svg>
                                        <span class="truncate text-xs flex-1">
                                            {{ chat.title }}
                                        </span>
                                    </Link>

                                    <!-- Form Edit Judul Inline -->
                                    <div v-else class="flex items-center gap-1.5 flex-1 min-w-0">
                                        <input
                                            v-model="renameForm.title"
                                            type="text"
                                            class="w-full px-2 py-1 text-xs bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-650 rounded-lg focus:ring-1 focus:ring-blue-500 focus:outline-none text-gray-900 dark:text-white"
                                            @keydown.enter="saveRename(chat)"
                                            @keydown.esc="cancelRename"
                                            v-focus
                                        />
                                        <button @click="saveRename(chat)" class="p-1 hover:bg-green-50 dark:hover:bg-green-900/30 text-green-600 rounded shrink-0">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </button>
                                        <button @click="cancelRename" class="p-1 hover:bg-red-50 dark:hover:bg-red-900/30 text-red-500 rounded shrink-0">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>

                                    <!-- Menu Tombol Aksi (Hanya muncul saat hover) -->
                                    <div v-if="editingChatId !== chat.id" class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity bg-gradient-to-l from-white via-white dark:from-gray-850 dark:via-gray-850 pl-3">
                                        <!-- Tombol Semat/Pin -->
                                        <button
                                            @click.stop="togglePin(chat)"
                                            :class="[
                                                'p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition shrink-0',
                                                chat.is_pinned ? 'text-amber-500' : 'text-gray-400 hover:text-gray-600'
                                            ]"
                                            :title="chat.is_pinned ? 'Lepas Sematan' : 'Sematkan Sesi'"
                                        >
                                            <!-- Icon Pin -->
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/>
                                            </svg>
                                        </button>

                                        <!-- Tombol Rename -->
                                        <button
                                            @click.stop="startRename(chat)"
                                            class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-blue-500 transition shrink-0"
                                            title="Ubah Nama"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                            </svg>
                                        </button>

                                        <!-- Tombol Hapus -->
                                        <button
                                            @click.stop="deleteChat(chat)"
                                            class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-red-500 transition shrink-0"
                                            title="Hapus Sesi"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Viewport Chat Kanan -->
            <div class="flex-1 flex flex-col min-w-0 bg-gray-50 dark:bg-gray-900">
                <!-- Top Header Chat -->
                <div class="h-16 border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-850 px-6 flex items-center justify-between shadow-sm shrink-0 z-10">
                    <div class="flex items-center gap-3">
                        <Link href="/dashboard" class="md:hidden p-1.5 hover:bg-gray-100 rounded-lg text-gray-500 mr-1">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </Link>
                        <div>
                            <h2 class="text-sm font-bold text-gray-900 dark:text-white truncate max-w-md">
                                {{ currentChat.title }}
                            </h2>
                            <p class="text-[10px] text-gray-500 dark:text-gray-400 font-semibold tracking-wide uppercase">
                                Kab. Mempawah • SPESIAL AI
                            </p>
                        </div>
                    </div>
                    <!-- Status Badge Server & Websockets -->
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-1.5 bg-green-50 dark:bg-green-950/30 text-green-700 dark:text-green-400 px-2.5 py-1 rounded-full text-[10px] font-bold border border-green-200/50">
                            <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                            BPS-MCP Active
                        </div>
                    </div>
                </div>

                <!-- Messages List Viewport -->
                <div
                    ref="messageContainer"
                    class="flex-1 overflow-y-auto p-6 space-y-6 scroll-smooth"
                >
                    <div v-for="message in localMessages" :key="message.id" :class="['flex', message.role === 'user' ? 'justify-end' : 'justify-start']">
                        <!-- Message Card -->
                        <div :class="[
                            'max-w-2xl w-full rounded-2xl p-5 border shadow-sm transition',
                            message.role === 'user'
                                ? 'bg-gradient-to-br from-blue-600 to-indigo-600 border-blue-500 text-white'
                                : 'bg-white dark:bg-gray-800 border-gray-150 dark:border-gray-700 text-gray-900 dark:text-gray-100'
                        ]">
                            <!-- Multimodal Image Attachment -->
                            <div v-if="message.attachments && message.attachments.length > 0" class="grid grid-cols-2 gap-3 mb-4">
                                <div v-for="(img, idx) in message.attachments" :key="idx" class="rounded-xl overflow-hidden shadow-sm border border-black/10 dark:border-white/10 aspect-video bg-gray-100 dark:bg-gray-900">
                                    <img :src="img.startsWith('storage/') ? '/' + img : img" class="w-full h-full object-cover" />
                                </div>
                            </div>

                            <!-- Text Content -->
                            <div class="text-sm leading-relaxed whitespace-pre-wrap select-text">
                                {{ message.content }}
                            </div>

                            <!-- Spinner Loading Model -->
                            <div v-if="message.is_loading" class="flex items-center gap-3 mt-2 py-1 text-blue-500 dark:text-blue-400">
                                <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-xs font-semibold animate-pulse">Menghubungi Agen BPS AI...</span>
                            </div>

                            <!-- Stepper Langkah Berpikir / Evaluasi Agen -->
                            <div v-if="message.agent_steps && message.agent_steps.length > 0" class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700/80">
                                <h4 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">
                                    Langkah Eksekusi Model Context Protocol (MCP):
                                </h4>
                                <div class="space-y-3">
                                    <div
                                        v-for="(step, sIdx) in message.agent_steps"
                                        :key="sIdx"
                                        class="rounded-xl bg-gray-50 dark:bg-gray-900/60 border border-gray-200/50 dark:border-gray-800 overflow-hidden"
                                    >
                                        <div
                                            @click="activeStepDetail = activeStepDetail === step.step ? null : step.step"
                                            class="p-3 flex items-center justify-between cursor-pointer hover:bg-gray-100/40 dark:hover:bg-gray-900 transition"
                                        >
                                            <div class="flex items-center gap-2">
                                                <!-- Status Icon -->
                                                <span v-if="step.status === 'running'" class="flex h-2.5 w-2.5 relative">
                                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                                                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-blue-500"></span>
                                                </span>
                                                <span v-else-if="step.status === 'success'" class="text-green-500">
                                                    <svg class="w-4.5 h-4.5" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                    </svg>
                                                </span>
                                                <span v-else class="text-red-500">
                                                    <svg class="w-4.5 h-4.5" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                                    </svg>
                                                </span>
                                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300">
                                                    Step {{ step.step }}: <code class="bg-gray-200 dark:bg-gray-800 text-blue-600 dark:text-blue-400 px-1.5 py-0.5 rounded font-semibold">{{ step.tool }}</code>
                                                </span>
                                            </div>
                                            <svg :class="['w-4 h-4 text-gray-400 transition-transform duration-200', activeStepDetail === step.step ? 'rotate-180' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </div>
                                        <!-- Collapsible detail response dari server MCP -->
                                        <div v-if="activeStepDetail === step.step && step.result" class="p-3 bg-gray-100/50 dark:bg-gray-950/40 border-t border-gray-200/40 dark:border-gray-800 text-xs font-mono overflow-x-auto text-gray-600 dark:text-gray-400 max-h-60">
                                            <pre>{{ JSON.stringify(step.result, null, 2) }}</pre>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Stateful Chart Rendering (100% Self-Hosted Pure SVG & CSS) -->
                            <div v-if="message.chart_data" class="mt-5 p-4 bg-gray-50 dark:bg-gray-900 border border-gray-150 dark:border-gray-850 rounded-2xl">
                                <h5 class="text-xs font-extrabold text-gray-700 dark:text-gray-300 tracking-wider uppercase mb-4 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                    {{ message.chart_data.title }}
                                </h5>

                                <!-- Bar Chart -->
                                <div v-if="message.chart_data.type === 'bar' || message.chart_data.type === 'line'" class="space-y-4">
                                    <div class="h-48 flex items-end justify-between px-4 pb-2 border-b border-gray-200 dark:border-gray-800">
                                        <div
                                            v-for="(val, vIdx) in message.chart_data.datasets[0].data"
                                            :key="vIdx"
                                            class="flex-1 flex flex-col items-center group relative mx-2 h-full justify-end"
                                        >
                                            <!-- Data tooltip -->
                                            <span class="absolute -top-7 scale-0 group-hover:scale-100 bg-gray-900 text-white text-[10px] px-2 py-1 rounded font-bold transition shadow-md z-20">
                                                {{ val }}
                                            </span>
                                            <!-- Bar element -->
                                            <div
                                                :style="{ height: `${(val / Math.max(...message.chart_data.datasets[0].data)) * 100}%` }"
                                                class="w-full bg-gradient-to-t from-blue-600 to-blue-400 dark:from-blue-500 dark:to-cyan-400 rounded-t-lg transition-all duration-500 shadow-sm"
                                            ></div>
                                        </div>
                                    </div>
                                    <!-- Labels -->
                                    <div class="flex justify-between text-[10px] font-bold text-gray-500 dark:text-gray-400 px-4">
                                        <span v-for="(lbl, lIdx) in message.chart_data.labels" :key="lIdx" class="flex-1 text-center truncate">
                                            {{ lbl }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Multimodal Footer Input Layer -->
                <div class="p-4 bg-white dark:bg-gray-850 border-t border-gray-200 dark:border-gray-800 shrink-0">
                    <form @submit.prevent="sendMessage" class="max-w-4xl mx-auto">
                        <!-- Image Previews Container -->
                        <div v-if="imagePreviews.length > 0" class="flex flex-wrap gap-3 mb-3 p-3 bg-gray-50 dark:bg-gray-900 rounded-xl border border-gray-150 dark:border-gray-800">
                            <div v-for="(preview, idx) in imagePreviews" :key="idx" class="relative w-20 h-20 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 shadow-sm">
                                <img :src="preview.url" class="w-full h-full object-cover" />
                                <button
                                    type="button"
                                    @click="removeImagePreview(idx)"
                                    class="absolute top-1 right-1 p-1 bg-red-600/90 text-white rounded-full hover:bg-red-700 transition"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Input Control Wrapper -->
                        <div class="relative flex items-center bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-2xl shadow-sm focus-within:ring-2 focus-within:ring-blue-500 focus-within:border-blue-500 transition px-3 py-2">
                            <!-- Hidden Image File input -->
                            <input
                                ref="fileInput"
                                type="file"
                                accept="image/*"
                                multiple
                                class="hidden"
                                @change="handleImageChange"
                            />

                            <!-- Multimodal Image Trigger Button -->
                            <button
                                type="button"
                                @click="triggerImageUpload"
                                class="p-2 text-gray-500 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-150 dark:hover:bg-gray-800 rounded-xl transition shrink-0 mr-1"
                                title="Lampirkan Gambar"
                            >
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </button>

                            <!-- Chat Prompt Textarea -->
                            <textarea
                                v-model="form.content"
                                rows="1"
                                placeholder="Tanyakan analisis statistik daerah BPS..."
                                class="flex-1 bg-transparent border-0 ring-0 focus:ring-0 focus:border-0 text-sm text-gray-900 dark:text-white px-2 placeholder-gray-500 dark:placeholder-gray-400 py-1.5 max-h-36 resize-none focus:outline-none"
                                @keydown.enter.prevent="sendMessage"
                            ></textarea>

                            <!-- Send Action Trigger -->
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="p-2.5 bg-blue-600 text-white hover:bg-blue-700 rounded-xl shadow transition shrink-0 ml-2 disabled:opacity-50"
                            >
                                <svg class="w-4.5 h-4.5 transform rotate-90" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" />
                                </svg>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
/* Glassmorphism sidebar coloring */
.dark .bg-gray-850 {
    background-color: #172033;
}
</style>
