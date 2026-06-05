<script setup lang="ts">
import { CircleAlert, CircleCheck, CreditCard, Check, ImageUp, Layers3, PackagePlus, Plus, Save, Trash2, Truck } from 'lucide-vue-next';
import { loadStripe, type Stripe, type StripeCardElement, type StripeElements } from '@stripe/stripe-js';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';

import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FileDropzone from '@/components/ui/FileDropzone.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import Textarea from '@/components/ui/Textarea.vue';
import { money } from '@/lib/utils';
import { ApiError, usePortalStore } from '@/stores/portal';
import type { MediaFile, Order, ProductOption, ProductVariant } from '@/types/portal';

type WizardItem = {
    id: string;
    product_type_id: string;
    product_variant_id: string;
    quantity: number;
    client_sku: string;
    print_option: string;
    hanging_option: string;
    extras: string;
    artwork: MediaFile | null;
};

const draftKey = 'b2bcanvas:new-order-wizard';
const store = usePortalStore();
const router = useRouter();

const step = ref(1);
const activeIndex = ref(0);
const uploading = ref(false);
const submitting = ref(false);
const statusMessage = ref('');
const errorMessage = ref('');
const draftLoaded = ref(false);
const autosaveDraft = ref(true);
const lastSavedAt = ref('');
const touchedSteps = ref<number[]>([]);
const items = ref<WizardItem[]>([blankItem()]);
const shipping = reactive(defaultShipping());
const notes = ref('');

function defaultShipping() {
    return {
        customer_name: '',
        line1: '',
        line2: '',
        city: '',
        state: '',
        postal_code: '',
        country: 'US',
        shipping_service: 'Standard Ground',
    };
}

function resetShipping() {
    Object.assign(shipping, defaultShipping());
}

const paymentOrder = ref<Order | null>(null);
const paymentIntentId = ref('');
const paymentClientSecret = ref('');
const paymentSubmitting = ref(false);
const paymentMessage = ref('');
const paymentError = ref('');
const stripeClient = ref<Stripe | null>(null);
const stripeElements = ref<StripeElements | null>(null);
const cardElement = ref<StripeCardElement | null>(null);
const stripeCardMount = ref<HTMLElement | null>(null);

const baseSteps = ['Products', 'Configure', 'Artwork', 'Shipping', 'Summary'];
const subtotalCents = computed(() => items.value.reduce((total, item) => total + itemSubtotal(item), 0));
const requiresPayment = computed(() => subtotalCents.value > 0);
const steps = computed(() => requiresPayment.value ? [...baseSteps, 'Payment'] : baseSteps);
const activeItem = computed(() => items.value[activeIndex.value] ?? items.value[0]);
const activeVariant = computed(() => variantFor(activeItem.value));
const allConfigured = computed(() => items.value.every((item) => item.product_variant_id && item.quantity > 0));
const allOptionsReady = computed(() => items.value.every((item) => item.print_option));
const allArtworkReady = computed(() => items.value.every((item) => item.artwork));
const shippingReady = computed(() => Boolean(shipping.customer_name && shipping.line1 && shipping.city && shipping.postal_code && shipping.country));
const isPaymentStep = computed(() => requiresPayment.value && step.value === steps.value.length);
const firstInvalidStep = computed(() => {
    if (!allConfigured.value) {
        return 1;
    }
    if (!allOptionsReady.value) {
        return 2;
    }
    if (!allArtworkReady.value) {
        return 3;
    }
    if (!shippingReady.value) {
        return 4;
    }

    return 0;
});
const currentStepFeedback = computed(() => {
    if (isPaymentStep.value) {
        return paymentOrder.value
            ? 'Card payment is ready for this submitted order.'
            : 'Submit the summary first to create a secure payment request.';
    }

    if (step.value === 5) {
        if (firstInvalidStep.value) {
            return `Resolve ${steps.value[firstInvalidStep.value - 1].toLowerCase()} before creating the order. ${stepFeedbackFor(firstInvalidStep.value)}`;
        }

        return requiresPayment.value
            ? 'Review the order and create the payment request when everything looks right.'
            : 'Review the order total, shipping, and artwork before submitting.';
    }

    return stepFeedbackFor(step.value);
});
const currentStepFeedbackTone = computed<'info' | 'warning'>(() => {
    if (!currentStepFeedback.value) {
        return 'info';
    }

    if (touchedSteps.value.includes(step.value) && firstInvalidStep.value !== 0 && firstInvalidStep.value <= step.value) {
        return 'warning';
    }

    return 'info';
});

