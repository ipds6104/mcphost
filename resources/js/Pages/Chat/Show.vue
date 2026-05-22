<script setup lang="ts">
import ChatInput from '@/Components/Chat/ChatInput.vue';
import ChatMessages from '@/Components/Chat/ChatMessages.vue';
import ChatSidebar from '@/Components/Chat/ChatSidebar.vue';
import { useEcho } from '@/composables/useEcho';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, watch } from 'vue';

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
    currentChat: Chat | null;
    messages: Message[];
}>();

const isSidebarOpen = ref(true);
onMounted(() => {
    if (window.innerWidth < 768) {
        isSidebarOpen.value = false;
    }

    // Pemulihan State Latar Belakang (Transient State Auto-Recovery - Mei 2026 Best Practice)
    // Jika pesan terakhir di chat adalah dari user, asumsikan asisten AI sedang
    // memproses di latar belakang (misal setelah refresh halaman).
    if (localMessages.value.length > 0) {
        const lastMsg = localMessages.value[localMessages.value.length - 1];
        if (lastMsg.role === 'user') {
            isAiProcessing.value = true;

            // Masukkan temp-loader jika belum ada agar loader visual berputar dan Echo mendengarkan
            const hasLoader = localMessages.value.some(
                (m) => m.id === 'temp-loader',
            );
            if (!hasLoader) {
                localMessages.value.push({
                    id: 'temp-loader',
                    role: 'assistant',
                    content: '',
                    is_loading: true,
                    agent_steps: [...activeAgentSteps.value],
                    created_at: new Date().toISOString(),
                });
            }
        }
    }
});
const page = usePage();
watch(
    () => page.url,
    () => {
        if (window.innerWidth < 768) {
            isSidebarOpen.value = false;
        }
    },
);

const localMessages = ref<Message[]>([...props.messages]);

const isAiProcessing = ref(false);
const activeAgentSteps = ref<AgentStep[]>([]);
const queryStartTime = ref<number | null>(null);
const stepStartTimes = ref<Record<number, number>>({});

watch(
    () => props.messages,
    (newVal) => {
        localMessages.value = [...newVal];
        if (isAiProcessing.value) {
            localMessages.value.push({
                id: 'temp-loader',
                role: 'assistant',
                content: '',
                is_loading: true,
                agent_steps: [...activeAgentSteps.value],
                created_at: new Date().toISOString(),
            });
        }
    },
    { deep: true },
);
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
    queryStartTime.value = performance.now();
    console.log(`🚀 [Chat] Prompt sent: "${content}"`);
    form.content = content;
    form.images = images;

    // UUID collision-free untuk ID pesan sementara (Senior-level standard)
    const tempUserMsgId = `temp-${crypto.randomUUID()}`;

    // Konversi file lokal menjadi Object URL dengan tracking memori untuk revoke kelak
    const localPreviews = images.map((file) => {
        const url = URL.createObjectURL(file);
        objectUrls.value.push(url);
        return url;
    });

    isAiProcessing.value = true;
    activeAgentSteps.value = [];

    createOptimisticMessages(
        content,
        localPreviews,
        tempUserMsgId,
        'temp-loader',
    );

    // Kirim Inertia form ke controller backend secara dinamis
    if (!props.currentChat) {
        form.post(route('chats.store'), {
            onSuccess: () => {
                form.reset();
            },
            onError: () => {
                isAiProcessing.value = false;
                activeAgentSteps.value = [];
                removeOptimisticMessages(tempUserMsgId, 'temp-loader');
            },
        });
    } else {
        form.post(route('chats.messages.store', props.currentChat.id), {
            onSuccess: () => {
                form.reset();
            },
            onError: () => {
                isAiProcessing.value = false;
                activeAgentSteps.value = [];
                removeOptimisticMessages(tempUserMsgId, 'temp-loader');
            },
        });
    }
};

// Pengelolaan Laravel Echo WebSocket (Reverb) menggunakan composable useEcho (Standar Industri Mei 2026)
const { listenPrivate, leaveChannel } = useEcho();
const currentSubscribedChannel = ref<string | null>(null);

