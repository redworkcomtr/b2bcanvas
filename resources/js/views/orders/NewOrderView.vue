<script setup lang="ts">
import { Check, ImageUp, Layers3, PackagePlus, Plus, Save, Trash2, Truck } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';
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
import type { MediaFile, ProductOption, ProductVariant } from '@/types/portal';

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
const items = ref<WizardItem[]>([blankItem()]);
const shipping = reactive({
    customer_name: '',
    line1: '',
    line2: '',
    city: '',
    state: '',
    postal_code: '',
    country: 'US',
    shipping_service: 'Standard Ground',
});
const notes = ref('');

const steps = ['Products', 'Configure', 'Artwork', 'Shipping', 'Summary'];
const activeItem = computed(() => items.value[activeIndex.value] ?? items.value[0]);
const activeVariant = computed(() => variantFor(activeItem.value));
const allConfigured = computed(() => items.value.every((item) => item.product_variant_id && item.quantity > 0));
const allOptionsReady = computed(() => items.value.every((item) => item.print_option));
const allArtworkReady = computed(() => items.value.every((item) => item.artwork));
const shippingReady = computed(() => shipping.customer_name && shipping.line1 && shipping.city && shipping.postal_code && shipping.country);
const subtotalCents = computed(() => items.value.reduce((total, item) => total + itemSubtotal(item), 0));

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

function saveLocalDraft(message = 'Draft saved locally.') {
    localStorage.setItem(draftKey, JSON.stringify({
        items: items.value,
        shipping,
        notes: notes.value,
        step: step.value,
        activeIndex: activeIndex.value,
    }));
    statusMessage.value = message;
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
        };
        if (draft.items?.length) {
            items.value = draft.items;
        }
        Object.assign(shipping, draft.shipping ?? {});
        notes.value = draft.notes ?? '';
        step.value = draft.step ?? 1;
        activeIndex.value = draft.activeIndex ?? 0;
    } catch {
        localStorage.removeItem(draftKey);
    }
}

function validateBeforeSubmit() {
    if (!allConfigured.value) {
        errorMessage.value = 'Every item needs a production product and quantity.';
        step.value = 1;
        return false;
    }
    if (!allOptionsReady.value) {
        errorMessage.value = 'Every item needs a print option.';
        step.value = 2;
        return false;
    }
    if (!allArtworkReady.value) {
        errorMessage.value = 'Every item needs an uploaded artwork file.';
        step.value = 3;
        return false;
    }
    if (!shippingReady.value) {
        errorMessage.value = 'Shipping information is required before submitting.';
        step.value = 4;
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

        localStorage.removeItem(draftKey);
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
    saveLocalDraft('');
}, { deep: true });

onMounted(loadLocalDraft);
</script>