function blankItem(): WizardItem {
    return {
        id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
        product_type_id: '',
        product_variant_id: '',
        quantity: 1,
        client_sku: '',
        print_option: '',
        hanging_option: '',
        extras: '',
        artwork: null,
    };
}

function stripePublishableKey(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="stripe-publishable-key"]')?.content ?? '';
}

function variantFor(item: WizardItem): ProductVariant | undefined {
    return store.variants.find((variant) => String(variant.id) === item.product_variant_id);
}

function optionsFor(item: WizardItem, group?: string): ProductOption[] {
    const type = store.productTypes.find((productType) => String(productType.id) === item.product_type_id);
    const options = type?.options ?? [];
    return group ? options.filter((option) => option.group === group) : options;
}

function optionPrice(item: WizardItem) {
    const names = [item.print_option, item.hanging_option].filter(Boolean);
    return optionsFor(item)
        .filter((option) => names.includes(option.name))
        .reduce((total, option) => total + option.price_cents, 0);
}

function itemSubtotal(item: WizardItem) {
    const variant = variantFor(item);
    if (!variant) {
        return 0;
    }

    return (variant.price_cents + optionPrice(item)) * Number(item.quantity || 1);
}

function missingProductIndex() {
    return items.value.findIndex((item) => !item.product_variant_id || Number(item.quantity || 0) <= 0);
}

function missingOptionsIndex() {
    return items.value.findIndex((item) => !item.print_option);
}

function missingArtworkIndex() {
    return items.value.findIndex((item) => !item.artwork);
}

function missingShippingFields() {
    const fields: string[] = [];

    if (!shipping.customer_name) {
        fields.push('customer name');
    }
    if (!shipping.line1) {
        fields.push('address line 1');
    }
    if (!shipping.city) {
        fields.push('city');
    }
    if (!shipping.postal_code) {
        fields.push('postal code');
    }
    if (!shipping.country) {
        fields.push('country');
    }

    return fields;
}

function stepFeedbackFor(stepNumber: number) {
    if (stepNumber === 1) {
        const index = missingProductIndex();

        return index === -1
            ? 'Products are ready.'
            : `Item ${index + 1} needs a production product and a quantity above zero.`;
    }

    if (stepNumber === 2) {
        const index = missingOptionsIndex();

        return index === -1
            ? 'Production options are ready.'
            : `Item ${index + 1} needs a print option before artwork upload.`;
    }

    if (stepNumber === 3) {
        const index = missingArtworkIndex();

        return index === -1
            ? 'Artwork is ready for every item.'
            : `Item ${index + 1} still needs an uploaded artwork file.`;
    }

    if (stepNumber === 4) {
        const fields = missingShippingFields();

        return fields.length
            ? `Shipping is missing ${fields.join(', ')}.`
            : 'Shipping is ready.';
    }

    return '';
}

function markStepTouched(stepNumber = step.value) {
    if (!touchedSteps.value.includes(stepNumber)) {
        touchedSteps.value = [...touchedSteps.value, stepNumber];
    }
}

function firstInvalidStepBefore(targetStep: number) {
    if (targetStep > 1 && !allConfigured.value) {
        return 1;
    }
    if (targetStep > 2 && !allOptionsReady.value) {
        return 2;
    }
    if (targetStep > 3 && !allArtworkReady.value) {
        return 3;
    }
    if (targetStep > 4 && !shippingReady.value) {
        return 4;
    }

    return 0;
}

function stepIsComplete(stepNumber: number) {
    if (requiresPayment.value && stepNumber === steps.value.length) {
        return Boolean(paymentOrder.value && paymentClientSecret.value);
    }

    if (stepNumber === 1) {
        return allConfigured.value;
    }
    if (stepNumber === 2) {
        return allConfigured.value && allOptionsReady.value;
    }
    if (stepNumber === 3) {
        return allConfigured.value && allOptionsReady.value && allArtworkReady.value;
    }
    if (stepNumber === 4) {
        return firstInvalidStep.value === 0;
    }
    if (stepNumber === 5) {
        return firstInvalidStep.value === 0;
    }

    return false;
}

