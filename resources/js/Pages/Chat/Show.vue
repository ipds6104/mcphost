<script setup lang="ts">
import ChatInput from '@/Components/Chat/ChatInput.vue';
import ChatMessages from '@/Components/Chat/ChatMessages.vue';
import ChatSidebar from '@/Components/Chat/ChatSidebar.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Echo from 'laravel-echo';
import { onMounted, onUnmounted, ref, shallowRef } from 'vue';

interface Chat {
    id: string;
    title: string;
    is_pinned: boolean | number;
    created_at: string;
}

interface AgentStep {
    step: number;
    tool: string;
    status: 'running' | 'success' | 'failed';
    result?: unknown;
}

interface ChartData {
    title: string;
    type: 'bar' | 'line';
    labels: string[];
    datasets: {
        label?: string;
        data: number[];
    }[];
}

interface Message {
    id: string | number;
    role: 'user' | 'assistant';
    content: string;
    attachments?: string[];
    is_loading?: boolean;
    agent_steps?: AgentStep[];
    chart_data?: ChartData | null;
    created_at: string;
}

const props = defineProps<{
    chats: Chat[];
    currentChat: Chat;
    messages: Message[];
}>();

const localMessages = ref<Message[]>([...props.messages]);
const form = useForm({
    content: '',
    images: [] as File[],
});

// Pelacakan Object URL lokal untuk mencegah kebocoran memori (Memory Leak Protection)
const objectUrls = ref<string[]>([]);

// Modular Helper 1: Membuat optimistic messages di UI
const createOptimisticMessages = (
    content: string,
    previews: string[],
    tempUserMsgId: string,
    tempAssistantMsgId: string,
) => {
    // Masukkan pesan user secara instan
    localMessages.value.push({
        id: tempUserMsgId,
        role: 'user',
        content: content,
        attachments: previews,
        created_at: new Date().toISOString(),
    });

    // Tampilkan loading asisten dengan efek BPS Aurora Sparkle
    localMessages.value.push({
        id: tempAssistantMsgId,
        role: 'assistant',
        content: '',
        is_loading: true,
        agent_steps: [],
        created_at: new Date().toISOString(),
    });
};

// Modular Helper 2: Menghapus optimistic messages jika pengiriman gagal
const removeOptimisticMessages = (
    tempUserMsgId: string,
    tempAssistantMsgId: string,
) => {
    localMessages.value = localMessages.value.filter(
        (m) => m.id !== tempUserMsgId && m.id !== tempAssistantMsgId,
    );
};

// Penanganan pengiriman pesan & UX instan (Orchestrator)
const handleSend = ({
    content,
    images,
}: {
    content: string;
    images: File[];
}) => {
    form.content = content;
    form.images = images;

    // UUID collision-free untuk ID pesan sementara (Senior-level standard)
    const tempUserMsgId = `temp-${crypto.randomUUID()}`;
    const tempAssistantMsgId = `temp-${crypto.randomUUID()}`;

    // Konversi file lokal menjadi Object URL dengan tracking memori untuk revoke kelak
    const localPreviews = images.map((file) => {
        const url = URL.createObjectURL(file);
        objectUrls.value.push(url);
        return url;
    });

    createOptimisticMessages(
        content,
        localPreviews,
        tempUserMsgId,
        tempAssistantMsgId,
    );

    // Kirim Inertia form ke controller backend
    form.post(route('chats.messages.store', props.currentChat.id), {
        onSuccess: () => {
            form.reset();
        },
        onError: () => {
            removeOptimisticMessages(tempUserMsgId, tempAssistantMsgId);
        },
    });
};

// Pengelolaan Laravel Echo WebSocket (Reverb) - Menggunakan shallowRef (Senior performance optimization)
const echoInstance = shallowRef<Echo<'reverb'> | null>(null);

