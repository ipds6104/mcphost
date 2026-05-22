<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';

interface SendPayload {
    content: string;
    images: File[];
}

const props = defineProps<{
    processing: boolean;
}>();

const emit = defineEmits<{
    (e: 'send', payload: SendPayload): void;
}>();

const content = ref('');
const images = ref<File[]>([]);
const imagePreviews = ref<{ file: File; url: string }[]>([]);
const fileInput = ref<HTMLInputElement | null>(null);
const textareaRef = ref<HTMLTextAreaElement | null>(null);

// Mengatur tinggi otomatis textarea (autogrow) secara responsif
const adjustHeight = () => {
    nextTick(() => {
        const el = textareaRef.value;
        if (el) {
            el.style.height = 'auto';
            const newHeight = Math.min(el.scrollHeight, 144); // Maksimal tinggi 144px (scrollbar muncul jika lebih)
            el.style.height = `${newHeight}px`;
        }
    });
};

watch(content, adjustHeight);

const triggerImageUpload = () => {
    fileInput.value?.click();
};

const handleImageChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (!target.files) return;

    const files = Array.from(target.files);
    files.forEach((file) => {
        if (!file.type.startsWith('image/')) return;
        images.value.push(file);

        const reader = new FileReader();
        reader.onload = (event) => {
            if (event.target?.result) {
                imagePreviews.value.push({
                    file,
                    url: event.target.result as string,
                });
            }
        };
        reader.readAsDataURL(file);
    });

    // Reset file input value agar user bisa mengunggah berkas yang sama jika diperlukan
    if (fileInput.value) fileInput.value.value = '';
};

const removeImagePreview = (index: number) => {
    images.value.splice(index, 1);
    imagePreviews.value.splice(index, 1);
    adjustHeight();
};

const onSubmit = () => {
    if (props.processing) return;
    const trimmedContent = content.value.trim();
    if (!trimmedContent && images.value.length === 0) return;

    emit('send', {
        content: trimmedContent,
        images: [...images.value],
    });

    // Reset state lokal setelah kirim
    content.value = '';
    images.value = [];
    imagePreviews.value = [];

    // Reset tinggi textarea
    nextTick(() => {
        if (textareaRef.value) {
            textareaRef.value.style.height = 'auto';
        }
    });
};

const handleKeydown = (e: KeyboardEvent) => {
    // Kirim jika menekan Enter tanpa tombol Shift
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        onSubmit();
    }
};

defineExpose({
    content,
    textareaRef,
    focus: () => textareaRef.value?.focus(),
});
</script>

<template>
    <div class="mx-auto w-full max-w-4xl px-4 md:px-0">
        <form @submit.prevent="onSubmit" class="relative">
            <!-- Container Kapsul Utama -->
            <div
                class="flex w-full flex-col rounded-[28px] border border-gray-200/80 bg-white p-2 shadow-sm transition-all duration-300 focus-within:border-blue-500/40 focus-within:ring-2 focus-within:ring-blue-500/20 dark:border-gray-800 dark:bg-[#1e1f20]"
            >
                <!-- Pratinjau Gambar di Dalam Kapsul (Gaya Premium Gemini) -->
                <div
                    v-if="imagePreviews.length > 0"
                    class="mb-2 flex flex-wrap gap-2 border-b border-gray-100 px-3 pb-1 pt-2 dark:border-gray-800/60"
                >
                    <div
                        v-for="(preview, idx) in imagePreviews"
                        :key="idx"
                        class="group/thumb relative h-16 w-16 shrink-0 overflow-hidden rounded-xl border border-black/5 shadow-sm transition-transform duration-200 hover:scale-105 dark:border-white/5"
                    >
                        <img
                            :src="preview.url"
                            class="h-full w-full object-cover"
                        />
                        <button
                            type="button"
                            @click="removeImagePreview(idx)"
                            class="absolute right-1 top-1 rounded-full bg-red-600/90 p-1 text-white opacity-90 shadow transition hover:bg-red-700 group-hover/thumb:opacity-100"
                        >
                            <svg
                                class="h-3 w-3"
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
                </div>

                <!-- Baris Input & Aksi -->
                <div class="flex w-full items-end gap-1.5">
                    <!-- Hidden Image File input -->
                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/*"
                        multiple
                        class="hidden"
                        @change="handleImageChange"
                    />

                    <!-- Tombol Upload Gambar (Landscape Icon) -->
                    <button
                        type="button"
                        @click="triggerImageUpload"
                        class="shrink-0 self-center rounded-full p-3 text-gray-500 transition hover:bg-gray-100 hover:text-blue-600 dark:hover:bg-gray-800 dark:hover:text-blue-400"
                        title="Lampirkan Gambar"
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
                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                            />
                        </svg>
                    </button>

                    <!-- Textarea Utama -->
                    <textarea
                        ref="textareaRef"
                        v-model="content"
                        rows="1"
                        placeholder="Tanyakan analisis statistik daerah BPS..."
                        class="max-h-36 flex-1 resize-none border-0 bg-transparent px-2 py-2.5 font-sans text-[13px] leading-relaxed text-gray-900 placeholder-gray-500 ring-0 focus:border-0 focus:outline-none focus:ring-0 dark:text-white dark:placeholder-gray-400"
                        @keydown="handleKeydown"
                    ></textarea>

                    <!-- Tombol Kirim Bundar Minimalis -->
                    <button
                        type="submit"
                        :disabled="
                            processing ||
                            (!content.trim() && images.length === 0)
                        "
                        :class="[
                            'shrink-0 self-center rounded-full p-3 transition-all duration-300',
                            content.trim() || images.length > 0
                                ? 'bg-blue-600 text-white shadow-md hover:scale-105 hover:bg-blue-700'
                                : 'cursor-not-allowed bg-transparent text-gray-400 dark:text-gray-600',
                        ]"
                    >
                        <svg
                            class="h-4 w-4 rotate-90 transform"
                            fill="currentColor"
                            viewBox="0 0 20 20"
                        >
                            <path
                                d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"
                            />
                        </svg>
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>
