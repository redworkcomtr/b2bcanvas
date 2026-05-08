<script setup lang="ts">
import { Check, ImageUp, PackagePlus } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';

import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import { money } from '@/lib/utils';
import { usePortalStore } from '@/stores/portal';

const store = usePortalStore();
const router = useRouter();
const step = ref(1);
const selectedTypeId = ref('');
const selectedVariantId = ref('');
const selectedVariant = computed(() => store.variants.find((variant) => String(variant.id) === selectedVariantId.value));
const options = reactive({
    quantity: 1,
    clientSku: '',
    printOption: '',
    hangingOption: 'None',
    imageName: '',
});
const shipping = reactive({
    customer_name: '',
    line1: '',
    city: '',
    state: '',
    postal_code: '',
    country: 'US',
    shipping_service: 'Standard Ground',
});

const productOptions = computed(() => {
    const type = store.productTypes.find((item) => String(item.id) === selectedTypeId.value);
    return type?.options ?? [];
});
const printOptions = computed(() => productOptions.value.filter((option) => option.group === 'Print Options'));
const hangingOptions = computed(() => productOptions.value.filter((option) => option.group === 'Hanging Options'));
const canConfigure = computed(() => Boolean(selectedVariant.value));
const canShip = computed(() => options.printOption && options.imageName);
const canSubmit = computed(() => shipping.customer_name && shipping.line1 && shipping.city && shipping.state && shipping.postal_code);

async function submit() {
    if (!selectedVariant.value) {
        return;
    }

    const order = await store.createOrder({
        order_number: `WEB-${Date.now()}`,
        customer_name: shipping.customer_name,
        shipping_service: shipping.shipping_service,
        shipping_address: {
            line1: shipping.line1,
            city: shipping.city,
            state: shipping.state,
            postal_code: shipping.postal_code,
            country: shipping.country,
        },
        items: [{
            product_variant_id: selectedVariant.value.id,
            item_name: selectedVariant.value.name,
            item_sku: options.clientSku || selectedVariant.value.sku,
            quantity: options.quantity,
            product_code: selectedVariant.value.sku,
            product_type: selectedVariant.value.product_type?.name,
            panel_summary: selectedVariant.value.panel_sizes?.join(', '),
            design_images: ['https://images.unsplash.com/photo-1518005020951-eccb494ad742?auto=format&fit=crop&w=900&q=80'],
            options: {
                print: options.printOption,
                hanging: options.hangingOption,
                source_file: options.imageName,
            },
        }],
    });

    router.push(`/orders/${order.uuid}`);
}
</script>