<template>
    <div class="grid gap-5">
        <div class="flex flex-col justify-between gap-4 xl:flex-row xl:items-end">
            <div>
                <h2 class="text-2xl font-bold text-slate-950">Submit New Order</h2>
                <p class="text-slate-600">Build a multi-item print order with artwork, production options, pricing, and shipping gates.</p>
            </div>
            <Button variant="outline" @click="saveLocalDraft()"><Save class="h-4 w-4" /> Save draft</Button>
        </div>

        <Alert v-if="statusMessage" tone="success" title="Wizard state saved" :description="statusMessage" />
        <Alert v-if="errorMessage" tone="danger" title="Order wizard warning" :description="errorMessage" />

        <div class="grid gap-5 2xl:grid-cols-[1fr_380px]">
            <Card>
                <div class="mb-6 grid gap-2 md:grid-cols-5">
                    <button
                        v-for="(label, index) in steps"
                        :key="label"
                        :class="['rounded-md border px-3 py-2 text-left text-sm font-semibold', step === index + 1 ? 'border-teal-500 bg-teal-50 text-teal-800' : 'border-slate-200 bg-white text-slate-500']"
                        @click="step = index + 1"
                    >
                        {{ label }}
                    </button>
                </div>

                <div v-if="step === 1" class="grid gap-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-bold text-slate-950">Products</h3>
                            <p class="text-sm text-slate-500">Add every production line item before configuring options and artwork.</p>
                        </div>
                        <Button variant="outline" @click="addItem"><Plus class="h-4 w-4" /> Item</Button>
                    </div>

                    <div class="grid gap-3 lg:grid-cols-[240px_1fr]">
                        <div class="grid gap-2">
                            <button
                                v-for="(item, index) in items"
                                :key="item.id"
                                :class="['rounded-md border p-3 text-left text-sm', activeIndex === index ? 'border-teal-400 bg-teal-50' : 'border-slate-200 bg-white']"
                                @click="activeIndex = index"
                            >
                                <p class="font-bold text-slate-950">Item {{ index + 1 }}</p>
                                <p class="mt-1 text-slate-500">{{ variantFor(item)?.sku ?? 'No product selected' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ money(itemSubtotal(item)) }}</p>
                            </button>
                        </div>

                        <div class="grid gap-4 rounded-md border border-slate-200 p-4">
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

                            <div v-if="activeVariant" class="rounded-md bg-slate-50 p-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h4 class="font-bold text-slate-950">{{ activeVariant.name }}</h4>
                                        <p class="text-sm text-slate-500">{{ activeVariant.product_type?.description }}</p>
                                    </div>
                                    <Badge tone="info">{{ money(activeVariant.price_cents) }}</Badge>
                                </div>
                                <div class="mt-3 grid gap-2 text-sm text-slate-600 md:grid-cols-3">
                                    <span><strong>Panels:</strong> {{ activeVariant.panel_count }}</span>
                                    <span><strong>Layout:</strong> {{ activeVariant.layout }}</span>
                                    <span><strong>Template:</strong> {{ activeVariant.template_url ?? '-' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <Button :disabled="!allConfigured" @click="step = 2">Next</Button>
                    </div>
                </div>

                <div v-else-if="step === 2" class="grid gap-5">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-lg font-bold text-slate-950">Configure Item {{ activeIndex + 1 }}</h3>
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
                        <Button variant="outline" @click="step = 1">Back</Button>
                        <Button :disabled="!allOptionsReady" @click="step = 3">Next</Button>
                    </div>
                </div>

                <div v-else-if="step === 3" class="grid gap-5">
                    <div class="flex items-center gap-2">
                        <ImageUp class="h-5 w-5 text-teal-700" />
                        <h3 class="text-lg font-bold text-slate-950">Artwork Upload</h3>
                    </div>
                    <div class="grid gap-4 md:grid-cols-[1fr_260px]">
                        <FileDropzone
                            label="Upload artwork or print-ready panel"
                            description="Files are stored immediately and attached to the order when submitted."
                            accept="image/*,.pdf"
                            @selected="uploadArtwork"
                        />
                        <div class="rounded-md border border-slate-200 p-4">
                            <p class="text-sm font-bold text-slate-950">Item {{ activeIndex + 1 }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ variantFor(activeItem)?.name ?? 'No product selected' }}</p>
                            <Badge v-if="activeItem.artwork" class="mt-3" tone="success">{{ activeItem.artwork.original_name }}</Badge>
                            <Badge v-else class="mt-3" tone="warning">{{ uploading ? 'Uploading...' : 'Artwork required' }}</Badge>
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <button
                            v-for="(item, index) in items"
                            :key="item.id"
                            :class="['rounded-md border px-3 py-2 text-left text-sm', activeIndex === index ? 'border-teal-400 bg-teal-50' : 'border-slate-200 bg-white']"
                            @click="activeIndex = index"
                        >
                            Item {{ index + 1 }} · {{ variantFor(item)?.sku ?? 'No SKU' }} · {{ item.artwork?.original_name ?? 'No artwork' }}
                        </button>
                    </div>
                    <div class="flex justify-between">
                        <Button variant="outline" @click="step = 2">Back</Button>
                        <Button :disabled="!allArtworkReady" @click="step = 4">Next</Button>
                    </div>
                </div>

                <div v-else-if="step === 4" class="grid gap-5">
                    <div class="flex items-center gap-2">
                        <Truck class="h-5 w-5 text-teal-700" />
                        <h3 class="text-lg font-bold text-slate-950">Shipping</h3>
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
                        <Button variant="outline" @click="step = 3">Back</Button>
                        <Button :disabled="!shippingReady" @click="step = 5">Next</Button>
                    </div>
                </div>

                <div v-else class="grid gap-5">
                    <div class="flex items-center gap-2">
                        <Layers3 class="h-5 w-5 text-teal-700" />
                        <h3 class="text-lg font-bold text-slate-950">Summary</h3>
                    </div>
                    <div class="grid gap-3">
                        <div v-for="(item, index) in items" :key="item.id" class="rounded-md border border-slate-200 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-500">Item {{ index + 1 }}</p>
                                    <h4 class="font-bold text-slate-950">{{ variantFor(item)?.name }}</h4>
                                    <p class="text-sm text-slate-600">{{ item.quantity }} unit · {{ item.print_option }} · {{ item.hanging_option || 'No hanger' }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ item.artwork?.original_name }}</p>
                                </div>
                                <Badge tone="info">{{ money(itemSubtotal(item)) }}</Badge>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-md bg-slate-50 p-4 text-sm text-slate-700">
                        <p class="font-bold text-slate-950">{{ shipping.customer_name }}</p>
                        <p>{{ shipping.line1 }}, {{ shipping.city }}, {{ shipping.state }} {{ shipping.postal_code }}, {{ shipping.country }}</p>
                    </div>
                    <div class="flex flex-wrap justify-between gap-2">
                        <Button variant="outline" @click="step = 4">Back</Button>
                        <div class="flex flex-wrap gap-2">
                            <Button variant="outline" :disabled="submitting" @click="submit('draft')"><Save class="h-4 w-4" /> Create draft</Button>
                            <Button :disabled="submitting" @click="submit('verified')"><Check class="h-4 w-4" /> Submit order</Button>
                        </div>
                    </div>
                </div>
            </Card>

            <Card class="h-fit 2xl:sticky 2xl:top-24">
                <div class="mb-4 flex items-center gap-2">
                    <PackagePlus class="h-5 w-5 text-teal-700" />
                    <h3 class="text-lg font-bold text-slate-950">Order Summary</h3>
                </div>
                <div v-if="items.some((item) => item.product_variant_id)" class="grid gap-3">
                    <div v-for="(item, index) in items" :key="item.id" class="rounded-md border border-slate-200 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-950">Item {{ index + 1 }}</p>
                                <p class="text-sm text-slate-500">{{ variantFor(item)?.sku ?? 'No product' }}</p>
                            </div>
                            <Badge tone="info">{{ money(itemSubtotal(item)) }}</Badge>
                        </div>
                    </div>
                    <div class="border-t border-slate-200 pt-4">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-slate-600">Subtotal</span>
                            <span class="text-xl font-bold text-slate-950">{{ money(subtotalCents) }}</span>
                        </div>
                    </div>
                </div>
                <EmptyState v-else title="No product selected" description="Select at least one production product to build the order." :icon="PackagePlus" />
            </Card>
        </div>
    </div>
</template>
