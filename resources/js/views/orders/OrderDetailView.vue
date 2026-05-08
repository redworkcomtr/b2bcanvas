<script setup lang="ts">
import { ArrowLeft, Image, LifeBuoy, MapPin, Pencil, Printer } from 'lucide-vue-next';
import { computed } from 'vue';
import { RouterLink } from 'vue-router';

import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import { dateLabel, money, statusTone } from '@/lib/utils';
import { usePortalStore } from '@/stores/portal';

const props = defineProps<{ uuid: string }>();
const store = usePortalStore();
const order = computed(() => store.orders.find((item) => item.uuid === props.uuid));
const address = computed(() => order.value?.shipping_address ?? {});
</script>

<template>
    <div v-if="order" class="grid gap-5">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <RouterLink to="/orders">
                    <Button variant="ghost" size="icon" aria-label="Back to orders">
                        <ArrowLeft class="h-5 w-5" />
                    </Button>
                </RouterLink>
                <div>
                    <h2 class="text-2xl font-bold text-slate-950">Order Details</h2>
                    <p class="text-slate-600">{{ order.order_number }} · {{ order.customer_name }}</p>
                </div>
            </div>
            <Badge :tone="statusTone(order.status)">{{ order.status.replace('_', ' ') }}</Badge>
        </div>

        <div class="grid gap-5 xl:grid-cols-[1fr_340px]">
            <div class="grid gap-5">
                <Card>
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Order Id</p>
                            <h3 class="mt-1 text-2xl font-bold text-slate-950">{{ order.order_number }}</h3>
                        </div>
                        <div class="grid gap-1 text-sm text-slate-600 md:grid-cols-3 md:gap-6">
                            <span><strong>Status:</strong> {{ order.status.replace('_', ' ') }}</span>
                            <span><strong>Order Date:</strong> {{ dateLabel(order.order_date) }}</span>
                            <span><strong>Submitted:</strong> {{ dateLabel(order.submitted_at) }}</span>
                        </div>
                    </div>
                </Card>

                <Card>
                    <div class="mb-4 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <MapPin class="h-5 w-5 text-teal-700" />
                            <h3 class="text-lg font-bold text-slate-950">Shipping Details</h3>
                        </div>
                        <Button variant="ghost" size="icon" aria-label="Edit address">
                            <Pencil class="h-4 w-4" />
                        </Button>
                    </div>
                    <p class="text-sm leading-6 text-slate-700">
                        {{ order.customer_name }},
                        {{ address.line1 }},
                        {{ address.city }},
                        {{ address.state }}
                        {{ address.postal_code }},
                        {{ address.country }}
                    </p>
                    <p class="mt-2 text-sm text-slate-500">Service: {{ order.shipping_service ?? '-' }}</p>
                </Card>

                <Card v-for="item in order.items" :key="item.id">
                    <div class="mb-4 flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Item SKU: {{ item.item_sku ?? 'N/A' }}</p>
                            <h3 class="mt-1 text-xl font-bold text-slate-950">{{ item.item_name }}</h3>
                        </div>
                        <Badge tone="info">{{ item.quantity }} item</Badge>
                    </div>

                    <div class="grid gap-4 md:grid-cols-4">
                        <div><p class="text-xs text-slate-500">Product Code</p><p class="font-semibold">{{ item.product_code ?? 'Unmapped' }}</p></div>
                        <div><p class="text-xs text-slate-500">Product Type</p><p class="font-semibold">{{ item.product_type ?? 'Pending' }}</p></div>
                        <div><p class="text-xs text-slate-500">Panels</p><p class="font-semibold">{{ item.panel_summary ?? '-' }}</p></div>
                        <div><p class="text-xs text-slate-500">Price</p><p class="font-semibold">{{ money(item.variant?.price_cents ?? 0) }}</p></div>
                    </div>

                    <div class="mt-5 grid gap-4 lg:grid-cols-[1fr_280px]">
                        <div>
                            <div class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                                <Image class="h-4 w-4" />
                                Design Images
                            </div>
                            <img
                                v-if="item.design_images?.[0]"
                                :src="item.design_images[0]"
                                :alt="item.item_name"
                                class="aspect-[16/9] w-full rounded-md border border-slate-200 object-cover"
                            >
                            <div v-else class="grid aspect-[16/9] place-items-center rounded-md border border-dashed border-slate-300 text-sm text-slate-500">
                                No design image
                            </div>
                        </div>

                        <div>
                            <div class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                                <Printer class="h-4 w-4" />
                                Options
                            </div>
                            <ul class="grid gap-2 text-sm text-slate-700">
                                <li v-for="(value, key) in item.options" :key="key" class="rounded-md bg-slate-50 px-3 py-2">
                                    <strong>{{ key }}:</strong> {{ value }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </Card>
            </div>

            <Card>
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-950">Issues</h3>
                    <RouterLink to="/issues/tickets">
                        <Button size="sm"><LifeBuoy class="h-4 w-4" /> Open Ticket</Button>
                    </RouterLink>
                </div>
                <div v-if="order.issues?.length" class="grid gap-3">
                    <div v-for="issue in order.issues" :key="issue.id" class="rounded-md border border-slate-200 p-3">
                        <Badge :tone="statusTone(issue.status)">{{ issue.status }}</Badge>
                        <p class="mt-2 text-sm text-slate-700">{{ issue.description }}</p>
                    </div>
                </div>
                <p v-else class="text-sm text-slate-500">No issues for this order.</p>
            </Card>
        </div>
    </div>

    <Card v-else>
        <p class="text-sm text-slate-600">Order not found. Return to the orders list and select another order.</p>
    </Card>
</template>
