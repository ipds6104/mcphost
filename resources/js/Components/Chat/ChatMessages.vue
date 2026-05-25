<script setup lang="ts">
import { useMarkdown } from '@/composables/useMarkdown';
import { h, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import ThinkingSteps from './ThinkingSteps.vue';

interface AgentStep {
    step: number;
    tool: string;
    status: 'running' | 'success' | 'failed';
    result?: unknown;
}

interface Message {
    id: string | number;
    role: 'user' | 'assistant';
    content: string;
    attachments?: string[];
    is_loading?: boolean;
    agent_steps?: AgentStep[];
    chart_data?: {
        title: string;
        type: 'bar' | 'line';
        labels: string[];
        datasets: {
            label?: string;
            data: number[];
        }[];
    } | null;
    created_at: string;
}

const props = defineProps<{
    messages: Message[];
}>();

const emit = defineEmits<{
    (e: 'select-prompt', prompt: string): void;
    (e: 'edit-message', content: string): void;
    (e: 'regenerate-message', messageId: string | number): void;
    (e: 'new-chat'): void;
}>();

const copiedMessageId = ref<string | number | null>(null);
const messageFeedback = ref<Record<string | number, 'up' | 'down'>>({});
const activeDropdownId = ref<string | number | null>(null);
const selectedTraceMessage = ref<Message | null>(null);

const copyToClipboard = async (text: string, id: string | number) => {
    try {
        await navigator.clipboard.writeText(text);
        copiedMessageId.value = id;
        setTimeout(() => {
            if (copiedMessageId.value === id) {
                copiedMessageId.value = null;
            }
        }, 2000);
    } catch (err) {
        console.error('Failed to copy: ', err);
    }
};

const handleFeedback = (id: string | number, type: 'up' | 'down') => {
    if (messageFeedback.value[id] === type) {
        delete messageFeedback.value[id];
    } else {
        messageFeedback.value[id] = type;
    }
};

const toggleDropdown = (id: string | number) => {
    if (activeDropdownId.value === id) {
        activeDropdownId.value = null;
    } else {
        activeDropdownId.value = id;
    }
};

const showTraceDetails = (message: Message) => {
    selectedTraceMessage.value = message;
    activeDropdownId.value = null;
};

const exportToMarkdown = (content: string, id: string | number) => {
    const blob = new Blob([content], { type: 'text/markdown' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `respons-ai-${id}.md`;
    a.click();
    URL.revokeObjectURL(url);
    activeDropdownId.value = null;
};

const closeAllDropdowns = () => {
    activeDropdownId.value = null;
};

onUnmounted(() => {
    window.removeEventListener('click', closeAllDropdowns);
});

// Komponen SVG Fungsional yang ringan (Functional SVG Icons)
const ChartIcon = () =>
    h(
        'svg',
        {
            class: 'w-4 h-4',
            fill: 'none',
            stroke: 'currentColor',
            viewBox: '0 0 24 24',
        },
        [
            h('path', {
                'stroke-linecap': 'round',
                'stroke-linejoin': 'round',
                'stroke-width': '2',
                d: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
            }),
        ],
    );

const DatabaseIcon = () =>
    h(
        'svg',
        {
            class: 'w-4 h-4',
            fill: 'none',
            stroke: 'currentColor',
            viewBox: '0 0 24 24',
        },
        [
            h('path', {
                'stroke-linecap': 'round',
                'stroke-linejoin': 'round',
                'stroke-width': '2',
                d: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4',
            }),
        ],
    );

const ShieldIcon = () =>
    h(
        'svg',
        {
            class: 'w-4 h-4',
            fill: 'none',
            stroke: 'currentColor',
            viewBox: '0 0 24 24',
        },
        [
            h('path', {
                'stroke-linecap': 'round',
                'stroke-linejoin': 'round',
                'stroke-width': '2',
                d: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
            }),
        ],
    );

const SparklesIcon = () =>
    h('svg', { class: 'w-4 h-4', fill: 'currentColor', viewBox: '0 0 24 24' }, [
        h('path', {
            d: 'M12 2L14.7 9.3L22 12L14.7 14.7L12 22L9.3 14.7L2 12L9.3 9.3L12 2Z',
        }),
    ]);

const suggestionCards = [
    {
        title: 'Tren Indeks Pembangunan Statistik (IPS) Kabupaten Mempawah tahun ini.',
        prompt: 'Bagaimana tren Indeks Pembangunan Statistik (IPS) Kabupaten Mempawah tahun ini?',
        type: 'Analisis',
        icon: ChartIcon,
    },
    {
        title: 'Standar metadata Angka Kematian Neonatal Dinas Kesehatan Mempawah.',
        prompt: 'Bagaimana pemenuhan standar metadata statistik sektoral untuk dataset Angka Kematian Neonatal per 1.000 Kelahiran Hidup dari Dinas Kesehatan Mempawah?',
        type: 'Metadata',
        icon: DatabaseIcon,
    },
    {
        title: 'Evaluasi pencapaian Standar Pelayanan Minimal (SPM) Kesehatan Mempawah.',
        prompt: 'Tolong lakukan evaluasi pencapaian Standar Pelayanan Minimal (SPM) Bidang Kesehatan di Kabupaten Mempawah berdasarkan indikator yang ditangani Dinas Kesehatan.',
        type: 'Evaluasi',
        icon: ShieldIcon,
    },
    {
        title: 'Visualisasikan jumlah pencari kerja terdaftar vs tenaga kerja industri di Mempawah.',
        prompt: 'Visualisasikan perbandingan Jumlah Pencari Kerja Terdaftar dengan Jumlah Tenaga Kerja Industri di Kabupaten Mempawah.',
        type: 'Visualisasi',
        icon: SparklesIcon,
    },
];

const { renderMarkdown } = useMarkdown();

const containerRef = ref<HTMLDivElement | null>(null);

const scrollToBottom = () => {
    nextTick(() => {
        if (containerRef.value) {
            containerRef.value.scrollTop = containerRef.value.scrollHeight;
        }
    });
};

// Scroll otomatis ke bawah jika ada pesan baru atau langkah baru masuk
watch(() => props.messages, scrollToBottom, { deep: true });

onMounted(() => {
    scrollToBottom();
    window.addEventListener('click', closeAllDropdowns);
});
</script>

<template>
    <div
        ref="containerRef"
        class="scrollbar-thin flex-1 space-y-8 overflow-y-auto scroll-smooth px-6 py-6 md:px-12"
    >
        <!-- State Kosong: Google Gemini-style Greeting & Prompt Cards -->
        <div
            v-if="messages.length === 0"
            class="mx-auto flex w-full max-w-3xl flex-col justify-center py-12 md:py-20"
        >
            <!-- Greeting Area with Beautiful Shimmer/Gradient -->
            <div class="mb-10 text-left md:text-center">
                <h1
                    class="bg-gradient-to-r from-blue-600 via-purple-500 to-pink-500 bg-clip-text text-4xl font-extrabold tracking-tight text-transparent md:text-5xl dark:from-blue-400 dark:via-purple-400 dark:to-pink-400"
                >
                    Halo, {{ $page.props.auth.user.name }}
                </h1>
                <p
                    class="mt-3 text-sm font-medium text-gray-500 md:text-base dark:text-gray-400"
                >
                    Ada yang bisa saya bantu hari ini untuk mengelola data dasar & statistik
                    sektoral?
                </p>
            </div>

            <!-- Suggestion Prompt Cards Grid -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-4">
                <button
                    v-for="(card, idx) in suggestionCards"
                    :key="idx"
                    @click="emit('select-prompt', card.prompt)"
                    class="group flex flex-col justify-between rounded-[20px] border border-gray-100/50 bg-white p-5 text-left shadow-sm transition-all duration-300 hover:-translate-y-1 hover:bg-gray-50 hover:shadow-md dark:border-gray-800/10 dark:bg-[#1e1f20]/40 dark:hover:bg-[#1e1f20]/80"
                >
                    <!-- Card Prompt Text -->
                    <span
                        class="font-sans text-[11px] font-semibold leading-relaxed text-gray-700 transition-colors group-hover:text-blue-600 dark:text-gray-300 dark:group-hover:text-blue-400"
                    >
                        {{ card.title }}
                    </span>
                    <!-- Bottom Row: Icon & Mini Text -->
                    <div class="mt-8 flex items-center justify-between">
                        <span
                            class="text-[9px] font-extrabold uppercase tracking-widest text-gray-400 group-hover:text-gray-500 dark:text-gray-500"
                        >
                            {{ card.type }}
                        </span>
                        <!-- Custom Icon with hover glow -->
                        <div
                            class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 text-gray-500 shadow-sm transition group-hover:bg-blue-50 group-hover:text-blue-600 dark:bg-[#131314] dark:text-gray-400 dark:group-hover:bg-blue-950/30 dark:group-hover:text-blue-400"
                        >
                            <component :is="card.icon" />
                        </div>
                    </div>
                </button>
            </div>
        </div>

        <div v-else class="mx-auto w-full max-w-3xl space-y-8">
            <div
                v-for="message in messages"
                :key="message.id"
                :class="[
                    'flex w-full',
                    message.role === 'user' ? 'justify-end' : 'justify-start',
                ]"
            >
                <!-- Tampilan Pesan User (Kapsul Tipis & Bersahaja) -->
                <div
                    v-if="message.role === 'user'"
                    class="group flex max-w-[70%] flex-col items-end gap-1"
                >
                    <div
                        class="rounded-[20px] border border-gray-200/40 bg-white/80 px-4 py-3 font-sans text-xs leading-relaxed text-gray-800 shadow-sm dark:border-gray-800/40 dark:bg-[#1e1f20]/60 dark:text-gray-200"
                    >
                        <!-- User Image Attachments -->
                        <div
                            v-if="
                                message.attachments &&
                                message.attachments.length > 0
                            "
                            class="mb-2 grid w-64 grid-cols-2 gap-2"
                        >
                            <div
                                v-for="(img, idx) in message.attachments"
                                :key="idx"
                                class="aspect-video overflow-hidden rounded-lg border border-black/5 bg-gray-50 dark:border-white/5 dark:bg-gray-900"
                            >
                                <img
                                    :src="
                                        img.startsWith('storage/')
                                            ? '/' + img
                                            : img
                                    "
                                    class="h-full w-full object-cover"
                                />
                            </div>
                        </div>
                        <!-- User Text Content -->
                        <span
                            class="select-text whitespace-pre-wrap leading-relaxed"
                            >{{ message.content }}</span
                        >
                    </div>

                    <!-- Hover Toolbar (Copy & Edit) -->
                    <div
                        class="mr-2 flex items-center gap-1 opacity-0 transition-opacity duration-200 group-hover:opacity-100"
                    >
                        <!-- Copy Button -->
                        <button
                            type="button"
                            @click="
                                copyToClipboard(message.content, message.id)
                            "
                            class="rounded-lg p-1 text-gray-400 hover:bg-gray-200/50 hover:text-gray-600 dark:hover:bg-gray-800/50 dark:hover:text-gray-300"
                            :title="
                                copiedMessageId === message.id
                                    ? 'Tersalin'
                                    : 'Salin perintah'
                            "
                        >
                            <svg
                                v-if="copiedMessageId === message.id"
                                class="h-3.5 w-3.5 text-green-500"
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
                                class="h-3.5 w-3.5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"
                                />
                            </svg>
                        </button>
                        <!-- Edit Button -->
                        <button
                            type="button"
                            @click="emit('edit-message', message.content)"
                            class="rounded-lg p-1 text-gray-400 hover:bg-gray-200/50 hover:text-gray-600 dark:hover:bg-gray-800/50 dark:hover:text-gray-300"
                            title="Sunting perintah"
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
                    </div>
                </div>

                <!-- Tampilan Respon AI (Aliran Artikel Natural Tanpa Gelembung Chat Kaku) -->
                <div v-else class="flex w-full max-w-4xl items-start">
                    <!-- Text & Media Flow Container -->
                    <div class="min-w-0 flex-1 space-y-4">
                        <!-- Loader Efek Aurora Sparkle (Pulsing Gradient Shimmer) -->
                        <div
                            v-if="message.is_loading"
                            class="w-full space-y-3 py-3"
                        >
                            <!-- KASUS A: AI Menjawab Langsung / Sedang Berpikir (Direct Answer, agent_steps Kosong) -->
                            <div
                                v-if="
                                    !message.agent_steps ||
                                    message.agent_steps.length === 0
                                "
                                class="animate-fade-in space-y-3"
                            >
                                <div
                                    class="flex items-center gap-2.5 text-indigo-500 dark:text-indigo-400"
                                >
                                    <!-- Indigo Sparkle/Brain Pulse Dot -->
                                    <span
                                        class="relative flex h-3 w-3 shrink-0 items-center justify-center"
                                    >
                                        <span
                                            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-indigo-400 opacity-75"
                                        ></span>
                                        <span
                                            class="relative inline-flex h-2 w-2 rounded-full bg-indigo-500 shadow-[0_0_6px_rgba(99,102,241,0.5)]"
                                        ></span>
                                    </span>
                                    <span
                                        class="animate-pulse text-[11px] font-bold uppercase tracking-wider"
                                        >Sedang merumuskan jawaban
                                        langsung...</span
                                    >
                                </div>
                                <div class="pl-5.5 space-y-2">
                                    <div
                                        class="h-1.5 w-[85%] animate-pulse rounded-full bg-gradient-to-r from-indigo-100/60 via-purple-100/60 to-pink-100/60 dark:from-indigo-950/20 dark:via-purple-950/20 dark:to-pink-950/20"
                                    ></div>
                                    <div
                                        class="h-1.5 w-[55%] animate-pulse rounded-full bg-gradient-to-r from-indigo-100/60 via-purple-100/60 to-pink-100/60 dark:from-indigo-950/20 dark:via-purple-950/20 dark:to-pink-950/20"
                                    ></div>
                                    <div
                                        class="h-1.5 w-[70%] animate-pulse rounded-full bg-gradient-to-r from-indigo-100/60 via-purple-100/60 to-pink-100/60 dark:from-indigo-950/20 dark:via-purple-950/20 dark:to-pink-950/20"
                                    ></div>
                                </div>
                            </div>

                            <!-- KASUS B: AI Menggunakan Tools / MCP Aktif (Tabel/Statistik Sedang Ditarik) -->
                            <div v-else class="animate-fade-in space-y-3">
                                <div
                                    class="flex items-center gap-2.5 text-emerald-500 dark:text-emerald-400"
                                >
                                    <!-- Emerald Analytics Pulse Dot -->
                                    <span
                                        class="relative flex h-3 w-3 shrink-0 items-center justify-center"
                                    >
                                        <span
                                            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"
                                        ></span>
                                        <span
                                            class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500 shadow-[0_0_6px_rgba(16,185,129,0.5)]"
                                        ></span>
                                    </span>
                                    <span
                                        class="animate-pulse text-[11px] font-bold uppercase tracking-wider"
                                        >Sedang memproses analisis data dasar & sektoral
                                        (MCP)...</span
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Stepper Langkah Berpikir / Evaluasi Agen (Accordion Interaktif) -->
                        <ThinkingSteps
                            v-if="
                                message.agent_steps &&
                                message.agent_steps.length > 0
                            "
                            :steps="message.agent_steps"
                            :loading="message.is_loading"
                        />

                        <!-- Isi Artikel/Dokumen Output -->
                        <div
                            v-if="message.content"
                            class="prose prose-sm dark:prose-invert prose-headings:font-semibold prose-headings:text-gray-900 dark:prose-headings:text-gray-50 prose-code:rounded prose-code:bg-gray-100/80 prose-code:px-1.5 prose-code:py-0.5 dark:prose-code:bg-gray-800/80 prose-code:text-[12px] prose-code:font-mono prose-pre:rounded-xl prose-pre:bg-gray-100/80 dark:prose-pre:bg-gray-800/80 prose-a:text-blue-600 dark:prose-a:text-blue-400 prose-a:no-underline hover:prose-a:underline prose-ul:my-2 prose-ol:my-2 prose-li:my-0.5 prose-p:my-2 prose-blockquote:border-l-blue-500 max-w-none select-text text-[13.5px] leading-relaxed tracking-normal text-gray-900 dark:text-gray-100"
                            v-html="renderMarkdown(message.content)"
                        />

                        <!-- Widget Diagram Grafik Interaktif (Desain Premium Floating, SVG + CSS) -->
                        <div
                            v-if="message.chart_data"
                            class="mt-5 max-w-2xl rounded-[24px] border border-gray-200/50 bg-white p-5 shadow-sm dark:border-gray-800/60 dark:bg-[#1e1f20]/60"
                        >
                            <h5
                                class="mb-5 flex items-center gap-2 font-sans text-xs font-bold uppercase tracking-wider text-gray-800 dark:text-gray-200"
                            >
                                <svg
                                    class="h-4 w-4 text-blue-500"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                                    />
                                </svg>
                                {{ message.chart_data.title }}
                            </h5>

                            <!-- Bar Chart Rendering -->
                            <div
                                v-if="
                                    message.chart_data.type === 'bar' ||
                                    message.chart_data.type === 'line'
                                "
                                class="space-y-4"
                            >
                                <div
                                    class="border-gray-150/40 flex h-44 items-end justify-between border-b px-2 pb-2 dark:border-gray-800/40"
                                >
                                    <div
                                        v-for="(val, vIdx) in message.chart_data
                                            .datasets[0].data"
                                        :key="vIdx"
                                        class="group relative mx-2 flex h-full flex-1 flex-col items-center justify-end"
                                    >
                                        <!-- Tooltip melayang saat hover -->
                                        <span
                                            class="absolute -top-7 z-20 scale-0 rounded-md bg-gray-900/90 px-2 py-1 font-sans text-[10px] font-bold text-white shadow-md transition group-hover:scale-100 dark:bg-gray-800"
                                        >
                                            {{ val }}
                                        </span>
                                        <!-- Elemen Grafik Bergradien -->
                                        <div
                                            :style="{
                                                height: `${(val / Math.max(...(message.chart_data?.datasets[0].data || [1]))) * 100}%`,
                                            }"
                                            class="w-full shrink-0 rounded-t-md bg-gradient-to-t from-blue-600 to-blue-400 shadow-sm transition-all duration-500 hover:brightness-105 dark:from-blue-500 dark:to-cyan-400"
                                        ></div>
                                    </div>
                                </div>
                                <!-- Labels -->
                                <div
                                    class="flex justify-between px-2 font-sans text-[9px] font-extrabold text-gray-400 dark:text-gray-500"
                                >
                                    <span
                                        v-for="(lbl, lIdx) in message.chart_data
                                            .labels"
                                        :key="lIdx"
                                        class="flex-1 truncate px-1 text-center"
                                    >
                                        {{ lbl }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- AI Response Toolbar (Thumbs Up/Down, Regenerate, Copy, More Options) -->
                        <div
                            v-if="!message.is_loading"
                            class="mt-4 flex items-center gap-1.5 text-gray-400 dark:text-gray-500"
                        >
                            <!-- Sukai (Thumbs Up) -->
                            <button
                                type="button"
                                @click="handleFeedback(message.id, 'up')"
                                :class="[
                                    'rounded-full p-1.5 transition-all duration-200 hover:bg-gray-200/50 dark:hover:bg-gray-800/50',
                                    messageFeedback[message.id] === 'up'
                                        ? 'bg-blue-50 text-blue-600 dark:bg-blue-950/20 dark:text-blue-400'
                                        : 'hover:text-gray-600 dark:hover:text-gray-300',
                                ]"
                                title="Sukai"
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
                                        d="M14 9V5a3 3 0 00-3-3l-4 9v11h11.28a2 2 0 002-1.7l1.38-9a2 2 0 00-2-2.3zM7 22H4a2 2 0 01-2-2v-7a2 2 0 012-2h3"
                                    />
                                </svg>
                            </button>

                            <!-- Tidak Suka (Thumbs Down) -->
                            <button
                                type="button"
                                @click="handleFeedback(message.id, 'down')"
                                :class="[
                                    'rounded-full p-1.5 transition-all duration-200 hover:bg-gray-200/50 dark:hover:bg-gray-800/50',
                                    messageFeedback[message.id] === 'down'
                                        ? 'bg-red-50 text-red-600 dark:bg-red-950/20 dark:text-red-400'
                                        : 'hover:text-gray-600 dark:hover:text-gray-300',
                                ]"
                                title="Tidak sukai"
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
                                        d="M10 15v4a3 3 0 003 3l4-9V2H5.72a2 2 0 00-2 1.7l-1.38 9a2 2 0 002 2.3zm7-13h3a2 2 0 012 2v7a2 2 0 01-2 2h-3"
                                    />
                                </svg>
                            </button>

                            <!-- Buat Ulang (Regenerate) -->
                            <button
                                type="button"
                                @click="emit('regenerate-message', message.id)"
                                class="rounded-full p-1.5 transition-all duration-200 hover:bg-gray-200/50 hover:text-gray-600 dark:hover:bg-gray-800/50 dark:hover:text-gray-300"
                                title="Buat ulang jawaban"
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
                                        d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"
                                    />
                                </svg>
                            </button>

                            <!-- Salin Jawaban -->
                            <button
                                type="button"
                                @click="
                                    copyToClipboard(message.content, message.id)
                                "
                                class="rounded-full p-1.5 transition-all duration-200 hover:bg-gray-200/50 hover:text-gray-600 dark:hover:bg-gray-800/50 dark:hover:text-gray-300"
                                :title="
                                    copiedMessageId === message.id
                                        ? 'Tersalin'
                                        : 'Salin jawaban'
                                "
                            >
                                <svg
                                    v-if="copiedMessageId === message.id"
                                    class="h-3.5 w-3.5 text-green-500"
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
                                    class="h-3.5 w-3.5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"
                                    />
                                </svg>
                            </button>

                            <!-- Opsi Lainnya (...) -->
                            <div class="relative">
                                <button
                                    type="button"
                                    @click.stop="toggleDropdown(message.id)"
                                    class="rounded-full p-1.5 transition-all duration-200 hover:bg-gray-200/50 hover:text-gray-600 dark:hover:bg-gray-800/50 dark:hover:text-gray-300"
                                    title="Opsi lainnya"
                                >
                                    <svg
                                        class="h-3.5 w-3.5"
                                        fill="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"
                                        />
                                    </svg>
                                </button>

                                <!-- Dropdown Menu -->
                                <div
                                    v-if="activeDropdownId === message.id"
                                    class="border-gray-150/40 absolute bottom-8 left-0 z-30 w-48 rounded-xl border bg-white py-1.5 shadow-lg ring-1 ring-black/5 dark:border-gray-800/60 dark:bg-[#1e1f20]"
                                    @click.stop
                                >
                                    <button
                                        type="button"
                                        @click="emit('new-chat')"
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
                                                d="M12 4v16m8-8H4"
                                            />
                                        </svg>
                                        <span>Buat chat baru</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="
                                            exportToMarkdown(
                                                message.content,
                                                message.id,
                                            )
                                        "
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
                                                d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h7a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"
                                            />
                                        </svg>
                                        <span>Ekspor ke Dokumen</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="showTraceDetails(message)"
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
                                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                            />
                                        </svg>
                                        <span>Lihat detail respons</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detail Respons (Observability Trace) -->
    <div
        v-if="selectedTraceMessage"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
        @click="selectedTraceMessage = null"
    >
        <div
            class="border-gray-150/40 w-full max-w-sm rounded-2xl border bg-white p-6 shadow-xl dark:border-gray-800/60 dark:bg-[#1e1f20]"
            @click.stop
        >
            <div class="mb-4 flex items-center justify-between">
                <h3
                    class="font-sans text-xs font-bold text-gray-900 dark:text-gray-50"
                >
                    Detail Respons AI (Trace Metadata)
                </h3>
                <button
                    type="button"
                    @click="selectedTraceMessage = null"
                    class="rounded-full p-1 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800"
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
                            stroke-width="2.5"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
            </div>
            <div class="space-y-3 font-sans text-xs">
                <div
                    class="flex justify-between border-b border-gray-100 pb-2 dark:border-gray-800/60"
                >
                    <span class="text-gray-500">ID Pesan</span>
                    <span
                        class="select-all font-mono text-[10px] text-gray-800 dark:text-gray-200"
                        >{{ selectedTraceMessage.id }}</span
                    >
                </div>
                <div
                    class="flex justify-between border-b border-gray-100 pb-2 dark:border-gray-800/60"
                >
                    <span class="text-gray-500">Tanggal Pembuatan</span>
                    <span class="text-gray-800 dark:text-gray-200">{{
                        new Date(
                            selectedTraceMessage.created_at,
                        ).toLocaleString('id-ID')
                    }}</span>
                </div>
                <div
                    class="flex justify-between border-b border-gray-100 pb-2 dark:border-gray-800/60"
                >
                    <span class="text-gray-500">Langkah Berpikir Agen</span>
                    <span class="text-gray-800 dark:text-gray-200">
                        {{
                            selectedTraceMessage.agent_steps
                                ? selectedTraceMessage.agent_steps.length
                                : 0
                        }}
                        Langkah
                    </span>
                </div>
                <div class="flex justify-between pb-2">
                    <span class="text-gray-500">Provider Utama</span>
                    <span
                        class="rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-semibold text-blue-600 dark:bg-blue-900/30 dark:text-blue-400"
                    >
                        Google Gemini AI
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Scrollbar tipis */
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
