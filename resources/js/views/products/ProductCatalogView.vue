<script setup lang="ts">
import { Boxes, FileText, PackagePlus, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FileDropzone from '@/components/ui/FileDropzone.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import DataTable from '@/components/ui/Table.vue';
import Textarea from '@/components/ui/Textarea.vue';
import { money } from '@/lib/utils';
import { usePortalStore } from '@/stores/portal';
import type { ProductType } from '@/types/portal';

const store = usePortalStore();
const selectedTypeId = ref('');
const selectedVariantId = ref('');
const selectedOptionId = ref('');
const uploadMessage = ref('');

const typeForm = reactive({
    name: '',
    code: '',
    description: '',
    image_url: '',
});

const variantForm = reactive({
    name: '',
    sku: '',
    layout: 'Horizontal',
    panel_count: 1,
    price_cents: 0,
    image_sizes: '',
    panel_sizes: '',
    template_url: '',
});

const optionForm = reactive({
    group: 'Print Options',
    name: '',
    code: '',
    price_cents: 0,
});

const selectedType = computed(() => store.productTypes.find((type) => String(type.id) === selectedTypeId.value) ?? store.productTypes[0] ?? null);
const selectedVariant = computed(() => selectedType.value?.variants.find((variant) => String(variant.id) === selectedVariantId.value) ?? null);
const selectedOption = computed(() => selectedType.value?.options.find((option) => String(option.id) === selectedOptionId.value) ?? null);

watch(selectedType, (type) => {
    if (!type) {
        resetTypeForm();
        return;
    }

    selectedTypeId.value = String(type.id);
    typeForm.name = type.name;
    typeForm.code = type.code;
    typeForm.description = type.description ?? '';
    typeForm.image_url = type.image_url ?? '';
}, { immediate: true });

watch(selectedVariant, (variant) => {
    if (!variant) {
        resetVariantForm();
        return;
    }

    variantForm.name = variant.name;
    variantForm.sku = variant.sku;
    variantForm.layout = variant.layout ?? '';
    variantForm.panel_count = variant.panel_count;
    variantForm.price_cents = variant.price_cents;
    variantForm.image_sizes = joinList(variant.image_sizes);
    variantForm.panel_sizes = joinList(variant.panel_sizes);
    variantForm.template_url = variant.template_url ?? '';
});

watch(selectedOption, (option) => {
    if (!option) {
        resetOptionForm();
        return;
    }

    optionForm.group = option.group;
    optionForm.name = option.name;
    optionForm.code = option.code;
    optionForm.price_cents = option.price_cents;
});

function joinList(value: string[] | null) {
    return value?.join(', ') ?? '';
}

function splitList(value: string) {
    return value.split(',').map((item) => item.trim()).filter(Boolean);
}

function resetTypeForm() {
    selectedTypeId.value = '';
    typeForm.name = '';
    typeForm.code = '';
    typeForm.description = '';
    typeForm.image_url = '';
}

function resetVariantForm() {
    selectedVariantId.value = '';
    variantForm.name = '';
    variantForm.sku = '';
    variantForm.layout = 'Horizontal';
    variantForm.panel_count = 1;
    variantForm.price_cents = 0;
    variantForm.image_sizes = '';
    variantForm.panel_sizes = '';
    variantForm.template_url = '';
}

function resetOptionForm() {
    selectedOptionId.value = '';
    optionForm.group = 'Print Options';
    optionForm.name = '';
    optionForm.code = '';
    optionForm.price_cents = 0;
}

function typePayload() {
    return {
        name: typeForm.name,
        code: typeForm.code,
        description: typeForm.description || null,
        image_url: typeForm.image_url || null,
    };
}

function variantPayload() {
    return {
        name: variantForm.name,
        sku: variantForm.sku,
        layout: variantForm.layout || null,
        panel_count: Number(variantForm.panel_count),
        price_cents: Number(variantForm.price_cents),
        image_sizes: splitList(variantForm.image_sizes),
        panel_sizes: splitList(variantForm.panel_sizes),
        template_url: variantForm.template_url || null,
    };
}

function optionPayload() {
    return {
        group: optionForm.group,
        name: optionForm.name,
        code: optionForm.code,
        price_cents: Number(optionForm.price_cents),
    };
}

async function saveType() {
    if (selectedType.value && selectedTypeId.value) {
        await store.updateProductType(selectedType.value, typePayload());
    } else {
        await store.createProductType(typePayload());
    }
}

async function deleteType() {
    if (!selectedType.value) {
        return;
    }

    await store.deleteProductType(selectedType.value);
    resetTypeForm();
}

async function saveVariant() {
    if (!selectedType.value) {
        return;
    }

    if (selectedVariant.value) {
        await store.updateProductVariant(selectedVariant.value.id, variantPayload());
    } else {
        await store.createProductVariant(selectedType.value, variantPayload());
    }
}

async function deleteVariant() {
    if (!selectedVariant.value) {
        return;
    }

    await store.deleteProductVariant(selectedVariant.value.id);
    resetVariantForm();
}

async function saveOption() {
    if (!selectedType.value) {
        return;
    }

    if (selectedOption.value) {
        await store.updateProductOption(selectedOption.value.id, optionPayload());
    } else {
        await store.createProductOption(selectedType.value, optionPayload());
    }
}

async function deleteOption() {
    if (!selectedOption.value) {
        return;
    }

    await store.deleteProductOption(selectedOption.value.id);
    resetOptionForm();
}

async function uploadProductImage(file: File) {
    const media = await store.uploadFile(file, 'product_image');
    typeForm.image_url = media.url;
    uploadMessage.value = `${media.original_name} uploaded with checksum ${media.checksum.slice(0, 10)}...`;
}

async function uploadTemplate(file: File) {
    const media = await store.uploadFile(file, 'template');
    variantForm.template_url = media.url;
    uploadMessage.value = `${media.original_name} uploaded with checksum ${media.checksum.slice(0, 10)}...`;
}
</script>

<template>
    <div class="grid gap-5">
        <div class="flex flex-col justify-between gap-4 xl:flex-row xl:items-end">
            <div>
                <h2 class="text-2xl font-bold text-slate-950">Product Catalog</h2>
                <p class="text-slate-600">Manage product types, variants, option pricing, templates, and catalog media.</p>
            </div>
            <Button variant="outline" @click="resetTypeForm"><Plus class="h-4 w-4" /> New product type</Button>
        </div>

        <div class="grid gap-5 xl:grid-cols-[360px_1fr]">
            <Card>
                <div class="mb-4 flex items-center gap-2">
                    <Boxes class="h-5 w-5 text-teal-700" />
                    <h3 class="text-lg font-bold text-slate-950">Catalog Types</h3>
                </div>

                <div class="grid gap-3">
                    <button
                        v-for="type in store.productTypes"
                        :key="type.id"
                        :class="[
                            'rounded-md border p-3 text-left transition',
                            selectedType?.id === type.id ? 'border-teal-300 bg-teal-50/70' : 'border-slate-200 hover:border-teal-200 hover:bg-slate-50',
                        ]"
                        @click="selectedTypeId = String(type.id)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-bold text-slate-950">{{ type.name }}</p>
                                <p class="text-sm text-slate-500">{{ type.code }}</p>
                            </div>
                            <Badge tone="info">{{ type.variants.length }} variants</Badge>
                        </div>
                    </button>
                    <EmptyState v-if="store.productTypes.length === 0" title="No catalog types" description="Create the first product type to begin catalog setup." :icon="Boxes" />
                </div>
            </Card>

            <div class="grid gap-5">
                <Card>
                    <div class="mb-4 flex items-center gap-2">
                        <Pencil class="h-5 w-5 text-teal-700" />
                        <h3 class="text-lg font-bold text-slate-950">Product Type</h3>
                    </div>
                    <div class="grid gap-4 lg:grid-cols-[1fr_280px]">
                        <div class="grid gap-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <Input v-model="typeForm.name" label="Name" required placeholder="Stretched Canvas" />
                                <Input v-model="typeForm.code" label="Code" required placeholder="CANVAS" />
                            </div>
                            <Textarea v-model="typeForm.description" label="Description" :rows="4" placeholder="Production description and catalog notes" />
                            <Input v-model="typeForm.image_url" label="Image URL" placeholder="https://..." />
                            <p v-if="uploadMessage" class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700">{{ uploadMessage }}</p>
                            <div class="flex flex-wrap gap-2">
                                <Button :disabled="!typeForm.name || !typeForm.code" @click="saveType">{{ selectedTypeId ? 'Update type' : 'Create type' }}</Button>
                                <Button v-if="selectedTypeId" variant="destructive" @click="deleteType"><Trash2 class="h-4 w-4" /> Delete type</Button>
                            </div>
                        </div>
                        <div class="grid gap-3">
                            <img v-if="typeForm.image_url" :src="typeForm.image_url" :alt="typeForm.name" class="aspect-[4/3] rounded-md border border-slate-200 object-cover">
                            <FileDropzone label="Upload product image" description="JPEG, PNG, WebP; stored with checksum and scan state." accept="image/*" @selected="uploadProductImage" />
                        </div>
                    </div>
                </Card>

                <div v-if="selectedType" class="grid gap-5 2xl:grid-cols-2">
                    <Card>
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <PackagePlus class="h-5 w-5 text-teal-700" />
                                <h3 class="text-lg font-bold text-slate-950">Variants</h3>
                            </div>
                            <Button variant="outline" size="sm" @click="resetVariantForm"><Plus class="h-4 w-4" /> New variant</Button>
                        </div>

                        <DataTable min-width="760px">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr><th class="px-4 py-3">SKU</th><th class="px-4 py-3">Layout</th><th class="px-4 py-3">Panels</th><th class="px-4 py-3">Price</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <tr v-for="variant in selectedType.variants" :key="variant.id" class="cursor-pointer hover:bg-slate-50" @click="selectedVariantId = String(variant.id)">
                                    <td class="px-4 py-3"><p class="font-semibold text-slate-950">{{ variant.sku }}</p><p class="text-slate-500">{{ variant.name }}</p></td>
                                    <td class="px-4 py-3">{{ variant.layout ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ variant.panel_count }}</td>
                                    <td class="px-4 py-3">{{ money(variant.price_cents) }}</td>
                                </tr>
                            </tbody>
                        </DataTable>

                        <div class="mt-5 grid gap-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <Input v-model="variantForm.name" label="Variant name" required />
                                <Input v-model="variantForm.sku" label="SKU" required />
                                <Input v-model="variantForm.layout" label="Layout" />
                                <Input v-model="variantForm.panel_count" label="Panel count" type="number" />
                                <Input v-model="variantForm.price_cents" label="Price cents" type="number" />
                                <Input v-model="variantForm.template_url" label="Template URL" />
                            </div>
                            <Textarea v-model="variantForm.image_sizes" label="Image sizes" help="Comma separated values, e.g. 36&quot; X 24&quot;" />
                            <Textarea v-model="variantForm.panel_sizes" label="Panel sizes" help="Comma separated production panels." />
                            <FileDropzone label="Upload template PDF" description="Template files are stored in media_files and linked to this variant form." accept=".pdf" @selected="uploadTemplate" />
                            <div class="flex flex-wrap gap-2">
                                <Button :disabled="!variantForm.name || !variantForm.sku" @click="saveVariant">{{ selectedVariant ? 'Update variant' : 'Create variant' }}</Button>
                                <Button v-if="selectedVariant" variant="destructive" @click="deleteVariant"><Trash2 class="h-4 w-4" /> Delete variant</Button>
                            </div>
                        </div>
                    </Card>

                    <Card>
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <FileText class="h-5 w-5 text-teal-700" />
                                <h3 class="text-lg font-bold text-slate-950">Options</h3>
                            </div>
                            <Button variant="outline" size="sm" @click="resetOptionForm"><Plus class="h-4 w-4" /> New option</Button>
                        </div>

                        <DataTable min-width="680px">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr><th class="px-4 py-3">Group</th><th class="px-4 py-3">Name</th><th class="px-4 py-3">Code</th><th class="px-4 py-3">Price</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <tr v-for="option in selectedType.options" :key="option.id" class="cursor-pointer hover:bg-slate-50" @click="selectedOptionId = String(option.id)">
                                    <td class="px-4 py-3">{{ option.group }}</td>
                                    <td class="px-4 py-3 font-semibold text-slate-950">{{ option.name }}</td>
                                    <td class="px-4 py-3">{{ option.code }}</td>
                                    <td class="px-4 py-3">{{ money(option.price_cents) }}</td>
                                </tr>
                            </tbody>
                        </DataTable>

                        <div class="mt-5 grid gap-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <Select v-model="optionForm.group" label="Group">
                                    <option>Print Options</option>
                                    <option>Hanging Options</option>
                                    <option>Frame Options</option>
                                    <option>Packaging</option>
                                </Select>
                                <Input v-model="optionForm.name" label="Name" required />
                                <Input v-model="optionForm.code" label="Code" required />
                                <Input v-model="optionForm.price_cents" label="Price cents" type="number" />
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button :disabled="!optionForm.name || !optionForm.code" @click="saveOption">{{ selectedOption ? 'Update option' : 'Create option' }}</Button>
                                <Button v-if="selectedOption" variant="destructive" @click="deleteOption"><Trash2 class="h-4 w-4" /> Delete option</Button>
                            </div>
                        </div>
                    </Card>
                </div>
            </div>
        </div>
    </div>
</template>
