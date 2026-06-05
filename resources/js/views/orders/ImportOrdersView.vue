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
const csv = ref('');
const selectedFile = ref<File | null>(null);
const sampleFilename = ref('sample-import.csv');
const sampleLoading = ref(true);
const preview = ref<ImportPreview | null>(null);
const history = ref<ImportBatch[]>([]);
const loading = ref(false);
const statusMessage = ref('');
const errorMessage = ref('');
const templateError = ref('');

async function loadHistory() {
    history.value = await store.importHistory();
}

async function loadTemplate() {
    sampleLoading.value = true;
    templateError.value = '';

    try {
        const template = await store.importTemplate();
        csv.value = template.sample;
        selectedFile.value = null;
        sampleFilename.value = template.name;
    } catch {
        templateError.value = 'Sample template could not be loaded.';
        csv.value = '';
    } finally {
        sampleLoading.value = false;
    }
}

async function runPreview() {
    loading.value = true;
    statusMessage.value = '';
    errorMessage.value = '';
    try {
        preview.value = await store.previewImport(selectedFile.value ?? csv.value, selectedFile.value?.name ?? sampleFilename.value);
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
    const extension = file.name.split('.').pop()?.toLowerCase();
    if (extension === 'xlsx' || file.type.includes('spreadsheet')) {
        selectedFile.value = file;
        csv.value = '';
        sampleFilename.value = file.name;
        statusMessage.value = `${file.name} selected for XLSX preview.`;
        return;
    }

    selectedFile.value = null;
    const reader = new FileReader();
    reader.onload = () => {
        csv.value = String(reader.result ?? '');
    };
    reader.readAsText(file);
}

function downloadTemplate() {
    const text = csv.value;
    if (!text) {
        return;
    }

    const file = new Blob([text], { type: 'text/csv;charset=utf-8' });
    const link = document.createElement('a');
    const href = URL.createObjectURL(file);
    link.href = href;
    link.download = sampleFilename.value || 'sample-import.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(href);
}

onMounted(() => {
    void loadHistory();
    void loadTemplate();
});
</script>

<template>
    <div class="app-page">
        <div class="flex flex-col justify-between gap-3 lg:flex-row lg:items-end">
            <div class="page-heading">
                <h2>Import orders</h2>
                <p>{{ history.length }} batches · {{ sampleFilename }}</p>
            </div>
            <Button variant="outline" size="sm" :disabled="sampleLoading || !csv" @click="downloadTemplate">
                <Download class="h-4 w-4" />
                Template
            </Button>
        </div>

        <Alert v-if="statusMessage" tone="success" title="Import workflow updated" :description="statusMessage" />
        <Alert v-if="errorMessage" tone="danger" title="Import workflow warning" :description="errorMessage" />

        <div class="grid gap-5 xl:grid-cols-[1fr_360px]">
            <Card>
                <div class="mb-4 flex items-center gap-2">
                    <FileSpreadsheet class="h-5 w-5 text-[#18181b]" />
                    <h3 class="panel-title">CSV / XLSX intake</h3>
                </div>
                <p v-if="selectedFile" class="mb-3 rounded-2xl bg-white px-3 py-2 text-sm font-medium text-[#18181b]">
                    {{ selectedFile.name }} is ready for backend preview.
                </p>
                <Textarea
                    v-model="csv"
                    label="CSV contents"
                    :rows="9"
                />
                <p v-if="templateError" class="mt-2 text-xs text-red-600">{{ templateError }}</p>
                <div class="mt-4">
                    <FileDropzone label="Upload CSV or XLSX file" accept=".csv,.txt,.xlsx" @selected="useUploadedCsv" />
                </div>
                <div class="mt-4 flex flex-wrap justify-end gap-2">
                    <Button :disabled="loading || (!csv && !selectedFile)" @click="runPreview"><Upload class="h-4 w-4" /> Preview Import</Button>
                </div>
            </Card>

            <Card>
                <div class="mb-4 flex items-center gap-2">
                    <History class="h-5 w-5 text-[#18181b]" />
                    <h3 class="panel-title">Import history</h3>
                </div>
                <div class="grid gap-3">
                    <div v-for="batch in history" :key="batch.id" class="rounded-2xl bg-white p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-[#18181b]">#{{ batch.id }} {{ batch.filename }}</p>
                                <p class="text-sm text-[#71717a]">{{ dateLabel(batch.created_at) }} · {{ batch.total_rows }} rows</p>
                            </div>
                            <Badge :tone="batch.status === 'committed' ? 'success' : batch.status === 'partial' ? 'warning' : 'info'">{{ batch.status }}</Badge>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2 text-xs text-[#71717a]">
                            <span>{{ batch.valid_rows }} ready</span>
                            <span>{{ batch.invalid_rows }} needs action</span>
                            <a class="font-semibold text-[#18181b]" :href="`/api/orders/imports/${batch.id}/errors`">errors.csv</a>
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
                <thead>
                    <tr>
                        <th class="px-4 py-3">Row</th>
                        <th class="px-4 py-3">Order</th>
                        <th class="px-4 py-3">Item</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Matched Mapping</th>
                        <th class="px-4 py-3">Errors</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <tr v-for="row in preview.rows" :key="row.row_number">
                        <td class="px-4 py-3">{{ row.row_number }}</td>
                        <td class="px-4 py-3">{{ row.payload.order_number }}</td>
                        <td class="px-4 py-3">{{ row.payload.item_name }}</td>
                        <td class="px-4 py-3"><Badge :tone="row.status === 'ready' ? 'success' : 'warning'">{{ row.status.replace('_', ' ') }}</Badge></td>
                        <td class="px-4 py-3">{{ row.matched_mapping?.name ?? 'No match' }}</td>
                        <td class="px-4 py-3 text-sm text-[#4c4546]">{{ row.errors?.join(', ') || '-' }}</td>
                    </tr>
                </tbody>
            </DataTable>
        </Card>
    </div>
</template>
