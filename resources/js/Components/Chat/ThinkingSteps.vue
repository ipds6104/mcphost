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
    loading?: boolean;
}>();

const isExpanded = ref(true);
const expandedStepIds = ref<Record<number, boolean>>({});

// Normalisasi langkah secara reaktif agar status selalu terdefinisi
const normalizedSteps = computed(() => {
    return props.steps.map((step) => {
        let status = step.status;
        if (!status) {
            // Jika status tidak didefinisikan (misal saat cold-load dari DB),
            // tebak berdasarkan keberadaan hasil tool-call.
            status = step.result ? 'success' : 'running';
        }
        return {
            ...step,
            status,
        };
    });
});

// Periksa apakah ada langkah yang masih berjalan
const hasRunningStep = computed(() => {
    return normalizedSteps.value.some((s) => s.status === 'running');
});

// Auto-collapse/expand reaktif berdasarkan status pemuatan AI (seperti Perplexity)
watch(
    () => props.loading,
    (isLoading) => {
        isExpanded.value = !!isLoading;
    },
    { immediate: true },
);

const toggleStepDetail = (stepIndex: number) => {
    expandedStepIds.value[stepIndex] = !expandedStepIds.value[stepIndex];
};

const areAllStepsExpanded = computed(() => {
    const stepsWithResult = normalizedSteps.value.filter((s) => s.result);
    if (stepsWithResult.length === 0) return false;
    return normalizedSteps.value.every((s, idx) => !s.result || expandedStepIds.value[idx]);
});

const toggleExpandAll = () => {
    const target = !areAllStepsExpanded.value;
    normalizedSteps.value.forEach((s, idx) => {
        if (s.result) {
            expandedStepIds.value[idx] = target;
        }
    });
};

