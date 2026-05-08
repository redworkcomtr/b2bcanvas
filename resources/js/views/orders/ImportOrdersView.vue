<script setup lang="ts">
import { FileSpreadsheet, Upload } from 'lucide-vue-next';
import { ref } from 'vue';

import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import { usePortalStore } from '@/stores/portal';
import type { ImportPreview } from '@/types/portal';

const store = usePortalStore();
const sample = `order_number,item_name,item_sku,quantity,customer_name,address_line_1,city,state,postal_code,country
WEB-9001,Framed Art Print-Black / 36" x 24",MGC-FP-36x24_Black,1,Jordan Lee,101 Harbor Road,Seattle,WA,98101,US
WEB-9002,Unknown marketplace product,CUSTOM-44x30,1,Mina Chen,225 Lake Drive,Denver,CO,80202,US`;
const csv = ref(sample);
const preview = ref<ImportPreview | null>(null);

async function runPreview() {
    preview.value = await store.previewImport(csv.value);
    await store.load();
}
</script>

<template>
    <div class="grid gap-5">
        <div>
            <h2 class="text-2xl font-bold text-slate-950">Submit Multiple New Orders</h2>
            <p class="text-slate-600">Upload or paste CSV order data, preview mapping results, and route exceptions into Required Actions.</p>
        </div>

        <div class="grid gap-5 xl:grid-cols-[1fr_360px]">
            <Card>
                <div class="mb-4 flex items-center gap-2">
                    <FileSpreadsheet class="h-5 w-5 text-teal-700" />
                    <h3 class="text-lg font-bold text-slate-950">CSV Preview</h3>
                </div>
                <textarea
                    v-model="csv"
                    class="focus-ring min-h-72 w-full rounded-md border border-slate-300 p-3 font-mono text-sm shadow-sm"
                    spellcheck="false"
                />
                <div class="mt-4 flex justify-end">
                    <Button @click="runPreview"><Upload class="h-4 w-4" /> Preview Import</Button>
                </div>
            </Card>

            <Card>
                <h3 class="text-lg font-bold text-slate-950">Import Rules</h3>
                <ul class="mt-3 grid gap-3 text-sm text-slate-600">
                    <li class="rounded-md bg-slate-50 p-3">Required columns are validated before batch creation.</li>
                    <li class="rounded-md bg-slate-50 p-3">Product mapping is resolved by SKU, fulfillment SKU, and item name rules.</li>
                    <li class="rounded-md bg-slate-50 p-3">Unmapped rows automatically create Required Actions.</li>
                </ul>
            </Card>
        </div>

        <Card v-if="preview">
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <Badge tone="info">{{ preview.summary.total }} rows</Badge>
                <Badge tone="success">{{ preview.summary.ready }} ready</Badge>
                <Badge tone="warning">{{ preview.summary.needs_action }} needs action</Badge>
            </div>
            <div class="overflow-hidden rounded-md border border-slate-200">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr><th class="px-4 py-3">Row</th><th class="px-4 py-3">Order</th><th class="px-4 py-3">Item</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Matched Mapping</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr v-for="row in preview.rows" :key="row.row_number">
                            <td class="px-4 py-3">{{ row.row_number }}</td>
                            <td class="px-4 py-3">{{ row.payload.order_number }}</td>
                            <td class="px-4 py-3">{{ row.payload.item_name }}</td>
                            <td class="px-4 py-3"><Badge :tone="row.status === 'ready' ? 'success' : 'warning'">{{ row.status.replace('_', ' ') }}</Badge></td>
                            <td class="px-4 py-3">{{ row.matched_mapping?.name ?? 'No match' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </div>
</template>
