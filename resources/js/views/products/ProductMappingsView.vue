<script setup lang="ts">
import { Plus, SlidersHorizontal } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import { usePortalStore } from '@/stores/portal';

const store = usePortalStore();
const query = ref('');
const form = reactive({
    name: '',
    product_variant_id: '',
    frame: '',
    field: 'sku',
    operator: 'contains',
    value: '',
});

const mappings = computed(() => store.productMappings.filter((mapping) => {
    const text = `${mapping.name} ${mapping.variant?.sku} ${mapping.rules?.map((rule) => rule.value).join(' ')}`.toLowerCase();
    return text.includes(query.value.toLowerCase());
}));

async function createMapping() {
    if (!form.product_variant_id || !form.name || !form.value) {
        return;
    }

    await store.createMapping({
        product_variant_id: Number(form.product_variant_id),
        name: form.name,
        properties: form.frame ? { Frame: form.frame } : {},
        rules: [{
            field: form.field,
            operator: form.operator,
            value: form.value,
            priority: 60,
        }],
    });

    form.name = '';
    form.product_variant_id = '';
    form.frame = '';
    form.value = '';
}
</script>

<template>
    <div class="grid gap-5">
        <div>
            <h2 class="text-2xl font-bold text-slate-950">Product Mappings</h2>
            <p class="text-slate-600">Resolve marketplace item names and SKUs into production-ready product variants.</p>
        </div>

        <div class="grid gap-5 xl:grid-cols-[1fr_420px]">
            <Card>
                <div class="mb-4 grid gap-3 md:grid-cols-[1fr_auto]">
                    <Input v-model="query" label="Search mappings" placeholder="SKU, item name, frame, rule..." />
                    <Button variant="outline" class="self-end"><SlidersHorizontal class="h-4 w-4" /> Rules</Button>
                </div>

                <div class="overflow-hidden rounded-md border border-slate-200">
                    <table class="w-full min-w-[900px] text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Product</th>
                                <th class="px-4 py-3">Properties</th>
                                <th class="px-4 py-3">Rules</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr v-for="mapping in mappings" :key="mapping.id" class="bg-white">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-950">{{ mapping.variant?.sku }}</p>
                                    <p class="text-slate-500">{{ mapping.name }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <Badge v-for="(value, key) in mapping.properties" :key="key" tone="neutral">{{ key }}: {{ value }}</Badge>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="grid gap-1">
                                        <code v-for="rule in mapping.rules" :key="rule.value" class="rounded bg-slate-100 px-2 py-1 text-xs text-slate-700">
                                            {{ rule.field }} {{ rule.operator }} {{ rule.value }}
                                        </code>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>

            <Card>
                <div class="mb-4 flex items-center gap-2">
                    <Plus class="h-5 w-5 text-teal-700" />
                    <h3 class="text-lg font-bold text-slate-950">Create Mapping</h3>
                </div>
                <div class="grid gap-4">
                    <Input v-model="form.name" label="Mapping name" placeholder="Black framed art 36x24" />
                    <Select v-model="form.product_variant_id" label="Production product">
                        <option value="">Select variant</option>
                        <option v-for="variant in store.variants" :key="variant.id" :value="variant.id">
                            {{ variant.sku }} · {{ variant.name }}
                        </option>
                    </Select>
                    <Input v-model="form.frame" label="Property: Frame" placeholder="Black Modern Thin" />
                    <div class="grid grid-cols-2 gap-3">
                        <Select v-model="form.field" label="Field">
                            <option value="sku">Sku</option>
                            <option value="name">Name</option>
                            <option value="fulfillment_sku">Fulfillment Sku</option>
                        </Select>
                        <Select v-model="form.operator" label="Operator">
                            <option value="equals">equals</option>
                            <option value="contains">contains</option>
                            <option value="starts_with">starts with</option>
                            <option value="regex">regex</option>
                        </Select>
                    </div>
                    <Input v-model="form.value" label="Value" placeholder="MGC-FP-36x24_Black" />
                    <Button @click="createMapping">Add Rule Mapping</Button>
                </div>
            </Card>
        </div>
    </div>
</template>
