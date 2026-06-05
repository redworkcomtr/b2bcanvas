<script setup lang="ts">
import { MailCheck } from 'lucide-vue-next';
import { ref } from 'vue';
import { RouterLink } from 'vue-router';

import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import Input from '@/components/ui/Input.vue';
import { ApiError, usePortalStore } from '@/stores/portal';

const store = usePortalStore();
const email = ref('selin@example.test');
const loading = ref(false);
const message = ref('');
const error = ref('');

async function submit() {
    loading.value = true;
    message.value = '';
    error.value = '';

    try {
        const response = await store.forgotPassword(email.value);
        message.value = response.message;
    } catch (exception) {
        error.value = exception instanceof ApiError ? exception.message : 'Could not start reset flow.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <main class="grid min-h-screen place-items-center bg-[#f9f9f9] p-4">
        <Card class="w-full max-w-md">
            <div class="mb-6">
                <div class="mb-4 grid h-12 w-12 place-items-center rounded-xl bg-white text-[#18181b]">
                    <MailCheck class="h-6 w-6" />
                </div>
                <h2 class="text-2xl font-bold text-[#18181b]">Reset password</h2>
                <p class="mt-1 text-sm text-[#71717a]">Send reset instructions to a workspace user.</p>
            </div>

            <form class="grid gap-4" @submit.prevent="submit">
                <Input v-model="email" label="Email" type="email" required placeholder="name@company.com" />
                <p v-if="message" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700">{{ message }}</p>
                <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700">{{ error }}</p>
                <Button type="submit" :disabled="loading">{{ loading ? 'Sending...' : 'Send reset instructions' }}</Button>
                <RouterLink class="text-center text-sm font-semibold text-[#18181b] hover:text-black" to="/login">Back to sign in</RouterLink>
            </form>
        </Card>
    </main>
</template>