watch(
    () => props.currentChat,
    (newChat) => {
        // Lepas channel lama jika ada
        if (currentSubscribedChannel.value) {
            leaveChannel(currentSubscribedChannel.value);
            currentSubscribedChannel.value = null;
        }

        if (newChat) {
            const channelName = `chats.${newChat.id}`;
            currentSubscribedChannel.value = channelName;

            listenPrivate(channelName, [
                {
                    name: 'AgentStepStarted',
                    callback: (event: {
                        stepIndex: number;
                        stepName?: string;
                        toolName?: string;
                    }) => {
                        stepStartTimes.value[event.stepIndex] =
                            performance.now();
                        const stepName =
                            event.stepName ||
                            event.toolName ||
                            'Mengakses BPS MCP Tool';
                        console.log(
                            `🤖 [AI Step Started] Step #${event.stepIndex}: "${stepName}"`,
                        );

                        const step: AgentStep = {
                            step: event.stepIndex,
                            tool: stepName,
                            status: 'running',
                        };
                        activeAgentSteps.value.push(step);

                        const idx = localMessages.value.findIndex(
                            (m) => m.id === 'temp-loader',
                        );
                        if (idx !== -1) {
                            localMessages.value[idx] = {
                                ...localMessages.value[idx],
                                agent_steps: [...activeAgentSteps.value],
                            };
                        }
                    },
                },
                {
                    name: 'AgentStepCompleted',
                    callback: (event: {
                        stepIndex: number;
                        stepName?: string;
                        toolName?: string;
                        output?: unknown;
                        result?: unknown;
                    }) => {
                        const stepName =
                            event.stepName ||
                            event.toolName ||
                            'Mengakses BPS MCP Tool';
                        const outputData =
                            event.output !== undefined
                                ? event.output
                                : event.result;
                        let stepDurationStr = '';
                        if (stepStartTimes.value[event.stepIndex]) {
                            const stepDuration = (
                                (performance.now() -
                                    stepStartTimes.value[event.stepIndex]) /
                                1000
                            ).toFixed(2);
                            stepDurationStr = ` in ${stepDuration}s`;
                            delete stepStartTimes.value[event.stepIndex];
                        }
                        console.log(
                            `✅ [AI Step Completed] Step #${event.stepIndex}: "${stepName}"${stepDurationStr}`,
                            {
                                output: outputData,
                            },
                        );

                        activeAgentSteps.value = activeAgentSteps.value.map(
                            (s) => {
                                if (s.step === event.stepIndex) {
                                    return {
                                        ...s,
                                        status: 'success' as const,
                                        result: outputData,
                                    };
                                }
                                return s;
                            },
                        );

                        const idx = localMessages.value.findIndex(
                            (m) => m.id === 'temp-loader',
                        );
                        if (idx !== -1) {
                            localMessages.value[idx] = {
                                ...localMessages.value[idx],
                                agent_steps: [...activeAgentSteps.value],
                            };
                        }
                    },
                },
                {
                    name: 'AgentResponseGenerated',
                    callback: (event: {
                        messageId: string;
                        content: string;
                        chartData: ChartData | null;
                    }) => {
                        isAiProcessing.value = false;
                        const prevSteps = [...activeAgentSteps.value];
                        activeAgentSteps.value = [];

                        const loadingIdx = localMessages.value.findIndex(
                            (m) => m.id === 'temp-loader',
                        );
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

                        // Calculate and log total duration
                        if (queryStartTime.value !== null) {
                            const duration = (
                                (performance.now() - queryStartTime.value) /
                                1000
                            ).toFixed(2);
                            console.log(
                                `⏱️ [Chat] AI response fully received in ${duration}s.`,
                            );
                            queryStartTime.value = null;
                        }
                    },
                },
            ]);
        }
    },
    { immediate: true },
);

const chatInputRef = ref<InstanceType<typeof ChatInput> | null>(null);

// Sunting perintah user
const handleEditMessage = (content: string) => {
    if (chatInputRef.value) {
        chatInputRef.value.content = content;
        chatInputRef.value.focus();
    }
};

