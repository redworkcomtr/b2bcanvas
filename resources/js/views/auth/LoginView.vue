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
    <main class="grid min-h-screen bg-[hsl(var(--background))] lg:grid-cols-[1fr_520px]">
        <section class="surface-grid hidden min-h-screen place-items-center bg-slate-950 p-12 text-white lg:grid">
            <div class="max-w-xl">
                <div class="mb-8 inline-flex h-14 w-14 items-center justify-center rounded-lg bg-white text-xl font-black text-slate-950">BC</div>
                <p class="text-sm font-bold uppercase tracking-wide text-teal-200">B2B Canvas Operations</p>
                <h1 class="mt-4 text-4xl font-bold tracking-tight">Secure fulfillment control for tenant teams.</h1>
                <p class="mt-5 text-lg leading-8 text-slate-300">
                    Session auth, tenant isolation, role-gated operations, audit-friendly actions, and a keyboard-first SaaS workspace.
                </p>
                <div class="mt-8 grid gap-3 text-sm text-slate-200">
                    <div class="flex items-center gap-3"><ShieldCheck class="h-5 w-5 text-teal-300" /> Owner, admin, operations, support, and viewer roles</div>
                    <div class="flex items-center gap-3"><ShieldCheck class="h-5 w-5 text-teal-300" /> Tenant-scoped API access by default</div>
                    <div class="flex items-center gap-3"><ShieldCheck class="h-5 w-5 text-teal-300" /> User invitations and active/passive controls</div>
                </div>
            </div>
        </section>

        <section class="flex min-h-screen items-center justify-center p-4 sm:p-8">
            <Card class="w-full max-w-md">
                <div class="mb-6">
                    <div class="mb-4 grid h-12 w-12 place-items-center rounded-md bg-slate-950 text-sm font-bold text-white lg:hidden">BC</div>
                    <h2 class="text-2xl font-bold text-slate-950">Sign in</h2>
                    <p class="mt-1 text-sm text-slate-500">Access your tenant fulfillment workspace.</p>
                </div>

                <form class="grid gap-4" @submit.prevent="submit">
                    <Input v-model="form.email" label="Email" type="email" required placeholder="selin@example.test">
                        <template #prefix><Mail class="h-4 w-4" /></template>
                    </Input>
                    <Input v-model="form.password" label="Password" type="password" required placeholder="password">
                        <template #prefix><LockKeyhole class="h-4 w-4" /></template>
                    </Input>

                    <div class="flex items-center justify-between gap-3 text-sm">
                        <label class="inline-flex items-center gap-2 font-medium text-slate-700">
                            <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-slate-300">
                            Remember me
                        </label>
                        <RouterLink class="font-semibold text-teal-700 hover:text-teal-900" to="/forgot-password">Forgot password?</RouterLink>
                    </div>

                    <p v-if="error" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700">{{ error }}</p>

                    <Button type="submit" :disabled="loading">
                        {{ loading ? 'Signing in...' : 'Sign in' }}
                    </Button>
                </form>
            </Card>
        </section>
    </main>
</template>
