<script setup lang="ts">
defineProps<{
    tabs: Array<{ label: string; value: string; count?: number }>;
    modelValue: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <div class="inline-flex max-w-full overflow-auto rounded-lg border border-zinc-200/80 bg-zinc-50 p-1">
        <button
            v-for="tab in tabs"
            :key="tab.value"
            :class="[
                'focus-ring inline-flex h-8 items-center gap-2 rounded-md px-3 text-[14px] font-semibold transition-colors',
                modelValue === tab.value ? 'border border-zinc-200 bg-white text-zinc-950 shadow-[0_1px_1px_rgba(15,23,42,0.04)]' : 'border border-transparent text-zinc-500 hover:bg-white hover:text-zinc-950',
            ]"
            @click="emit('update:modelValue', tab.value)"
        >
            {{ tab.label }}
            <span v-if="typeof tab.count === 'number'" class="rounded bg-zinc-200 px-1.5 text-xs text-zinc-700">{{ tab.count }}</span>
        </button>
    </div>
</template>
