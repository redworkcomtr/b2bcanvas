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
            'focus-ring grid cursor-pointer place-items-center rounded-lg border border-dashed p-6 text-center transition',
            dragging ? 'border-teal-500 bg-teal-50' : 'border-slate-300 bg-slate-50 hover:border-teal-400 hover:bg-teal-50/50',
        ]"
        tabindex="0"
        @dragenter.prevent="dragging = true"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="dragging = false; selectFile($event.dataTransfer?.files?.[0])"
    >
        <UploadCloud class="h-8 w-8 text-teal-700" />
        <span class="mt-3 text-sm font-bold text-slate-950">{{ label }}</span>
        <span v-if="description" class="mt-1 max-w-sm text-sm text-slate-500">{{ description }}</span>
        <input class="sr-only" type="file" :accept="accept" @change="selectFile(($event.target as HTMLInputElement).files?.[0])">
    </label>
</template>
