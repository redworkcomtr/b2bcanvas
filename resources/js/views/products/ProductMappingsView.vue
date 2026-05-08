<script setup lang="ts">
import { AlertTriangle, CheckCircle2, PlayCircle, Plus, RefreshCcw, SlidersHorizontal, Trash2 } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import DataTable from '@/components/ui/Table.vue';
import { usePortalStore, ApiError } from '@/stores/portal';
import type { MappingRule, MappingSimulation, ProductMapping } from '@/types/portal';

type RuleDraft = {
    field: MappingRule['field'];
    operator: MappingRule['operator'];
    value: string;
    priority: number;
};

const store = usePortalStore();
const query = ref('');
const selectedMappingId = ref('');
const saving = ref(false);
const statusMessage = ref('');
const errorMessage = ref('');
const conflicts = ref<Array<{ id: number; name: string; rules: MappingRule[] }>>([]);
const simulation = ref<MappingSimulation | null>(null);
const simulator = reactive({
    item_name: 'Framed Art Print-Black / 36" x 24"',
    item_sku: 'MGC-FP-36x24_Black',
    fulfillment_sku: '',
});
const form = reactive({
    name: '',
    product_variant_id: '',
    frame: '',
    rules: [
        { field: 'sku', operator: 'contains', value: '', priority: 60 },
    ] as RuleDraft[],
});

const selectedMapping = computed(() => store.productMappings.find((mapping) => String(mapping.id) === selectedMappingId.value) ?? null);
const mappings = computed(() => store.productMappings.filter((mapping) => {
    const text = `${mapping.name} ${mapping.variant?.sku} ${mapping.rules?.map((rule) => rule.value).join(' ')}`.toLowerCase();
    return text.includes(query.value.toLowerCase());
}));
const payloadRules = computed(() => form.rules.map((rule) => ({
    field: rule.field,
    operator: rule.operator,
    value: rule.value,
    priority: Number(rule.priority),
})));
const formPayload = computed(() => ({
    product_variant_id: Number(form.product_variant_id),
    name: form.name,
    properties: form.frame ? { Frame: form.frame } : {},
    rules: payloadRules.value,
}));

watch(selectedMapping, (mapping) => {
    if (!mapping) {
        return;
    }

    hydrateForm(mapping);
});

function hydrateForm(mapping: ProductMapping) {
    form.name = mapping.name;
    form.product_variant_id = String(mapping.variant?.id ?? '');
    form.frame = mapping.properties?.Frame ?? '';
    form.rules = mapping.rules.map((rule) => ({
        field: rule.field,
        operator: rule.operator,
        value: rule.value,
        priority: rule.priority ?? 50,
    }));
}

function clearFeedback() {
    statusMessage.value = '';
    errorMessage.value = '';
    conflicts.value = [];
    simulation.value = null;
}

function selectMapping(mapping: ProductMapping) {
    selectedMappingId.value = String(mapping.id);
    clearFeedback();
}

function resetForm() {
    selectedMappingId.value = '';
    form.name = '';
    form.product_variant_id = '';
    form.frame = '';
    form.rules = [{ field: 'sku', operator: 'contains', value: '', priority: 60 }];
    clearFeedback();
}

function addRule() {
    form.rules.push({ field: 'name', operator: 'contains', value: '', priority: 40 });
}

function removeRule(index: number) {
    form.rules.splice(index, 1);
    if (form.rules.length === 0) {
        addRule();
    }
}

async function checkConflicts() {
    statusMessage.value = '';
    errorMessage.value = '';
    conflicts.value = [];

    if (form.rules.some((rule) => !rule.value)) {
        errorMessage.value = 'Add a value to every rule before checking conflicts.';
        return;
    }

    try {
        const result = await store.detectMappingConflicts({
            rules: payloadRules.value,
            exclude_mapping_id: selectedMapping.value?.id,
        });
        conflicts.value = result.conflicts;
        statusMessage.value = result.conflicts.length === 0 ? 'No duplicate rules found.' : '';
    } catch (exception) {
        if (exception instanceof ApiError) {
            errorMessage.value = exception.errors.rules?.[0] ?? exception.message;
        } else {
            errorMessage.value = 'Rules could not be checked.';
        }
    }
}