<template>
    <div class="grid gap-5">
        <div>
            <h2 class="text-2xl font-bold text-slate-950">Submit New Order</h2>
            <p class="text-slate-600">A guided order wizard with product selection, artwork gate, shipping, and summary.</p>
        </div>

        <div class="grid gap-5 xl:grid-cols-[1fr_340px]">
            <Card>
                <div class="mb-6 grid gap-2 md:grid-cols-4">
                    <button v-for="index in 4" :key="index" :class="['rounded-md border px-3 py-2 text-left text-sm font-semibold', step === index ? 'border-teal-500 bg-teal-50 text-teal-800' : 'border-slate-200 bg-white text-slate-500']">
                        {{ ['Select products', 'Configure products', 'Shipping information', 'Summary'][index - 1] }}
                    </button>
                </div>

                <div v-if="step === 1" class="grid gap-4">
                    <Select v-model="selectedTypeId" label="Select product type">
                        <option value="">Choose product type</option>
                        <option v-for="type in store.productTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                    </Select>
                    <Select v-model="selectedVariantId" label="Select product">
                        <option value="">Choose product variant</option>
                        <option v-for="variant in store.variants.filter((item) => String(item.product_type_id) === selectedTypeId)" :key="variant.id" :value="variant.id">
                            {{ variant.name }} | {{ variant.sku }}
                        </option>
                    </Select>

                    <div v-if="selectedVariant" class="rounded-lg border border-slate-200 p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-bold text-slate-950">{{ selectedVariant.name }}</h3>
                                <p class="text-sm text-slate-500">{{ selectedVariant.product_type?.description }}</p>
                            </div>
                            <Badge tone="info">{{ money(selectedVariant.price_cents) }}</Badge>
                        </div>
                        <div class="mt-4 grid gap-2 text-sm text-slate-600 md:grid-cols-3">
                            <span><strong>SKU:</strong> {{ selectedVariant.sku }}</span>
                            <span><strong>Panels:</strong> {{ selectedVariant.panel_count }}</span>
                            <span><strong>Layout:</strong> {{ selectedVariant.layout }}</span>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <Button :disabled="!canConfigure" @click="step = 2">Next</Button>
                    </div>
                </div>

                <div v-else-if="step === 2" class="grid gap-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <Input v-model="options.quantity" label="Quantity" type="number" />
                        <Input v-model="options.clientSku" label="Client product SKU" placeholder="Optional marketplace SKU" />
                    </div>
                    <Select v-model="options.printOption" label="Print Options *">
                        <option value="">Select bleed method</option>
                        <option v-for="option in printOptions" :key="option.id" :value="option.name">{{ option.name }} · {{ money(option.price_cents) }}</option>
                    </Select>
                    <Select v-model="options.hangingOption" label="Hanging Options">
                        <option v-for="option in hangingOptions" :key="option.id" :value="option.name">{{ option.name }} · {{ money(option.price_cents) }}</option>
                    </Select>
                    <label class="grid gap-2 rounded-lg border border-dashed border-slate-300 p-5 text-sm">
                        <span class="flex items-center gap-2 font-semibold text-slate-800"><ImageUp class="h-4 w-4" /> Panel image upload gate</span>
                        <span class="text-slate-500">Select a local artwork file name for this MVP. Storage wiring is ready on the backend plan.</span>
                        <input type="file" class="text-sm" @change="options.imageName = ($event.target as HTMLInputElement).files?.[0]?.name ?? ''">
                        <Badge v-if="options.imageName" tone="success">{{ options.imageName }}</Badge>
                    </label>
                    <div class="flex justify-between">
                        <Button variant="outline" @click="step = 1">Back</Button>
                        <Button :disabled="!canShip" @click="step = 3">Next</Button>
                    </div>
                </div>

                <div v-else-if="step === 3" class="grid gap-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <Input v-model="shipping.customer_name" label="Customer name" />
                        <Input v-model="shipping.shipping_service" label="Shipping service" />
                        <Input v-model="shipping.line1" label="Address line 1" />
                        <Input v-model="shipping.city" label="City" />
                        <Input v-model="shipping.state" label="State" />
                        <Input v-model="shipping.postal_code" label="Postal code" />
                    </div>
                    <div class="flex justify-between">
                        <Button variant="outline" @click="step = 2">Back</Button>
                        <Button :disabled="!canSubmit" @click="step = 4">Next</Button>
                    </div>
                </div>

                <div v-else class="grid gap-4">
                    <div class="rounded-lg border border-slate-200 p-4">
                        <h3 class="font-bold text-slate-950">{{ selectedVariant?.name }}</h3>
                        <p class="text-sm text-slate-600">{{ options.quantity }} unit · {{ options.printOption }} · {{ options.hangingOption }}</p>
                        <p class="mt-2 text-sm text-slate-600">{{ shipping.customer_name }} · {{ shipping.line1 }}, {{ shipping.city }}</p>
                    </div>
                    <div class="flex justify-between">
                        <Button variant="outline" @click="step = 3">Back</Button>
                        <Button @click="submit"><Check class="h-4 w-4" /> Submit Order</Button>
                    </div>
                </div>
            </Card>

            <Card>
                <div class="mb-4 flex items-center gap-2">
                    <PackagePlus class="h-5 w-5 text-teal-700" />
                    <h3 class="text-lg font-bold text-slate-950">Selected Product</h3>
                </div>
                <div v-if="selectedVariant" class="grid gap-3 text-sm">
                    <p class="font-semibold text-slate-950">{{ selectedVariant.name }}</p>
                    <p class="text-slate-500">{{ selectedVariant.sku }}</p>
                    <Badge tone="info">{{ money(selectedVariant.price_cents) }}</Badge>
                </div>
                <p v-else class="text-sm text-slate-500">Select and configure a product first.</p>
            </Card>
        </div>
    </div>
</template>
