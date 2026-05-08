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
    <div class="inline-flex rounded-lg border border-slate-200 bg-slate-100 p-1">
        <button
            v-for="tab in tabs"
            :key="tab.value"
            :class="[
                'focus-ring inline-flex h-8 items-center gap-2 rounded-md px-3 text-sm font-semibold transition',
                modelValue === tab.value ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-900',
            ]"
            @click="emit('update:modelValue', tab.value)"
        >
            {{ tab.label }}
            <span v-if="typeof tab.count === 'number'" class="rounded-full bg-slate-200 px-1.5 text-xs text-slate-700">{{ tab.count }}</span>
        </button>
    </div>
</template>