async function saveMapping() {
    if (!form.name || !form.product_variant_id || form.rules.some((rule) => !rule.value)) {
        errorMessage.value = 'Mapping name, production product, and all rule values are required.';
        return;
    }

    saving.value = true;
    statusMessage.value = '';
    errorMessage.value = '';
    conflicts.value = [];

    try {
        const result = selectedMapping.value
            ? await store.updateMapping(selectedMapping.value, formPayload.value)
            : await store.createMapping(formPayload.value);
        selectedMappingId.value = String(result.mapping.id);
        statusMessage.value = result.resolved_actions > 0
            ? `${result.resolved_actions} required action resolved automatically.`
            : 'Mapping saved successfully.';
    } catch (exception) {
        if (exception instanceof ApiError) {
            errorMessage.value = exception.errors.rules?.[0] ?? exception.message;
        } else {
            errorMessage.value = 'Mapping could not be saved.';
        }
    } finally {
        saving.value = false;
    }
}

async function deleteMapping() {
    if (!selectedMapping.value) {
        return;
    }

    await store.deleteMapping(selectedMapping.value);
    resetForm();
    statusMessage.value = 'Mapping deleted.';
}

async function runSimulator() {
    simulation.value = await store.simulateMapping(simulator);
}

function maxRulePriority(mapping: ProductMapping) {
    return Math.max(0, ...mapping.rules.map((rule) => rule.priority ?? 0));
}
</script>

