<script setup lang="ts">
import { computed, ref, watch } from 'vue';

interface AgentStep {
    step: number;
    tool: string;
    status: 'running' | 'success' | 'failed';
    result?: unknown;
    duration?: string;
}

const props = defineProps<{
    steps: AgentStep[];
}>();

const isExpanded = ref(true);
const activeStepDetail = ref<number | null>(null);

// Periksa apakah ada langkah yang masih berjalan
const hasRunningStep = computed(() => {
    return props.steps.some((s) => s.status === 'running');
});

// Auto-collapse saat semua langkah selesai (seperti Perplexity)
watch(
    hasRunningStep,
    (running) => {
        if (!running) {
            isExpanded.value = false;
        }
    },
    { immediate: true },
);

const toggleStepDetail = (stepIndex: number) => {
    activeStepDetail.value =
        activeStepDetail.value === stepIndex ? null : stepIndex;
};

const getFriendlyToolName = (tool: string) => {
    const mapping: Record<string, string> = {
        search_statistics: 'Mencari data statistik regional BPS',
        compare_regencies: 'Membandingkan indikator pembangunan antar wilayah',
        fetch_regional_report: 'Mengambil laporan pembangunan regional lengkap',
        get_bps_indicator: 'Membaca nilai indikator data nasional BPS',
    };
    return mapping[tool] || `Mengeksekusi tool: ${tool}`;
};

// Helper salin teks ke papan klip dengan notifikasi visual
const showCopiedAlert = ref<number | null>(null);
const copyToClipboard = (text: string, stepId: number) => {
    navigator.clipboard.writeText(text).then(() => {
        showCopiedAlert.value = stepId;
        setTimeout(() => {
            showCopiedAlert.value = null;
        }, 2000);
    });
};
</script>

