<script setup lang="ts">
import { CheckCircle2, Download, FileSpreadsheet, History, Upload } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import FileDropzone from '@/components/ui/FileDropzone.vue';
import DataTable from '@/components/ui/Table.vue';
import Textarea from '@/components/ui/Textarea.vue';
import { dateLabel } from '@/lib/utils';
import { usePortalStore } from '@/stores/portal';
import type { ImportBatch, ImportPreview } from '@/types/portal';

const store = usePortalStore();
const sample = `order_number,item_name,item_sku,quantity,customer_name,address_line_1,city,state,postal_code,country
WEB-9001,Framed Art Print-Black / 36" x 24",MGC-FP-36x24_Black,1,Jordan Lee,101 Harbor Road,Seattle,WA,98101,US
WEB-9002,Unknown marketplace product,CUSTOM-44x30,1,Mina Chen,225 Lake Drive,Denver,CO,80202,US`;
const csv = ref(sample);
const preview = ref<ImportPreview | null>(null);
const history = ref<ImportBatch[]>([]);
const loading = ref(false);
const statusMessage = ref('');
const errorMessage = ref('');

async function loadHistory() {
    history.value = await store.importHistory();
}

async function runPreview() {
    loading.value = true;
    statusMessage.value = '';
    errorMessage.value = '';
    try {
        preview.value = await store.previewImport(csv.value);
        await loadHistory();
        await store.load();
        statusMessage.value = `Import #${preview.value.import_id} previewed.`;
    } catch {
        errorMessage.value = 'Import preview failed.';
    } finally {
        loading.value = false;
    }
}

async function commitPreview() {
    if (!preview.value) {
        return;
    }

    loading.value = true;
    statusMessage.value = '';
    const result = await store.commitImport(preview.value.import_id);
    await loadHistory();
    statusMessage.value = `${result.created_orders} orders committed from import #${preview.value.import_id}.`;
    loading.value = false;
}

function useUploadedCsv(file: File) {
    const reader = new FileReader();
    reader.onload = () => {
        csv.value = String(reader.result ?? '');
    };
    reader.readAsText(file);
}

onMounted(loadHistory);
</script>

<template>
    <div class="grid gap-5">
        <div>
            <h2 class="text-2xl font-bold text-slate-950">Import Orders</h2>
            <p class="text-slate-600">Preview CSV rows, resolve mapping exceptions, commit ready orders, and keep import history auditable.</p>
        </div>

        <Alert v-if="statusMessage" tone="success" title="Import workflow updated" :description="statusMessage" />
        <Alert v-if="errorMessage" tone="danger" title="Import workflow warning" :description="errorMessage" />

        <div class="grid gap-5 xl:grid-cols-[1fr_360px]">
            <Card>
                <div class="mb-4 flex items-center gap-2">
                    <FileSpreadsheet class="h-5 w-5 text-teal-700" />
                    <h3 class="text-lg font-bold text-slate-950">CSV / XLSX Intake</h3>
                </div>
                <Textarea
                    v-model="csv"
                    label="CSV contents"
                    :rows="12"
                    help="CSV is parsed immediately. XLSX files can be uploaded into the same intake surface once queue conversion is enabled."
                />
                <div class="mt-4">
                    <FileDropzone label="Upload CSV file" description="CSV files are read locally into the preview editor." accept=".csv,.txt" @selected="useUploadedCsv" />
                </div>
                <div class="mt-4 flex flex-wrap justify-end gap-2">
                    <Button variant="outline" @click="csv = sample"><Download class="h-4 w-4" /> Sample CSV</Button>
                    <Button :disabled="loading" @click="runPreview"><Upload class="h-4 w-4" /> Preview Import</Button>
                </div>
            </Card>

            <Card>
                <div class="mb-4 flex items-center gap-2">
                    <History class="h-5 w-5 text-teal-700" />
                    <h3 class="text-lg font-bold text-slate-950">Import History</h3>
                </div>
                <div class="grid gap-3">
                    <div v-for="batch in history" :key="batch.id" class="rounded-md border border-slate-200 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-950">#{{ batch.id }} {{ batch.filename }}</p>
                                <p class="text-sm text-slate-500">{{ dateLabel(batch.created_at) }} · {{ batch.total_rows }} rows</p>
                            </div>
                            <Badge :tone="batch.status === 'committed' ? 'success' : batch.status === 'partial' ? 'warning' : 'info'">{{ batch.status }}</Badge>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-500">
                            <span>{{ batch.valid_rows }} ready</span>
                            <span>{{ batch.invalid_rows }} needs action</span>
                            <a class="font-semibold text-teal-700" :href="`/api/orders/imports/${batch.id}/errors`">errors.csv</a>
                        </div>
                    </div>
                </div>
            </Card>
        </div>

        <Card v-if="preview">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap gap-2">
                    <Badge tone="info">{{ preview.summary.total }} rows</Badge>
                    <Badge tone="success">{{ preview.summary.ready }} ready</Badge>
                    <Badge tone="warning">{{ preview.summary.needs_action }} needs action</Badge>
                </div>
                <Button :disabled="loading || preview.summary.ready === 0" @click="commitPreview"><CheckCircle2 class="h-4 w-4" /> Commit ready rows</Button>
            </div>
            <DataTable min-width="980px">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Row</th>
                        <th class="px-4 py-3">Order</th>
                        <th class="px-4 py-3">Item</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Matched Mapping</th>
                        <th class="px-4 py-3">Errors</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr v-for="row in preview.rows" :key="row.row_number">
                        <td class="px-4 py-3">{{ row.row_number }}</td>
                        <td class="px-4 py-3">{{ row.payload.order_number }}</td>
                        <td class="px-4 py-3">{{ row.payload.item_name }}</td>
                        <td class="px-4 py-3"><Badge :tone="row.status === 'ready' ? 'success' : 'warning'">{{ row.status.replace('_', ' ') }}</Badge></td>
                        <td class="px-4 py-3">{{ row.matched_mapping?.name ?? 'No match' }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ row.errors?.join(', ') || '-' }}</td>
                    </tr>
                </tbody>
            </DataTable>
        </Card>
    </div>
</template>
