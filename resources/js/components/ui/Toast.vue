<script setup lang="ts">
import { CheckCircle2, Info, TriangleAlert, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(defineProps<{
    tone?: 'success' | 'info' | 'warning' | 'danger';
    title: string;
    description?: string;
}>(), {
    tone: 'info',
});

const icon = computed(() => ({
    success: CheckCircle2,
    info: Info,
    warning: TriangleAlert,
    danger: XCircle,
}[props.tone]));

const toneClass = computed(() => ({
    success: 'text-emerald-600',
    info: 'text-blue-600',
    warning: 'text-amber-600',
    danger: 'text-red-600',
}[props.tone]));
</script>

<template>
    <div class="flex w-full max-w-sm gap-3 rounded-lg border border-zinc-200 bg-white p-4 shadow-[0_14px_36px_-22px_rgba(15,23,42,0.45)]">
        <component :is="icon" :class="['mt-0.5 h-5 w-5 shrink-0', toneClass]" />
        <div>
            <p class="text-[14px] font-semibold text-zinc-950">{{ title }}</p>
            <p v-if="description" class="mt-1 text-[14px] leading-6 text-zinc-500">{{ description }}</p>
        </div>
    </div>
</template>
