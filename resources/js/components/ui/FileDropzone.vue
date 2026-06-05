<script setup lang="ts">
import { UploadCloud } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    label: string;
    description?: string;
    accept?: string;
}>();

const emit = defineEmits<{
    selected: [file: File];
}>();

const dragging = ref(false);

function selectFile(file?: File) {
    if (file) {
        emit('selected', file);
    }
}
</script>

<template>
    <label
        :class="[
            'focus-ring grid cursor-pointer place-items-center rounded-lg border border-dashed p-6 text-center transition-colors',
            dragging ? 'border-zinc-400 bg-white' : 'border-zinc-300 bg-zinc-50 hover:bg-white',
        ]"
        tabindex="0"
        @dragenter.prevent="dragging = true"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="dragging = false; selectFile($event.dataTransfer?.files?.[0])"
    >
        <div class="grid h-10 w-10 place-items-center rounded-md border border-zinc-200 bg-white text-zinc-700">
            <UploadCloud class="h-5 w-5" />
        </div>
        <span class="mt-3 text-[14px] font-semibold text-zinc-950">{{ label }}</span>
        <span v-if="description" class="mt-1 max-w-sm text-[14px] leading-6 text-zinc-500">{{ description }}</span>
        <input class="sr-only" type="file" :accept="accept" @change="selectFile(($event.target as HTMLInputElement).files?.[0])">
    </label>
</template>
