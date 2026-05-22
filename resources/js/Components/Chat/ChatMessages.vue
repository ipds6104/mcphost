<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue';

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

const containerRef = ref<HTMLDivElement | null>(null);
const activeStepDetail = ref<number | null>(null);

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
});

const toggleStepDetail = (stepIndex: number) => {
    activeStepDetail.value =
        activeStepDetail.value === stepIndex ? null : stepIndex;
};
</script>

<template>
    <div
        ref="containerRef"
        class="scrollbar-thin flex-1 space-y-8 overflow-y-auto scroll-smooth px-6 py-6 md:px-12"
    >
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
                class="flex max-w-[70%] flex-col items-end gap-1.5"
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
                                    img.startsWith('storage/') ? '/' + img : img
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
            </div>

            <!-- Tampilan Respon AI (Aliran Artikel Natural Tanpa Gelembung Chat Kaku) -->
            <div v-else class="flex w-full max-w-4xl items-start gap-4">
                <!-- BPS Sparkle Avatar / Logo -->
                <div
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-tr from-blue-500 via-indigo-500 to-pink-500 text-white shadow-md"
                >
                    <svg
                        class="h-5 w-5"
                        fill="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            d="M12 2L14.7 9.3L22 12L14.7 14.7L12 22L9.3 14.7L2 12L9.3 9.3L12 2Z"
                        />
                    </svg>
                </div>

                <!-- Text & Media Flow Container -->
                <div class="min-w-0 flex-1 space-y-4">
                    <!-- AI Title Header -->
                    <div class="-mb-2 flex items-center gap-1.5">
                        <span
                            class="font-sans text-xs font-bold tracking-wide text-gray-800 dark:text-gray-200"
                        >
                            Asisten MCP AI
                        </span>
                        <span
                            class="rounded-full bg-blue-50 px-1.5 py-0.5 font-sans text-[9px] font-extrabold uppercase text-blue-600 dark:bg-blue-900/30 dark:text-blue-400"
                        >
                            MODEL
                        </span>
                    </div>

                    <!-- Loader Efek Aurora Sparkle (Pulsing Gradient Shimmer) -->
                    <div
                        v-if="message.is_loading"
                        class="w-full space-y-3 py-3"
                    >
                        <div
                            class="flex animate-pulse items-center gap-2 text-blue-500 dark:text-blue-400"
                        >
                            <div
                                class="h-3.5 w-3.5 animate-ping rounded-full bg-gradient-to-tr from-cyan-400 via-blue-500 to-indigo-500"
                            ></div>
                            <span
                                class="animate-pulse text-[11px] font-bold tracking-wide"
                                >Menghubungi Agen BPS AI...</span
                            >
                        </div>
                        <div class="space-y-2">
                            <div
                                class="h-2 w-[85%] animate-pulse rounded-full bg-gradient-to-r from-blue-100 via-pink-100 to-indigo-100 dark:from-blue-900/20 dark:via-pink-900/20 dark:to-indigo-900/20"
                            ></div>
                            <div
                                class="h-2 w-[55%] animate-pulse rounded-full bg-gradient-to-r from-blue-100 via-pink-100 to-indigo-100 dark:from-blue-900/20 dark:via-pink-900/20 dark:to-indigo-900/20"
                            ></div>
                            <div
                                class="h-2 w-[70%] animate-pulse rounded-full bg-gradient-to-r from-blue-100 via-pink-100 to-indigo-100 dark:from-blue-900/20 dark:via-pink-900/20 dark:to-indigo-900/20"
                            ></div>
                        </div>
                    </div>

                    <!-- Isi Artikel/Dokumen Output -->
                    <div
                        v-if="message.content"
                        class="select-text space-y-4 whitespace-pre-wrap font-sans text-[13px] leading-relaxed tracking-normal text-gray-900 dark:text-gray-100"
                    >
                        {{ message.content }}
                    </div>

                    <!-- Stepper Langkah Berpikir / Evaluasi Agen (Accordion Interaktif) -->
                    <div
                        v-if="
                            message.agent_steps &&
                            message.agent_steps.length > 0
                        "
                        class="border-gray-150/40 mt-4 border-t pt-3 dark:border-gray-800/40"
                    >
                        <div class="mb-2 flex items-center gap-1.5">
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
                                    d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"
                                />
                            </svg>
                            <span
                                class="font-sans text-[10px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500"
                            >
                                Langkah Eksekusi Model Context Protocol (MCP)
                            </span>
                        </div>
                        <div class="space-y-1.5">
                            <div
                                v-for="(step, sIdx) in message.agent_steps"
                                :key="sIdx"
                                class="overflow-hidden rounded-xl border border-gray-200/50 bg-white/40 dark:border-gray-800/60 dark:bg-[#1e1f20]/20"
                            >
                                <div
                                    @click="toggleStepDetail(step.step)"
                                    class="flex cursor-pointer select-none items-center justify-between p-2.5 transition duration-150 hover:bg-gray-200/30 dark:hover:bg-gray-800/20"
                                >
                                    <div class="flex items-center gap-2">
                                        <!-- Step status indicator -->
                                        <span
                                            v-if="step.status === 'running'"
                                            class="relative flex h-2 w-2 shrink-0"
                                        >
                                            <span
                                                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-blue-400 opacity-75"
                                            ></span>
                                            <span
                                                class="relative inline-flex h-2 w-2 rounded-full bg-blue-500"
                                            ></span>
                                        </span>
                                        <span
                                            v-else-if="
                                                step.status === 'success'
                                            "
                                            class="shrink-0 text-green-500"
                                        >
                                            <svg
                                                class="h-4 w-4"
                                                fill="currentColor"
                                                viewBox="0 0 20 20"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                        </span>
                                        <span
                                            v-else
                                            class="shrink-0 text-red-500"
                                        >
                                            <svg
                                                class="h-4 w-4"
                                                fill="currentColor"
                                                viewBox="0 0 20 20"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                        </span>
                                        <span
                                            class="font-sans text-xs font-bold text-gray-700 dark:text-gray-300"
                                        >
                                            Langkah {{ step.step }}:
                                            <code
                                                class="rounded bg-gray-100 px-1.5 py-0.5 font-mono font-bold text-blue-600 dark:bg-gray-800 dark:text-blue-400"
                                                >{{ step.tool }}</code
                                            >
                                        </span>
                                    </div>
                                    <svg
                                        :class="[
                                            'h-3.5 w-3.5 text-gray-400 transition-transform duration-200',
                                            activeStepDetail === step.step
                                                ? 'rotate-180'
                                                : '',
                                        ]"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M19 9l-7 7-7-7"
                                        />
                                    </svg>
                                </div>
                                <!-- Collapsible detail response dari server MCP -->
                                <div
                                    v-if="
                                        activeStepDetail === step.step &&
                                        step.result
                                    "
                                    class="border-gray-150/40 max-h-60 overflow-x-auto border-t bg-gray-50/50 p-3 font-mono text-[10px] text-gray-600 dark:border-gray-800/40 dark:bg-gray-950/20 dark:text-gray-400"
                                >
                                    <pre class="leading-relaxed">{{
                                        JSON.stringify(step.result, null, 2)
                                    }}</pre>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Widget Diagram Grafik Interaktif (Desain Premium Floating, SVG + CSS) -->
                    <div
                        v-if="message.chart_data"
                        class="mt-5 max-w-2xl rounded-[24px] border border-gray-200/50 bg-white p-5 shadow-sm dark:border-gray-800/60 dark:bg-[#1e1f20]/60"
                    >
                        <h5
                            class="mb-5 flex items-center gap-2 font-sans text-xs font-bold uppercase tracking-wider text-gray-800 dark:text-gray-200"
                        >
                            <svg
                                class="w-4.5 h-4.5 text-blue-500"
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
