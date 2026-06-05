<script setup lang="ts">
defineProps<{
    label?: string;
    modelValue?: string | number | null;
    required?: boolean;
    help?: string;
    error?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <label class="grid min-w-0 gap-1.5 text-[14px] font-medium text-zinc-700">
        <span v-if="label" class="text-[12px] font-semibold text-zinc-600">{{ label }} <span v-if="required" class="text-red-600">*</span></span>
        <select
            :class="[
                'focus-ring h-10 min-w-0 rounded-md border bg-white px-3 text-[14px] font-medium text-zinc-950 shadow-[inset_0_1px_1px_rgba(15,23,42,0.03)]',
                error ? 'border-red-300' : 'border-zinc-300/80',
            ]"
            :value="modelValue ?? ''"
            @change="emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
        >
            <slot />
        </select>
        <span v-if="error" class="text-xs font-medium text-red-600">{{ error }}</span>
        <span v-else-if="help" class="text-xs font-normal text-zinc-500">{{ help }}</span>
    </label>
</template>