function stepStatus(stepNumber: number): 'complete' | 'warning' | 'idle' {
    if (stepIsComplete(stepNumber)) {
        return 'complete';
    }

    const invalidStep = firstInvalidStep.value;
    const shouldWarn = touchedSteps.value.includes(stepNumber) || step.value === stepNumber || step.value > stepNumber;

    if (shouldWarn && invalidStep !== 0 && invalidStep <= stepNumber) {
        return 'warning';
    }

    return 'idle';
}

function goToStep(targetStep: number) {
    const boundedStep = Math.min(Math.max(targetStep, 1), steps.value.length);

    markStepTouched();

    if (boundedStep > step.value) {
        const invalidStep = firstInvalidStepBefore(boundedStep);

        if (invalidStep) {
            markStepTouched(invalidStep);
            activeIndex.value = Math.max(0, [
                missingProductIndex(),
                missingOptionsIndex(),
                missingArtworkIndex(),
            ].find((index) => index >= 0) ?? activeIndex.value);
            step.value = invalidStep;
            errorMessage.value = '';
            return;
        }
    }

    if (requiresPayment.value && boundedStep === steps.value.length && !paymentOrder.value) {
        step.value = 5;
        errorMessage.value = '';
        return;
    }

    step.value = boundedStep;
    errorMessage.value = '';
}

function addItem() {
    items.value.push(blankItem());
    activeIndex.value = items.value.length - 1;
}

function removeItem(index: number) {
    items.value.splice(index, 1);
    if (items.value.length === 0) {
        items.value.push(blankItem());
    }
    activeIndex.value = Math.min(activeIndex.value, items.value.length - 1);
}

function resetVariant(item: WizardItem) {
    item.product_variant_id = '';
    item.print_option = '';
    item.hanging_option = '';
    item.artwork = null;
}

async function uploadArtwork(file: File) {
    if (!activeItem.value) {
        return;
    }

    uploading.value = true;
    errorMessage.value = '';
    try {
        activeItem.value.artwork = await store.uploadFile(file, 'artwork');
    } catch {
        errorMessage.value = 'Artwork could not be uploaded.';
    } finally {
        uploading.value = false;
    }
}

function clearPaymentState() {
    paymentMessage.value = '';
    paymentError.value = '';
    paymentIntentId.value = '';
    paymentClientSecret.value = '';
    paymentSubmitting.value = false;
}

async function detachCard() {
    if (cardElement.value) {
        cardElement.value.unmount();
    }
    cardElement.value = null;
    stripeElements.value = null;
}

async function setupCard(intentClientSecret: string) {
    if (!stripeCardMount.value) {
        throw new Error('Card container is not ready.');
    }

    await detachCard();

    if (!stripeClient.value) {
        const key = stripePublishableKey();
        if (!key) {
            throw new Error('Stripe publishable key is missing. Add STRIPE_PUBLISHABLE_KEY to the environment.');
        }

        stripeClient.value = await loadStripe(key);
        if (!stripeClient.value) {
            throw new Error('Stripe SDK could not be initialized.');
        }
    }

    stripeElements.value = stripeClient.value.elements({
        clientSecret: intentClientSecret,
    });
    cardElement.value = stripeElements.value.create('card', {
        style: {
            base: {
                color: '#0f172a',
                '::placeholder': {
                    color: '#64748b',
                },
            },
        },
    }) as StripeCardElement;

    cardElement.value.mount(stripeCardMount.value);
}

async function createPaymentIntentForOrder(order: Order): Promise<void> {
    try {
        clearPaymentState();
        const result = await store.createPaymentIntent(order, { force_new_intent: true });
        paymentOrder.value = result.order;
        paymentIntentId.value = result.payment.provider_payment_intent_id;
        paymentClientSecret.value = result.client_secret ?? '';

        if (!paymentClientSecret.value) {
            throw new Error('Stripe did not return a client secret for the payment intent.');
        }

        await nextTick();
        await setupCard(paymentClientSecret.value);
        paymentMessage.value = 'Card is ready. Complete payment to submit the order.';
    } catch (exception) {
        paymentError.value = exception instanceof Error
            ? exception.message
            : 'Card payment could not be initialized.';
    }
}

