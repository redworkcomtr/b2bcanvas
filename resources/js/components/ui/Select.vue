<script setup lang="ts">
defineProps<{
    label?: string;
    modelValue?: string | number | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <label class="grid gap-1.5 text-sm font-medium text-slate-700">
        <span v-if="label">{{ label }}</span>
        <select
            class="focus-ring h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm"
            :value="modelValue ?? ''"
            @change="emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
        >
            <slot />
        </select>
    </label>
</template>
