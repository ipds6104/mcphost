<script setup lang="ts">
import { ref } from 'vue';

interface AgentStep {
    step: number;
    tool: string;
    status: 'running' | 'success' | 'failed';
    result?: unknown;
}

defineProps<{
    steps: AgentStep[];
}>();

const activeStepDetail = ref<number | null>(null);

const toggleStepDetail = (stepIndex: number) => {
    activeStepDetail.value =
        activeStepDetail.value === stepIndex ? null : stepIndex;
};
</script>

<template>
    <div class="border-gray-150/40 mt-4 border-t pt-3 dark:border-gray-800/40">
        <!-- Header Section -->
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

        <!-- Step List -->
        <div class="space-y-1.5">
            <div
                v-for="(step, sIdx) in steps"
                :key="sIdx"
                class="overflow-hidden rounded-xl border border-gray-200/50 bg-white/40 dark:border-gray-800/60 dark:bg-[#1e1f20]/20"
            >
                <!-- Clickable Step Row -->
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
                            v-else-if="step.status === 'success'"
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
                        <span v-else class="shrink-0 text-red-500">
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
                            activeStepDetail === step.step ? 'rotate-180' : '',
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
                    v-if="activeStepDetail === step.step && step.result"
                    class="border-gray-150/40 max-h-60 overflow-x-auto border-t bg-gray-50/50 p-3 font-mono text-[10px] text-gray-600 dark:border-gray-800/40 dark:bg-gray-950/20 dark:text-gray-400"
                >
                    <pre class="leading-relaxed">{{
                        JSON.stringify(step.result, null, 2)
                    }}</pre>
                </div>
            </div>
        </div>
    </div>
</template>