async function submitPayment() {
    if (!paymentOrder.value || !paymentIntentId.value || !paymentClientSecret.value || !cardElement.value || !stripeClient.value) {
        paymentError.value = 'Payment is not ready yet. Please retry on this step.';
        return;
    }

    paymentSubmitting.value = true;
    paymentError.value = '';

    try {
        const stripeResult = await stripeClient.value.confirmCardPayment(paymentClientSecret.value, {
            payment_method: {
                card: cardElement.value,
            },
        });

        if (stripeResult.error) {
            paymentError.value = stripeResult.error.message ?? 'Card payment could not be processed.';
            if (stripeResult.paymentIntent?.id) {
                paymentIntentId.value = stripeResult.paymentIntent.id;
            }
        }

        const synced = await store.confirmPayment(paymentOrder.value, {
            payment_intent_id: paymentIntentId.value,
        });

        if (synced.result.order_status === 'submitted') {
            removeLocalDraft();
            paymentMessage.value = 'Payment successful. Order has been submitted.';
            await router.push(`/orders/${synced.order.uuid}`);
            return;
        }

        if (synced.result.payment_status === 'failed') {
            paymentError.value = `Ödeme başarısız: ${synced.result.payment_intent_status}`;
        } else if (synced.result.requires_action) {
            paymentError.value = 'Payment needs additional card verification.';
        } else {
            paymentError.value = `Ödeme durumu: ${synced.result.payment_status}. İlerleme için kart bilgilerini kontrol edin.`;
        }

        paymentOrder.value = synced.order;
    } catch (exception) {
        if (exception instanceof ApiError && Object.keys(exception.errors).length) {
            paymentError.value = `${exception.message}: ${Object.values(exception.errors).flat().join(' | ')}`;
            return;
        }

        if (exception instanceof ApiError) {
            paymentError.value = exception.message;
            return;
        }

        paymentError.value = 'Card payment could not be processed right now.';
    } finally {
        paymentSubmitting.value = false;
    }
}