const getFriendlyToolName = (tool: string) => {
    const mapping: Record<string, string> = {
        search_statistics: 'Mencari data statistik regional BPS',
        compare_regencies: 'Membandingkan indikator pembangunan antar wilayah',
        fetch_regional_report: 'Mengambil laporan pembangunan regional lengkap',
        get_bps_indicator: 'Membaca nilai indikator data nasional BPS',
    };
    if (mapping[tool]) return mapping[tool];
    const formatted = tool
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
    return `Menjalankan analisis data: ${formatted}`;
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
const getStepMetadata = (step: AgentStep) => {
    if (step.result && typeof step.result === 'object') {
        const res = step.result as Record<string, unknown>;

        // 1. Cek jika ada parameter atau metadata daerah BPS
        if (res.regency) {
            return `Wilayah: ${res.regency}`;
        }
        if (res.regencies) {
            return 'Pemetaan Wilayah Terpilih';
        }

        // 2. Cek indikator spesifik
        if (res.indicator) {
            return `Indikator: ${res.indicator}`;
        }

        // 3. Cek detail kueri BPS
        if (res.data && Array.isArray(res.data) && res.data[1]) {
            const dataArr = res.data[1];
            if (dataArr.length > 0) {
                if (dataArr[0].title) {
                    return `Ditemukan: ${dataArr[0].title.slice(0, 30)}...`;
                }
                if (dataArr[0].var_name) {
                    return `Variabel: ${dataArr[0].var_name}`;
                }
            }
        }

        // 4. Fallback pembandingan
        if (res.comparison || res.kutim || res.mempawah) {
            return 'Komparasi Regional';
        }
    }
    return null;
};
</script>

<template>
    <div class="my-3 font-sans text-xs">
        <!-- Header container -->
        <div class="flex flex-wrap items-center gap-2">
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

            <!-- Expand/Collapse All Details Button -->
            <button
                v-if="isExpanded && steps.some(s => s.result)"
                @click.stop="toggleExpandAll"
                class="inline-flex items-center gap-1 rounded-full border border-gray-200/30 bg-gray-100/50 px-2.5 py-1.5 font-medium text-gray-400 transition duration-200 hover:bg-gray-200/85 hover:text-gray-700 dark:border-gray-700/30 dark:bg-[#1e1f20]/40 dark:text-gray-400 dark:hover:bg-[#1e1f20] dark:hover:text-gray-200"
            >
                <svg
                    class="h-3 w-3 shrink-0 text-gray-400 transition group-hover:text-blue-500 dark:text-gray-500 dark:group-hover:text-blue-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        :d="areAllStepsExpanded ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7'"
                    />
                </svg>
                <span class="tracking-wide">{{ areAllStepsExpanded ? 'Collapse All Details' : 'Expand All Details' }}</span>
            </button>
        </div>

        <!-- Timeline (Only visible when isExpanded is true) -->
        <TransitionGroup
            v-show="isExpanded"
            name="step-fade"
            tag="div"
            class="duration-350 relative ml-3 mt-3 space-y-4 border-l border-gray-200 pl-4 transition-all ease-in-out dark:border-gray-800"
        >
            <div
                v-for="(step, sIdx) in normalizedSteps"
                :key="sIdx"
                class="animate-fade-in relative"
            >
                <!-- Timeline node dot with concentric pulsing radar rings -->
                <div
                    class="h-4.5 w-4.5 absolute -left-[26px] top-[1px] flex items-center justify-center"
                >
                    <span
                        v-if="step.status === 'running'"
                        class="absolute inline-flex h-full w-full animate-ping rounded-full bg-blue-400/20 opacity-75"
                    ></span>
                    <div
                        :class="[
                            'relative h-2.5 w-2.5 rounded-full border-2 shadow-sm transition-all duration-300',
                            step.status === 'running'
                                ? 'border-white bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.8)] dark:border-[#131314]'
                                : '',
                            step.status === 'success'
                                ? 'border-white bg-emerald-500 shadow-[0_0_6px_rgba(16,185,129,0.4)] dark:border-[#131314]'
                                : '',
                            step.status === 'failed'
                                ? 'border-white bg-rose-500 shadow-[0_0_6px_rgba(244,63,94,0.4)] dark:border-[#131314]'
                                : '',
                        ]"
                    ></div>
                </div>

                <!-- Content wrapper -->
                <div class="space-y-1">
                    <div
                        @click="toggleStepDetail(sIdx)"
                        class="group flex cursor-pointer select-none items-center justify-between text-gray-600 transition duration-150 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        <div class="flex min-w-0 items-center gap-2">
                            <!-- Micro search icon -->
                            <!-- Spinning loader icon for running steps (Perplexity style) -->
                            <svg
                                v-if="step.status === 'running'"
                                class="h-3.5 w-3.5 shrink-0 animate-spin text-blue-500"
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
                            <!-- Static search icon for completed steps -->
                            <svg
                                v-else
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
                                :class="[
                                    'truncate font-medium transition-colors duration-200',
                                    step.status === 'running'
                                        ? 'font-semibold text-blue-600 dark:text-blue-400'
                                        : 'text-gray-700 dark:text-gray-300',
                                ]"
                            >
                                {{ getFriendlyToolName(step.tool) }}
                            </span>
                            <!-- Animated three-dot bounce loader (Perplexity thinking style) -->
                            <span
                                v-if="step.status === 'running'"
                                class="ml-1 inline-flex shrink-0 items-center gap-0.5 text-blue-500/80"
                            >
                                <span
                                    class="h-1 w-1 animate-bounce rounded-full bg-current"
                                    style="animation-delay: 0ms"
                                ></span>
                                <span
                                    class="h-1 w-1 animate-bounce rounded-full bg-current"
                                    style="animation-delay: 150ms"
                                ></span>
                                <span
                                    class="h-1 w-1 animate-bounce rounded-full bg-current"
                                    style="animation-delay: 300ms"
                                ></span>
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
                                    expandedStepIds[sIdx]
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

                    <!-- Dynamic Parameter & Query Discovery Capsule (Perplexity Style) -->
                    <div
                        v-if="getStepMetadata(step)"
                        class="animate-fade-in mt-1.5 flex flex-wrap gap-1.5 pl-[22px]"
                    >
                        <span
                            class="inline-flex items-center gap-1 rounded-full border border-blue-100/40 bg-blue-50/80 px-2.5 py-0.5 text-[9px] font-semibold text-blue-600 shadow-sm dark:border-blue-900/20 dark:bg-blue-950/20 dark:text-blue-400"
                        >
                            <svg
                                class="h-2.5 w-2.5 shrink-0 text-blue-500"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2.5"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                            {{ getStepMetadata(step) }}
                        </span>
                    </div>

                    <!-- Collapsible Response JSON detail -->
                    <div
                        v-if="expandedStepIds[sIdx] && step.result"
                        class="group/code relative mt-1.5 max-h-48 overflow-y-auto rounded-xl border border-gray-200/40 bg-gray-50/50 p-3 font-mono text-[10px] text-gray-600 dark:border-gray-800/40 dark:bg-gray-950/20 dark:text-gray-400"
                    >
                        <!-- Absolute Copy/Status HUD -->
                        <div
                            class="absolute right-2 top-2 flex items-center gap-1.5 opacity-0 transition duration-200 group-hover/code:opacity-100"
                        >
                            <span
                                v-if="showCopiedAlert === sIdx"
                                class="animate-fade-in rounded border border-green-200 bg-white px-1 py-0.5 font-sans text-[9px] font-semibold text-green-500 shadow-sm dark:border-green-800 dark:bg-gray-900"
                            >
                                Copied!
                            </span>
                            <button
                                @click.stop="
                                    copyToClipboard(
                                        JSON.stringify(step.result, null, 2),
                                        sIdx,
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
        </TransitionGroup>
    </div>
</template>

<style scoped>
.step-fade-enter-active,
.step-fade-leave-active {
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.step-fade-enter-from,
.step-fade-leave-to {
    opacity: 0;
    transform: translateY(12px);
}
</style>
