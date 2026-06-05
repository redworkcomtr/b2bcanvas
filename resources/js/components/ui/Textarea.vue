<script setup lang="ts">
defineProps<{
    label?: string;
    modelValue?: string;
    placeholder?: string;
    required?: boolean;
    help?: string;
    error?: string;
    rows?: number;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <label class="grid gap-1.5 text-[14px] font-medium text-zinc-700">
        <span v-if="label" class="text-[12px] font-semibold text-zinc-600">{{ label }} <span v-if="required" class="text-red-600">*</span></span>
        <textarea
            :rows="rows ?? 4"
            :class="[
                'focus-ring min-h-24 rounded-md border bg-white px-3 py-2 text-[14px] text-zinc-950 shadow-[inset_0_1px_1px_rgba(15,23,42,0.03)] placeholder:text-zinc-400',
                error ? 'border-red-300' : 'border-zinc-300/80',
                disabled ? 'cursor-not-allowed opacity-60' : '',
            ]"
            :value="modelValue"
            :placeholder="placeholder"
            :disabled="disabled"
            @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
        />
        <span v-if="error" class="text-xs font-medium text-red-600">{{ error }}</span>
        <span v-else-if="help" class="text-xs font-normal text-zinc-500">{{ help }}</span>
    </label>
</template>