function formatSavedTime(date: Date) {
    return new Intl.DateTimeFormat(undefined, {
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function persistLocalDraft(message?: string) {
    const savedAt = new Date();

    localStorage.setItem(draftKey, JSON.stringify({
        items: items.value,
        shipping: { ...shipping },
        notes: notes.value,
        step: step.value,
        activeIndex: activeIndex.value,
        savedAt: savedAt.toISOString(),
    }));
    lastSavedAt.value = formatSavedTime(savedAt);

    if (message !== undefined) {
        statusMessage.value = message;
    }
}

function saveLocalDraft(message = 'Draft saved locally.') {
    persistLocalDraft(message);
}

function removeLocalDraft() {
    autosaveDraft.value = false;
    localStorage.removeItem(draftKey);
}

async function startNewOrder() {
    autosaveDraft.value = false;
    localStorage.removeItem(draftKey);

    await detachCard();
    items.value = [blankItem()];
    resetShipping();
    notes.value = '';
    step.value = 1;
    activeIndex.value = 0;
    touchedSteps.value = [];
    lastSavedAt.value = '';
    paymentOrder.value = null;
    clearPaymentState();
    errorMessage.value = '';
    statusMessage.value = 'Local draft cleared. A blank order is ready.';

    await nextTick();
    autosaveDraft.value = true;
}

function loadLocalDraft() {
    const raw = localStorage.getItem(draftKey);
    if (!raw) {
        return;
    }

    try {
        const draft = JSON.parse(raw) as {
            items?: WizardItem[];
            shipping?: Partial<typeof shipping>;
            notes?: string;
            step?: number;
            activeIndex?: number;
            savedAt?: string;
        };
        if (draft.items?.length) {
            items.value = draft.items;
        }
        Object.assign(shipping, draft.shipping ?? {});
        notes.value = draft.notes ?? '';
        step.value = Math.min(Math.max(Number(draft.step ?? 1), 1), steps.value.length);
        if (requiresPayment.value && step.value === steps.value.length) {
            step.value = 5;
        }
        activeIndex.value = Math.min(Math.max(Number(draft.activeIndex ?? 0), 0), items.value.length - 1);

        if (draft.savedAt) {
            const savedAt = new Date(draft.savedAt);

            if (!Number.isNaN(savedAt.getTime())) {
                lastSavedAt.value = formatSavedTime(savedAt);
            }
        }

        statusMessage.value = 'Draft restored from this browser.';
    } catch {
        localStorage.removeItem(draftKey);
    }
}

function validateBeforeSubmit() {
    if (firstInvalidStep.value) {
        markStepTouched(firstInvalidStep.value);
        errorMessage.value = stepFeedbackFor(firstInvalidStep.value);
        step.value = firstInvalidStep.value;
        return false;
    }

    return true;
}

async function submit(status: 'verified' | 'draft' = 'verified') {
    if (status === 'verified' && !validateBeforeSubmit()) {
        return;
    }

    submitting.value = true;
    errorMessage.value = '';
    statusMessage.value = '';

    try {
        const order = await store.createOrder({
            order_number: `WEB-${Date.now()}`,
            status,
            customer_name: shipping.customer_name,
            shipping_service: shipping.shipping_service,
            shipping_address: {
                line1: shipping.line1,
                line2: shipping.line2,
                city: shipping.city,
                state: shipping.state,
                postal_code: shipping.postal_code,
                country: shipping.country,
            },
            notes: notes.value,
            items: items.value.map((item) => {
                const variant = variantFor(item);
                return {
                    product_variant_id: Number(item.product_variant_id),
                    item_name: variant?.name,
                    item_sku: item.client_sku || variant?.sku,
                    quantity: Number(item.quantity || 1),
                    artwork_media_file_id: item.artwork?.id,
                    options: {
                        print: item.print_option,
                        hanging: item.hanging_option,
                        extras: item.extras,
                    },
                };
            }),
        });

        if (status === 'draft') {
            removeLocalDraft();
            await router.push(`/orders/${order.uuid}`);
            return;
        }

        if (requiresPayment.value) {
            removeLocalDraft();
            paymentOrder.value = order;
            step.value = steps.value.length;
            await createPaymentIntentForOrder(order);
            return;
        }

        removeLocalDraft();
        await router.push(`/orders/${order.uuid}`);
    } catch (exception) {
        errorMessage.value = exception instanceof ApiError
            ? exception.message
            : 'Order could not be submitted.';
    } finally {
        submitting.value = false;
    }
}

watch([items, shipping, notes, step, activeIndex], () => {
    if (!draftLoaded.value || !autosaveDraft.value) {
        return;
    }

    persistLocalDraft();
}, { deep: true });

watch(step, (value) => {
    if (value !== steps.value.length) {
        return;
    }

    if (!isPaymentStep.value || !paymentOrder.value) {
        return;
    }

    void createPaymentIntentForOrder(paymentOrder.value);
}, { immediate: true });

watch([subtotalCents], () => {
    const maxStep = steps.value.length;
    if (step.value > maxStep) {
        step.value = maxStep;
    }
});

onMounted(() => {
    loadLocalDraft();
    draftLoaded.value = true;
});

onBeforeUnmount(() => {
    void detachCard();
});
</script>

<template>
    <div class="app-page">
        <div class="flex flex-col justify-between gap-4 xl:flex-row xl:items-end">
            <div class="page-heading">
                <h2>Submit New Order</h2>
                <p>Build a multi-item print order with artwork, production options, pricing, and shipping gates.</p>
            </div>
            <div class="flex flex-col items-start gap-2 xl:items-end">
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" @click="saveLocalDraft()"><Save class="h-4 w-4" /> Save draft</Button>
                    <Button variant="ghost" size="sm" @click="startNewOrder"><Plus class="h-4 w-4" /> New blank order</Button>
                </div>
                <p v-if="lastSavedAt" class="text-xs font-medium text-[#71717a]">Last autosaved {{ lastSavedAt }}</p>
            </div>
        </div>

        <Alert v-if="statusMessage" tone="success" title="Wizard state saved" :description="statusMessage" />
        <Alert v-if="errorMessage" tone="danger" title="Order wizard warning" :description="errorMessage" />

        <div class="grid gap-5 2xl:grid-cols-[1fr_380px]">
            <Card>
                <div :class="['mb-4 grid gap-2', steps.length === 6 ? 'md:grid-cols-3 xl:grid-cols-6' : 'md:grid-cols-5']">
                    <button
                        v-for="(label, index) in steps"
                        :key="label"
                        :class="[
                            'min-h-[44px] rounded-lg border px-3 py-2 text-left text-sm font-medium transition-colors',
                            step === index + 1
                                ? 'border-[#18181b]/10 bg-zinc-200/60 text-[#18181b]'
                                : stepStatus(index + 1) === 'warning'
                                    ? 'border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100/80'
                                    : stepStatus(index + 1) === 'complete'
                                        ? 'border-emerald-200 bg-emerald-50/80 text-emerald-900 hover:bg-emerald-50'
                                        : 'border-transparent bg-white text-[#71717a] hover:bg-zinc-200/50'
                        ]"
                        @click="goToStep(index + 1)"
                    >
                        <span class="flex items-center justify-between gap-2">
                            <span>{{ label }}</span>
                            <CircleCheck v-if="stepStatus(index + 1) === 'complete'" class="h-4 w-4 shrink-0 text-emerald-700" />
                            <CircleAlert v-else-if="stepStatus(index + 1) === 'warning'" class="h-4 w-4 shrink-0 text-amber-700" />
                        </span>
                    </button>
                </div>

                <Alert v-if="currentStepFeedback" class="mb-5" :tone="currentStepFeedbackTone" title="Step check" :description="currentStepFeedback" />

                <div v-if="step === 1" class="grid gap-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="panel-title">Products</h3>
                            <p class="panel-caption">Add every production line item before configuring options and artwork.</p>
                        </div>
                        <Button variant="outline" @click="addItem"><Plus class="h-4 w-4" /> Item</Button>
                    </div>

                    <div class="grid gap-3 lg:grid-cols-[240px_1fr]">
                        <div class="grid gap-2">
                            <div
                                v-for="(item, index) in items"
                                :key="item.id"
                                class="grid grid-cols-[1fr_auto] gap-2"
                            >
                                <button
                                    :class="['rounded-2xl p-3 text-left text-sm transition-colors', activeIndex === index ? 'bg-zinc-200/60' : 'bg-white hover:bg-zinc-200/50']"
                                    @click="activeIndex = index"
                                >
                                    <p class="font-semibold text-[#18181b]">Item {{ index + 1 }}</p>
                                    <p class="mt-1 text-[#71717a]">{{ variantFor(item)?.sku ?? 'No product selected' }}</p>
                                    <p class="mt-1 text-xs text-[#71717a]">{{ money(itemSubtotal(item)) }}</p>
                                </button>
                                <Button
                                    v-if="items.length > 1"
                                    variant="ghost"
                                    size="icon"
                                    class="h-full min-h-[44px] text-red-700 hover:text-red-800"
                                    :aria-label="`Remove item ${index + 1}`"
                                    :title="`Remove item ${index + 1}`"
                                    @click="removeItem(index)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>

                        <div class="grid gap-4 rounded-lg border border-zinc-200 bg-white p-4">
                            <Select v-model="activeItem.product_type_id" label="Product type" @update:model-value="resetVariant(activeItem)">
                                <option value="">Choose product type</option>
                                <option v-for="type in store.productTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                            </Select>
                            <Select v-model="activeItem.product_variant_id" label="Production product">
                                <option value="">Choose product variant</option>
                                <option v-for="variant in store.variants.filter((item) => String(item.product_type_id) === activeItem.product_type_id)" :key="variant.id" :value="variant.id">
                                    {{ variant.name }} | {{ variant.sku }} | {{ money(variant.price_cents) }}
                                </option>
                            </Select>

                            <div v-if="activeVariant" class="rounded-lg border border-zinc-200 bg-zinc-50 p-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h4 class="font-semibold text-[#18181b]">{{ activeVariant.name }}</h4>
                                        <p class="text-sm text-[#71717a]">{{ activeVariant.product_type?.description }}</p>
                                    </div>
                                    <Badge tone="info">{{ money(activeVariant.price_cents) }}</Badge>
                                </div>
                                <div class="mt-3 grid gap-2 text-sm text-[#4c4546] md:grid-cols-3">
                                    <span><strong>Panels:</strong> {{ activeVariant.panel_count }}</span>
                                    <span><strong>Layout:</strong> {{ activeVariant.layout }}</span>
                                    <span><strong>Template:</strong> {{ activeVariant.template_url ?? '-' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <Button @click="goToStep(2)">Next</Button>
                    </div>
                </div>

                <div v-else-if="step === 2" class="grid gap-5">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="panel-title">Configure Item {{ activeIndex + 1 }}</h3>
                        <Button v-if="items.length > 1" variant="ghost" @click="removeItem(activeIndex)"><Trash2 class="h-4 w-4" /> Remove</Button>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <Input v-model="activeItem.quantity" label="Quantity" type="number" />
                        <Input v-model="activeItem.client_sku" label="Client SKU" placeholder="Marketplace SKU or buyer SKU" />
                    </div>
                    <Select v-model="activeItem.print_option" label="Print option" required>
                        <option value="">Select print handling</option>
                        <option v-for="option in optionsFor(activeItem, 'Print Options')" :key="option.id" :value="option.name">{{ option.name }} · {{ money(option.price_cents) }}</option>
                    </Select>
                    <Select v-model="activeItem.hanging_option" label="Hanging option">
                        <option value="">No hanging option</option>
                        <option v-for="option in optionsFor(activeItem, 'Hanging Options')" :key="option.id" :value="option.name">{{ option.name }} · {{ money(option.price_cents) }}</option>
                    </Select>
                    <Textarea v-model="activeItem.extras" label="Extras / production note" rows="3" placeholder="Frame handling, panel note, personalization, or packaging instruction..." />
                    <div class="flex justify-between">
                        <Button variant="outline" @click="goToStep(1)">Back</Button>
                        <Button @click="goToStep(3)">Next</Button>
                    </div>
                </div>

                <div v-else-if="step === 3" class="grid gap-5">
                    <div class="flex items-center gap-2">
                        <ImageUp class="h-5 w-5 text-[#18181b]" />
                        <h3 class="panel-title">Artwork Upload</h3>
                    </div>
                    <div class="grid gap-4 md:grid-cols-[1fr_260px]">
                        <FileDropzone
                            label="Upload artwork or print-ready panel"
                            description="Files are stored immediately and attached to the order when submitted."
                            accept="image/*,.pdf"
                            @selected="uploadArtwork"
                        />
                        <div class="rounded-2xl bg-white p-4">
                            <p class="text-sm font-semibold text-[#18181b]">Item {{ activeIndex + 1 }}</p>
                            <p class="mt-1 text-sm text-[#71717a]">{{ variantFor(activeItem)?.name ?? 'No product selected' }}</p>
                            <Badge v-if="activeItem.artwork" class="mt-3" tone="success">{{ activeItem.artwork.original_name }}</Badge>
                            <Badge v-else class="mt-3" tone="warning">{{ uploading ? 'Uploading...' : 'Artwork required' }}</Badge>
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <button
                            v-for="(item, index) in items"
                            :key="item.id"
                            :class="['rounded-2xl px-3 py-2 text-left text-sm transition-colors', activeIndex === index ? 'bg-zinc-200/60 text-[#18181b]' : 'bg-white text-[#4c4546] hover:bg-zinc-200/50']"
                            @click="activeIndex = index"
                        >
                            Item {{ index + 1 }} · {{ variantFor(item)?.sku ?? 'No SKU' }} · {{ item.artwork?.original_name ?? 'No artwork' }}
                        </button>
                    </div>
                    <div class="flex justify-between">
                        <Button variant="outline" @click="goToStep(2)">Back</Button>
                        <Button @click="goToStep(4)">Next</Button>
                    </div>
                </div>

                <div v-else-if="step === 4" class="grid gap-5">
                    <div class="flex items-center gap-2">
                        <Truck class="h-5 w-5 text-[#18181b]" />
                        <h3 class="panel-title">Shipping</h3>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <Input v-model="shipping.customer_name" label="Customer name" required />
                        <Input v-model="shipping.shipping_service" label="Shipping service" />
                        <Input v-model="shipping.line1" label="Address line 1" required />
                        <Input v-model="shipping.line2" label="Address line 2" />
                        <Input v-model="shipping.city" label="City" required />
                        <Input v-model="shipping.state" label="State" />
                        <Input v-model="shipping.postal_code" label="Postal code" required />
                        <Input v-model="shipping.country" label="Country" required />
                    </div>
                    <Textarea v-model="notes" label="Internal notes" rows="3" placeholder="Any order-level handling notes..." />
                    <div class="flex justify-between">
                        <Button variant="outline" @click="goToStep(3)">Back</Button>
                        <Button @click="goToStep(5)">Next</Button>
                    </div>
                </div>

                <div v-else-if="step === 5 && !isPaymentStep" class="grid gap-5">
                    <div class="flex items-center gap-2">
                        <Layers3 class="h-5 w-5 text-[#18181b]" />
                        <h3 class="panel-title">Summary</h3>
                    </div>
                    <div class="grid gap-3">
                        <div v-for="(item, index) in items" :key="item.id" class="rounded-2xl bg-white p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-[#71717a]">Item {{ index + 1 }}</p>
                                    <h4 class="font-semibold text-[#18181b]">{{ variantFor(item)?.name }}</h4>
                                    <p class="text-sm text-[#4c4546]">{{ item.quantity }} unit · {{ item.print_option }} · {{ item.hanging_option || 'No hanger' }}</p>
                                    <p class="mt-1 text-sm text-[#71717a]">{{ item.artwork?.original_name }}</p>
                                </div>
                                <Badge tone="info">{{ money(itemSubtotal(item)) }}</Badge>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-2xl bg-white p-4 text-sm text-[#4c4546]">
                        <p class="font-semibold text-[#18181b]">{{ shipping.customer_name }}</p>
                        <p>{{ shipping.line1 }}, {{ shipping.city }}, {{ shipping.state }} {{ shipping.postal_code }}, {{ shipping.country }}</p>
                    </div>
                    <div class="flex flex-wrap justify-between gap-2">
                        <Button variant="outline" @click="goToStep(4)">Back</Button>
                        <div class="flex flex-wrap gap-2">
                            <Button variant="outline" :disabled="submitting" @click="submit('draft')"><Save class="h-4 w-4" /> Create draft</Button>
                            <Button :disabled="submitting" @click="submit('verified')"><Check class="h-4 w-4" /> Submit order</Button>
                        </div>
                    </div>
                </div>

                <div v-else-if="isPaymentStep" class="grid gap-5">
                    <div class="flex items-center gap-2">
                        <CreditCard class="h-5 w-5 text-[#18181b]" />
                        <h3 class="panel-title">Payment</h3>
                    </div>

                    <div class="rounded-2xl bg-white p-4">
                        <p class="mb-3 text-sm text-[#4c4546]">Kredi kartı ile güvenli ödeme. Tutar: <strong>{{ money(subtotalCents) }}</strong>.</p>
                        <div ref="stripeCardMount" class="min-h-[48px] rounded-lg border border-zinc-200 bg-[#f9f9f9] p-3"></div>
                    </div>

                    <Alert v-if="paymentMessage" tone="success" title="Ödeme hazırlanıyor" :description="paymentMessage" />
                    <Alert v-if="paymentError" tone="danger" title="Ödeme hatası" :description="paymentError" />

                    <div class="flex flex-wrap justify-between gap-2">
                        <Button variant="outline" @click="goToStep(5)">Back</Button>
                        <Button :disabled="paymentSubmitting" @click="submitPayment">
                            <Check class="h-4 w-4" />
                            {{ paymentSubmitting ? 'Ödeme işleniyor...' : `Pay ${money(subtotalCents)}` }}
                        </Button>
                    </div>
                </div>
            </Card>

            <Card class="h-fit 2xl:sticky 2xl:top-24">
                <div class="mb-4 flex items-center gap-2">
                    <PackagePlus class="h-5 w-5 text-[#18181b]" />
                    <h3 class="panel-title">Order Summary</h3>
                </div>
                <div v-if="items.some((item) => item.product_variant_id)" class="grid gap-3">
                    <div v-for="(item, index) in items" :key="item.id" class="rounded-2xl bg-white p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-[#18181b]">Item {{ index + 1 }}</p>
                                <p class="text-sm text-[#71717a]">{{ variantFor(item)?.sku ?? 'No product' }}</p>
                            </div>
                            <Badge tone="info">{{ money(itemSubtotal(item)) }}</Badge>
                        </div>
                    </div>
                    <div class="border-t border-zinc-200/70 pt-4">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-[#4c4546]">Subtotal</span>
                            <span class="text-xl font-bold text-[#18181b]">{{ money(subtotalCents) }}</span>
                        </div>
                    </div>
                </div>
                <EmptyState v-else title="No product selected" description="Select at least one production product to build the order." :icon="PackagePlus" />
            </Card>
        </div>
    </div>
</template>