// Buat ulang jawaban AI (Regenerate)
const handleRegenerateMessage = (messageId: string | number) => {
    if (!props.currentChat) return;

    // Cari indeks pesan asisten
    const idx = localMessages.value.findIndex((m) => m.id === messageId);
    if (idx === -1) return;

    queryStartTime.value = performance.now();
    console.log(
        `🔄 [Chat] Regenerate message requested for message ID: ${messageId}`,
    );

    // Hapus pesan asisten dan tampilkan state loading
    localMessages.value.splice(idx, 1);

    isAiProcessing.value = true;
    activeAgentSteps.value = [];

    // Siapkan temporary assistant message untuk loader visual
    localMessages.value.push({
        id: 'temp-loader',
        role: 'assistant',
        content: '',
        is_loading: true,
        agent_steps: [],
        created_at: new Date().toISOString(),
    });

    // Jalankan request ke backend untuk men-delete dan men-dispatch ulang agent job
    router.post(
        route('chats.messages.regenerate', {
            chat: props.currentChat.id,
            message: messageId,
        }),
        {},
        {
            onFinish: () => {
                // Biarkan websocket atau inertia sync menangani data terbaru
            },
            onError: () => {
                // Rollback jika terjadi kesalahan koneksi
                isAiProcessing.value = false;
                activeAgentSteps.value = [];
                router.reload();
            },
        },
    );
};

// Mulai percakapan baru
const handleNewChat = () => {
    router.visit(route('dashboard'));
};

onUnmounted(() => {
    // Revoke seluruh Object URL yang dialokasikan di memori browser (Pencegahan kebocoran memori level senior)
    objectUrls.value.forEach((url) => URL.revokeObjectURL(url));
});
</script>

<template>
    <Head :title="currentChat ? currentChat.title : 'Percakapan Baru'" />

    <AuthenticatedLayout hideNav>
        <div
            class="flex h-screen overflow-hidden bg-[#f0f4f9] font-sans dark:bg-[#131314]"
        >
            <!-- Sidebar Sesi Kiri (Datar & Dikecualikan Garis Pembatas Kaku) -->
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
                        :currentChat="currentChat"
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

            <!-- Viewport Obrolan Utama -->
            <div
                class="flex min-w-0 flex-1 flex-col bg-[#f0f4f9] dark:bg-[#131314]"
            >
                <!-- Header Atas Transparan/Translucent -->
                <div
                    class="border-gray-250/20 z-10 flex h-16 shrink-0 select-none items-center justify-between border-b bg-gradient-to-b from-white/90 via-white/50 to-white/20 px-6 backdrop-blur-md dark:border-gray-800/20 dark:from-[#131314]/90 dark:via-[#131314]/50 dark:to-[#131314]/20"
                >
                    <div class="flex items-center gap-3">
                        <!-- Hamburger Menu Button (visible when sidebar is closed) -->
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
                                class="max-w-xs truncate font-sans text-xs font-extrabold tracking-wide text-gray-800 md:max-w-md dark:text-white"
                            >
                                {{
                                    currentChat
                                        ? currentChat.title
                                        : 'Percakapan Baru'
                                }}
                            </h2>
                        </div>
                    </div>
                </div>

                <!-- Daftar Aliran Pesan Obrolan -->
                <ChatMessages
                    :messages="localMessages"
                    @select-prompt="
                        (p) => handleSend({ content: p, images: [] })
                    "
                    @edit-message="handleEditMessage"
                    @regenerate-message="handleRegenerateMessage"
                    @new-chat="handleNewChat"
                />

                <!-- Footer Input Kapsul Mengambang (Fade Gradient Background) -->
                <div
                    class="z-10 shrink-0 bg-gradient-to-t from-[#f0f4f9] via-[#f0f4f9] to-transparent pb-6 pt-2 dark:from-[#131314] dark:via-[#131314] dark:to-transparent"
                >
                    <ChatInput
                        ref="chatInputRef"
                        :processing="form.processing"
                        @send="handleSend"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
