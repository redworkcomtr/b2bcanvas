<script setup lang="ts">
import { useSlots } from 'vue';

defineProps<{
    label?: string;
    modelValue?: string | number;
    type?: string;
    placeholder?: string;
    required?: boolean;
    help?: string;
    error?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const slots = useSlots();
</script>

<template>
    <label class="grid min-w-0 gap-1.5 text-sm font-medium text-slate-700">
        <span v-if="label">{{ label }} <span v-if="required" class="text-red-600">*</span></span>
        <div class="relative min-w-0">
            <span v-if="slots.prefix" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                <slot name="prefix" />
            </span>
            <input
                :class="[
                    'focus-ring h-10 w-full rounded-md border bg-white px-3 text-sm text-slate-900 shadow-sm placeholder:text-slate-400',
                    slots.prefix ? 'pl-9' : '',
                    error ? 'border-red-300' : 'border-slate-300',
                ]"
                :type="type ?? 'text'"
                :value="modelValue"
                :placeholder="placeholder"
                @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
            >
        </div>
        <span v-if="error" class="text-xs font-medium text-red-600">{{ error }}</span>
        <span v-else-if="help" class="text-xs font-normal text-slate-500">{{ help }}</span>
    </label>
</template>
