<script setup lang="ts">
defineProps<{
    label?: string;
    modelValue?: string;
    placeholder?: string;
    required?: boolean;
    help?: string;
    error?: string;
    rows?: number;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <label class="grid gap-1.5 text-sm font-medium text-slate-700">
        <span v-if="label">{{ label }} <span v-if="required" class="text-red-600">*</span></span>
        <textarea
            :rows="rows ?? 4"
            :class="[
                'focus-ring min-h-24 rounded-md border bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400',
                error ? 'border-red-300' : 'border-slate-300',
            ]"
            :value="modelValue"
            :placeholder="placeholder"
            @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
        />
        <span v-if="error" class="text-xs font-medium text-red-600">{{ error }}</span>
        <span v-else-if="help" class="text-xs font-normal text-slate-500">{{ help }}</span>
    </label>
</template>
