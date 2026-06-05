<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

const open = ref(false);
const root = ref<HTMLElement | null>(null);

function onDocumentClick(event: MouseEvent) {
    if (root.value && !root.value.contains(event.target as Node)) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('click', onDocumentClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick));
</script>

<template>
    <div ref="root" class="relative">
        <div @click="open = !open">
            <slot name="trigger" :open="open" />
        </div>
        <div
            v-if="open"
            class="absolute right-0 z-40 mt-2 min-w-64 overflow-hidden rounded-lg border border-zinc-200/80 bg-white p-1 shadow-[0_14px_36px_-22px_rgba(15,23,42,0.45)]"
        >
            <slot :close="() => (open = false)" />
        </div>
    </div>
</template>