<template>
    <div class="my-3 font-sans text-xs">
        <!-- Perplexity-style Collapsed Header or Expanded Header -->
        <div
            @click="isExpanded = !isExpanded"
            class="inline-flex w-fit cursor-pointer select-none items-center gap-2 rounded-full border border-gray-200/30 bg-gray-100/80 px-3 py-1.5 font-medium text-gray-500 transition duration-200 hover:bg-gray-200/80 hover:text-gray-700 dark:border-gray-700/30 dark:bg-[#1e1f20]/60 dark:text-gray-400 dark:hover:bg-[#1e1f20] dark:hover:text-gray-200"
        >
            <!-- Status icon -->
            <!-- Running spinner -->
            <span
                v-if="hasRunningStep"
                class="relative flex h-2 w-2 shrink-0 items-center justify-center"
            >
                <span
                    class="absolute inline-flex h-full w-full animate-ping rounded-full bg-blue-400 opacity-75"
                ></span>
                <span
                    class="relative inline-flex h-1.5 w-1.5 rounded-full bg-blue-500"
                ></span>
            </span>
            <!-- Success green check -->
            <span v-else class="shrink-0 text-green-500 dark:text-green-400">
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
            </span>

            <span class="tracking-wide">
                {{
                    hasRunningStep
                        ? 'Menjalankan langkah berpikir BPS AI...'
                        : `Completed ${steps.length} steps`
                }}
            </span>

            <!-- Chevron right for collapsed, Chevron down for expanded -->
            <svg
                :class="[
                    'h-3 w-3 shrink-0 text-gray-400 transition-transform duration-200',
                    isExpanded ? 'rotate-90' : '',
                ]"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2.5"
                    d="M9 5l7 7-7 7"
                />
            </svg>
        </div>

        <!-- Timeline (Only visible when isExpanded is true) -->
        <div
            v-show="isExpanded"
            class="border-gray-250/60 duration-350 relative ml-3 mt-3 space-y-4 border-l pl-4 transition-all ease-in-out dark:border-gray-800/60"
        >
            <div
                v-for="(step, sIdx) in steps"
                :key="sIdx"
                class="animate-fade-in relative"
            >
                <!-- Timeline node dot -->
                <div
                    class="absolute -left-[22.5px] top-1 flex h-3 w-3 items-center justify-center rounded-full bg-white dark:bg-[#131314]"
                >
                    <div
                        :class="[
                            'rounded-full transition duration-300',
                            step.status === 'running'
                                ? 'h-2.5 w-2.5 animate-pulse bg-blue-500'
                                : '',
                            step.status === 'success'
                                ? 'h-2 w-2 bg-green-500 dark:bg-green-400'
                                : '',
                            step.status === 'failed'
                                ? 'h-2 w-2 bg-red-500'
                                : '',
                        ]"
                    ></div>
                </div>

                <!-- Content wrapper -->
                <div class="space-y-1.5">
                    <div
                        @click="toggleStepDetail(step.step)"
                        class="group flex cursor-pointer select-none items-center justify-between text-gray-600 transition duration-150 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        <div class="flex min-w-0 items-center gap-2">
                            <!-- Micro search icon -->
                            <svg
                                class="h-3.5 w-3.5 shrink-0 text-gray-400 transition group-hover:text-blue-500 dark:text-gray-500 dark:group-hover:text-blue-400"
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
                            <span
                                class="truncate font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ getFriendlyToolName(step.tool) }}
                            </span>
                            <span
                                class="shrink-0 rounded bg-gray-100 px-1 font-mono text-[9.5px] font-semibold text-gray-400 dark:bg-gray-800 dark:text-gray-500"
                            >
                                {{ step.tool }}
                            </span>
                        </div>

                        <div
                            class="ml-2 flex shrink-0 items-center gap-2 text-[10px] font-medium text-gray-400 dark:text-gray-500"
                        >
                            <span
                                v-if="step.duration"
                                class="bg-gray-150/40 shrink-0 rounded px-1.5 py-0.5 text-[9px] dark:bg-gray-800/40"
                            >
                                {{ step.duration }}
                            </span>
                            <svg
                                v-if="step.result"
                                :class="[
                                    'h-3 w-3 shrink-0 transition-transform duration-200',
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
                                    stroke-width="2.5"
                                    d="M19 9l-7 7-7-7"
                                />
                            </svg>
                        </div>
                    </div>

                    <!-- Collapsible Response JSON detail -->
                    <div
                        v-if="activeStepDetail === step.step && step.result"
                        class="group/code relative mt-1.5 max-h-48 overflow-y-auto rounded-xl border border-gray-200/40 bg-gray-50/50 p-3 font-mono text-[10px] text-gray-600 dark:border-gray-800/40 dark:bg-gray-950/20 dark:text-gray-400"
                    >
                        <!-- Absolute Copy/Status HUD -->
                        <div
                            class="absolute right-2 top-2 flex items-center gap-1.5 opacity-0 transition duration-200 group-hover/code:opacity-100"
                        >
                            <span
                                v-if="showCopiedAlert === step.step"
                                class="animate-fade-in rounded border border-green-200 bg-white px-1 py-0.5 font-sans text-[9px] font-semibold text-green-500 shadow-sm dark:border-green-800 dark:bg-gray-900"
                            >
                                Copied!
                            </span>
                            <button
                                @click.stop="
                                    copyToClipboard(
                                        JSON.stringify(step.result, null, 2),
                                        step.step,
                                    )
                                "
                                class="rounded border border-gray-200 bg-white p-1 text-gray-500 shadow-sm transition hover:bg-gray-100 hover:text-blue-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-blue-400"
                                title="Salin hasil JSON"
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
                                        d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m-2 4h.01M9 16h5m-1-4h1m-1-4h1"
                                    />
                                </svg>
                            </button>
                        </div>
                        <pre
                            class="select-all whitespace-pre-wrap leading-relaxed"
                            >{{ JSON.stringify(step.result, null, 2) }}</pre
                        >
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
