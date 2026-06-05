<script setup lang="ts">
import { LockKeyhole, Mail, ShieldCheck } from 'lucide-vue-next';
import { reactive, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';

import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import Input from '@/components/ui/Input.vue';
import { ApiError, usePortalStore } from '@/stores/portal';

const store = usePortalStore();
const router = useRouter();
const route = useRoute();
const loading = ref(false);
const error = ref('');
const form = reactive({
    email: 'selin@example.test',
    password: 'password',
    remember: true,
});

async function submit() {
    loading.value = true;
    error.value = '';

    try {
        await store.login(form);
        await router.push(String(route.query.redirect ?? '/'));
    } catch (exception) {
        error.value = exception instanceof ApiError
            ? exception.message
            : 'Could not sign in. Please try again.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <main class="grid min-h-screen bg-[#f9f9f9] p-4 lg:grid-cols-[minmax(0,1fr)_480px] lg:p-4">
        <section class="hidden min-h-[calc(100vh-2rem)] place-items-center rounded-[2rem] bg-white p-12 lg:grid">
            <div class="w-full max-w-lg">
                <div class="mb-8 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-black text-lg font-black text-white">BC</div>
                <p class="text-sm font-semibold uppercase text-[#71717a]">B2B Canvas</p>
                <h1 class="mt-3 max-w-md text-3xl font-bold leading-tight text-[#18181b]">Fulfillment operations portal</h1>
                <div class="mt-8 grid max-w-sm gap-3 text-sm text-[#4c4546]">
                    <div class="flex items-center gap-3 rounded-2xl bg-[#f5f5f5] px-3 py-2"><ShieldCheck class="h-5 w-5 text-[#18181b]" /> Orders</div>
                    <div class="flex items-center gap-3 rounded-2xl bg-[#f5f5f5] px-3 py-2"><ShieldCheck class="h-5 w-5 text-[#18181b]" /> Product mappings</div>
                    <div class="flex items-center gap-3 rounded-2xl bg-[#f5f5f5] px-3 py-2"><ShieldCheck class="h-5 w-5 text-[#18181b]" /> Tickets and claims</div>
                </div>
            </div>
        </section>

        <section class="flex min-h-[calc(100vh-2rem)] items-center justify-center p-0 sm:p-8">
            <Card class="w-full max-w-md">
                <div class="mb-6">
                    <div class="mb-4 grid h-12 w-12 place-items-center rounded-xl bg-black text-sm font-bold text-white lg:hidden">BC</div>
                    <h2 class="text-2xl font-bold text-[#18181b]">Sign in</h2>
                    <p class="mt-1 text-sm text-[#71717a]">Access your tenant fulfillment workspace.</p>
                </div>

                <form class="grid gap-4" @submit.prevent="submit">
                    <Input v-model="form.email" label="Email" type="email" required placeholder="selin@example.test">
                        <template #prefix><Mail class="h-4 w-4" /></template>
                    </Input>
                    <Input v-model="form.password" label="Password" type="password" required placeholder="password">
                        <template #prefix><LockKeyhole class="h-4 w-4" /></template>
                    </Input>

                    <div class="flex items-center justify-between gap-3 text-sm">
                        <label class="inline-flex items-center gap-2 font-medium text-[#4c4546]">
                            <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-slate-300">
                            Remember me
                        </label>
                        <RouterLink class="font-semibold text-[#18181b] hover:text-black" to="/forgot-password">Forgot password?</RouterLink>
                    </div>

                    <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700">{{ error }}</p>

                    <Button type="submit" :disabled="loading">
                        {{ loading ? 'Signing in...' : 'Sign in' }}
                    </Button>
                </form>
            </Card>
        </section>
    </main>
</template>
