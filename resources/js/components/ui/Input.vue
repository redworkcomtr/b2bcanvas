<script setup lang="ts">
defineProps<{
    label?: string;
    modelValue?: string | number;
    type?: string;
    placeholder?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <label class="grid gap-1.5 text-sm font-medium text-slate-700">
        <span v-if="label">{{ label }}</span>
        <input
            class="focus-ring h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm placeholder:text-slate-400"
            :type="type ?? 'text'"
            :value="modelValue"
            :placeholder="placeholder"
            @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        >
    </label>
</template>