onMounted(() => {
    // window.Pusher diinisialisasi secara bersih di bootstrap.js untuk mencegah global mutation anti-pattern

    echoInstance.value = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    echoInstance.value
        .private(`chats.${props.currentChat.id}`)
        .listen(
            'AgentStepStarted',
            (event: { stepIndex: number; toolName: string }) => {
                // Immutable Update Pattern untuk menjamin reaktivitas penuh pada objek bersarang
                const idx = localMessages.value.findIndex(
                    (m) => m.is_loading === true,
                );
                if (idx !== -1) {
                    const currentMsg = localMessages.value[idx];
                    const steps = currentMsg.agent_steps
                        ? [...currentMsg.agent_steps]
                        : [];
                    steps.push({
                        step: event.stepIndex,
                        tool: event.toolName,
                        status: 'running',
                    });
                    localMessages.value[idx] = {
                        ...currentMsg,
                        agent_steps: steps,
                    };
                }
            },
        )
        .listen(
            'AgentStepCompleted',
            (event: { stepIndex: number; result: unknown }) => {
                // Immutable Update Pattern untuk status langkah berpikir MCP
                const idx = localMessages.value.findIndex(
                    (m) => m.is_loading === true,
                );
                if (idx !== -1) {
                    const currentMsg = localMessages.value[idx];
                    if (currentMsg.agent_steps) {
                        const steps = currentMsg.agent_steps.map((s) => {
                            if (s.step === event.stepIndex) {
                                return {
                                    ...s,
                                    status: 'success' as const,
                                    result: event.result,
                                };
                            }
                            return s;
                        });
                        localMessages.value[idx] = {
                            ...currentMsg,
                            agent_steps: steps,
                        };
                    }
                }
            },
        )
        .listen(
            'AgentResponseGenerated',
            (event: {
                messageId: string;
                content: string;
                chartData: ChartData | null;
            }) => {
                const loadingIdx = localMessages.value.findIndex(
                    (m) => m.is_loading === true,
                );
                const prevSteps =
                    loadingIdx !== -1
                        ? localMessages.value[loadingIdx].agent_steps
                        : [];

                if (loadingIdx !== -1) {
                    localMessages.value.splice(loadingIdx, 1);
                }

                localMessages.value.push({
                    id: event.messageId,
                    role: 'assistant',
                    content: event.content,
                    chart_data: event.chartData,
                    agent_steps: prevSteps,
                    created_at: new Date().toISOString(),
                });
            },
        );
});

onUnmounted(() => {
    if (echoInstance.value) {
        echoInstance.value.leave(`chats.${props.currentChat.id}`);
    }
    // Revoke seluruh Object URL yang dialokasikan di memori browser (Pencegahan kebocoran memori level senior)
    objectUrls.value.forEach((url) => URL.revokeObjectURL(url));
});
</script>

<template>
    <Head :title="currentChat.title" />

    <AuthenticatedLayout>
        <div
            class="flex h-[calc(100vh-64px)] overflow-hidden bg-[#f0f4f9] font-sans dark:bg-[#131314]"
        >
            <!-- Sidebar Sesi Kiri (Datar & Dikecualikan Garis Pembatas Kaku) -->
            <ChatSidebar
                :chats="chats"
                :currentChat="currentChat"
                class="hidden md:flex"
            />

            <!-- Viewport Obrolan Utama -->
            <div
                class="flex min-w-0 flex-1 flex-col bg-[#f0f4f9] dark:bg-[#131314]"
            >
                <!-- Header Atas Transparan/Translucent -->
                <div
                    class="border-gray-250/20 z-10 flex h-16 shrink-0 select-none items-center justify-between border-b bg-white/40 px-6 backdrop-blur-md dark:border-gray-800/20 dark:bg-[#131314]/40"
                >
                    <div class="flex items-center gap-3">
                        <Link
                            href="/dashboard"
                            class="dark:hover:bg-gray-850/50 mr-1 rounded-full p-1.5 text-gray-500 transition hover:bg-gray-200/50 md:hidden"
                        >
                            <svg
                                class="w-5.5 h-5.5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M15 19l-7-7 7-7"
                                />
                            </svg>
                        </Link>
                        <div>
                            <h2
                                class="max-w-xs truncate font-sans text-xs font-extrabold tracking-wide text-gray-800 md:max-w-md dark:text-white"
                            >
                                {{ currentChat.title }}
                            </h2>
                            <p
                                class="font-sans text-[9px] font-extrabold uppercase tracking-widest text-gray-400 dark:text-gray-500"
                            >
                                Kab. Mempawah • SPESIAL AI
                            </p>
                        </div>
                    </div>

                    <!-- Status Badge Server Actives -->
                    <div class="flex items-center gap-3">
                        <div
                            class="flex items-center gap-1.5 rounded-full border border-green-200/30 bg-green-50 px-3 py-1 font-sans text-[9px] font-extrabold uppercase tracking-wider text-green-700 dark:bg-green-950/30 dark:text-green-400"
                        >
                            <span
                                class="h-1.5 w-1.5 animate-pulse rounded-full bg-green-500"
                            ></span>
                            BPS Active
                        </div>
                    </div>
                </div>

                <!-- Daftar Aliran Pesan Obrolan -->
                <ChatMessages :messages="localMessages" />

                <!-- Footer Input Kapsul Mengambang (Fade Gradient Background) -->
                <div
                    class="z-10 shrink-0 bg-gradient-to-t from-[#f0f4f9] via-[#f0f4f9] to-transparent pb-6 pt-2 dark:from-[#131314] dark:via-[#131314] dark:to-transparent"
                >
                    <ChatInput
                        :processing="form.processing"
                        @send="handleSend"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