<template>
    <div class="grid gap-5">
        <div class="flex flex-col justify-between gap-4 xl:flex-row xl:items-end">
            <div>
                <h2 class="text-2xl font-bold text-slate-950">Product Mappings</h2>
                <p class="text-slate-600">Map marketplace item names, SKUs, and fulfillment SKUs into production-ready variants.</p>
            </div>
            <Button variant="outline" @click="resetForm"><Plus class="h-4 w-4" /> New mapping</Button>
        </div>

        <div class="grid gap-5 2xl:grid-cols-[1fr_520px]">
            <div class="grid gap-5">
                <Card>
                    <div class="mb-4 grid gap-3 md:grid-cols-[1fr_auto]">
                        <Input v-model="query" label="Search mappings" placeholder="SKU, item name, frame, rule..." />
                        <Button variant="outline" class="self-end" @click="checkConflicts"><SlidersHorizontal class="h-4 w-4" /> Check rules</Button>
                    </div>

                    <DataTable min-width="980px">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Mapping</th>
                                <th class="px-4 py-3">Production Product</th>
                                <th class="px-4 py-3">Rules</th>
                                <th class="px-4 py-3">Priority</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr
                                v-for="mapping in mappings"
                                :key="mapping.id"
                                class="cursor-pointer bg-white hover:bg-slate-50"
                                @click="selectMapping(mapping)"
                            >
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-950">{{ mapping.name }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <Badge v-for="(value, key) in mapping.properties" :key="key" tone="neutral">{{ key }}: {{ value }}</Badge>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-teal-800">{{ mapping.variant?.sku }}</p>
                                    <p class="text-slate-500">{{ mapping.variant?.name }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="grid gap-1">
                                        <code v-for="rule in mapping.rules" :key="rule.field + rule.operator + rule.value" class="rounded bg-slate-100 px-2 py-1 text-xs text-slate-700">
                                            {{ rule.field }} {{ rule.operator }} {{ rule.value }}
                                        </code>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge tone="info">{{ maxRulePriority(mapping) }}</Badge>
                                </td>
                            </tr>
                        </tbody>
                    </DataTable>
                    <EmptyState
                        v-if="mappings.length === 0"
                        class="mt-4"
                        title="No product mappings found"
                        description="Create a mapping or clear the search filter to inspect existing rules."
                        :icon="SlidersHorizontal"
                    />
                </Card>

                <Card>
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <PlayCircle class="h-5 w-5 text-teal-700" />
                            <h3 class="text-lg font-bold text-slate-950">Rule Simulator</h3>
                        </div>
                        <Button size="sm" @click="runSimulator"><RefreshCcw class="h-4 w-4" /> Simulate</Button>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <Input v-model="simulator.item_name" label="Item name" />
                        <Input v-model="simulator.item_sku" label="Item SKU" />
                        <Input v-model="simulator.fulfillment_sku" label="Fulfillment SKU" />
                    </div>

                    <div v-if="simulation" class="mt-4 grid gap-3">
                        <Alert
                            v-if="simulation.matched_mapping"
                            tone="success"
                            title="Matched mapping"
                            :description="`${simulation.matched_mapping.name} -> ${simulation.matched_mapping.variant?.sku}`"
                        />
                        <Alert v-else tone="warning" title="No mapping matched" description="This item would create a Required Action during import." />

                        <Alert
                            v-if="simulation.conflicts.length > 1"
                            tone="warning"
                            title="Conflict detected"
                            description="Multiple mappings match this sample. Increase priority or narrow rules."
                        />

                        <div v-if="simulation.candidates.length" class="grid gap-2">
                            <div v-for="candidate in simulation.candidates" :key="candidate.mapping.id" class="rounded-md border border-slate-200 p-3">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="font-semibold text-slate-950">{{ candidate.mapping.name }}</p>
                                    <Badge tone="info">Score {{ candidate.score }}</Badge>
                                </div>
                                <p class="mt-1 text-sm text-slate-500">{{ candidate.mapping.variant?.sku }} · {{ candidate.rule_count }} rules</p>
                            </div>
                        </div>
                    </div>
                </Card>
            </div>

            <Card>
                <div class="mb-4 flex items-center gap-2">
                    <Plus class="h-5 w-5 text-teal-700" />
                    <h3 class="text-lg font-bold text-slate-950">{{ selectedMapping ? 'Edit Mapping' : 'Create Mapping' }}</h3>
                </div>

                <div class="grid gap-4">
                    <Input v-model="form.name" label="Mapping name" required placeholder="Black framed art 36x24" />
                    <Select v-model="form.product_variant_id" label="Production product" required>
                        <option value="">Select variant</option>
                        <option v-for="variant in store.variants" :key="variant.id" :value="variant.id">
                            {{ variant.sku }} · {{ variant.name }}
                        </option>
                    </Select>
                    <Input v-model="form.frame" label="Property: Frame" placeholder="Black Modern Thin" />

                    <div class="grid gap-3">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-bold text-slate-950">Rules</p>
                            <Button variant="outline" size="sm" @click="addRule"><Plus class="h-4 w-4" /> Rule</Button>
                        </div>
                        <div v-for="(rule, index) in form.rules" :key="index" class="grid gap-3 rounded-md border border-slate-200 p-3">
                            <div class="grid grid-cols-2 gap-3">
                                <Select v-model="rule.field" label="Field">
                                    <option value="sku">SKU</option>
                                    <option value="name">Name</option>
                                    <option value="fulfillment_sku">Fulfillment SKU</option>
                                </Select>
                                <Select v-model="rule.operator" label="Operator">
                                    <option value="equals">equals</option>
                                    <option value="contains">contains</option>
                                    <option value="starts_with">starts with</option>
                                    <option value="regex">regex</option>
                                </Select>
                            </div>
                            <div class="grid gap-3 md:grid-cols-[1fr_120px_auto] md:items-end">
                                <Input v-model="rule.value" label="Value" required placeholder="MGC-FP-36x24_Black" />
                                <Input v-model="rule.priority" label="Priority" type="number" />
                                <Button variant="ghost" size="icon" aria-label="Remove rule" @click="removeRule(index)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>

                    <Alert v-if="statusMessage" tone="success" title="Mapping workflow updated" :description="statusMessage" />
                    <Alert v-if="errorMessage" tone="danger" title="Mapping cannot be saved" :description="errorMessage" />
                    <Alert
                        v-if="conflicts.length"
                        tone="warning"
                        title="Duplicate rule conflict"
                        :description="`Conflicts with ${conflicts.map((conflict) => conflict.name).join(', ')}`"
                    />

                    <div class="flex flex-wrap gap-2">
                        <Button :disabled="saving" @click="saveMapping">
                            <CheckCircle2 class="h-4 w-4" />
                            {{ saving ? 'Saving...' : selectedMapping ? 'Update mapping' : 'Create mapping' }}
                        </Button>
                        <Button variant="outline" @click="checkConflicts">
                            <AlertTriangle class="h-4 w-4" />
                            Detect conflict
                        </Button>
                        <Button v-if="selectedMapping" variant="destructive" @click="deleteMapping">
                            <Trash2 class="h-4 w-4" />
                            Delete
                        </Button>
                    </div>
                </div>
            </Card>
        </div>
    </div>
</template>
