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
    <label class="grid gap-1.5 text-sm font-medium text-slate-700">
        <span v-if="label">{{ label }} <span v-if="required" class="text-red-600">*</span></span>
        <select
            :class="[
                'focus-ring h-10 rounded-md border bg-white px-3 text-sm text-slate-900 shadow-sm',
                error ? 'border-red-300' : 'border-slate-300',
            ]"
            :value="modelValue ?? ''"
            @change="emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
        >
            <slot />
        </select>
        <span v-if="error" class="text-xs font-medium text-red-600">{{ error }}</span>
        <span v-else-if="help" class="text-xs font-normal text-slate-500">{{ help }}</span>
    </label>
</template>
