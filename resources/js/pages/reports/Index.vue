<script setup lang="ts">
import EntityAvatar from '@/components/EntityAvatar.vue';
import ListTable from '@/components/ListTable.vue';
import MasterDetail from '@/components/MasterDetail.vue';
import SortableTh from '@/components/SortableTh.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { agentLink, customerLink } from '@/lib/links';
import { type BreadcrumbItem } from '@/types';
import { type Paginated } from '@/types/pagination';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { FileText, Pencil, Plus, X } from 'lucide-vue-next';
import { ref } from 'vue';

interface VisitRow {
    id: number;
    completed_on: string | null;
    pool: string | null;
    pool_photo: string | null;
    customer: string | null;
    customer_photo: string | null;
    agent: string | null;
    agent_photo: string | null;
}

interface Reading {
    free_chlorine: number | null;
    total_chlorine: number | null;
    ph: number | null;
    alkalinity: number | null;
    calcium_hardness: number | null;
    cyanuric_acid: number | null;
    salt: number | null;
    water_temperature: number | null;
    lsi_score: number | null;
}

interface VisitDetail {
    id: number;
    pool: string | null;
    customer: string | null;
    customer_id: number | null;
    agent: string | null;
    agent_id: number | null;
    completed_on: string | null;
    notes: string | null;
    reading: Reading | null;
    treatments: { name: string; amount: number; unit: string }[];
    tasks: { name: string; done: boolean }[];
    can_edit?: boolean;
}

const props = defineProps<{
    visits: Paginated<VisitRow>;
    selected: VisitDetail | null;
    sort: { key: string; dir: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Reports', href: '/reports' }];

const open = (id: number) => router.get('/reports', { selected: id }, { preserveState: true, preserveScroll: true });
const closeDrawer = () => router.get('/reports', {}, { preserveState: true, preserveScroll: true });

const readingLabels: Record<keyof Reading, string> = {
    free_chlorine: 'Free Cl',
    total_chlorine: 'Total Cl',
    ph: 'pH',
    alkalinity: 'Alkalinity',
    calcium_hardness: 'Calcium',
    cyanuric_acid: 'CYA',
    salt: 'Salt',
    water_temperature: 'Temp °F',
    lsi_score: 'LSI',
};
const readingKeys = Object.keys(readingLabels) as (keyof Reading)[];

// --- edit ---
const editReadingKeys = [
    'free_chlorine',
    'total_chlorine',
    'ph',
    'alkalinity',
    'calcium_hardness',
    'cyanuric_acid',
    'salt',
    'water_temperature',
] as const;

const editing = ref(false);
const form = useForm<{
    completed_on: string;
    notes: string;
    reading: Record<string, number | string | null>;
    treatments: { name: string; amount: number | string; unit: string }[];
    tasks: { name: string; done: boolean }[];
}>({
    completed_on: '',
    notes: '',
    reading: {},
    treatments: [],
    tasks: [],
});

function openEdit() {
    const s = props.selected;
    if (!s) return;
    form.completed_on = s.completed_on ?? '';
    form.notes = s.notes ?? '';
    form.reading = Object.fromEntries(editReadingKeys.map((k) => [k, s.reading?.[k] ?? null]));
    form.treatments = s.treatments.map((t) => ({ ...t }));
    form.tasks = s.tasks.map((t) => ({ ...t }));
    form.clearErrors();
    editing.value = true;
}

function submitEdit() {
    if (!props.selected) return;
    form.patch(`/reports/${props.selected.id}`, {
        preserveScroll: true,
        onSuccess: () => (editing.value = false),
    });
}
</script>

<template>
    <Head title="Reports" />

    <AppLayout :breadcrumbs="breadcrumbs" :meta="`${props.visits.total} completed visits`">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <MasterDetail
                :has-selection="props.selected !== null"
                :selection-key="props.selected?.id ?? null"
                empty-text="Select a visit to see details."
                @close="closeDrawer"
            >
                <template #list>
                    <ListTable
                        :meta="props.visits"
                        :columns="4"
                        :row-key="(v) => v.id"
                        :selected-key="props.selected?.id ?? null"
                        @select="(v) => open(v.id)"
                    >
                        <template #head>
                            <SortableTh sort-key="date" :active="props.sort">Date</SortableTh>
                            <SortableTh sort-key="pool" :active="props.sort">Pool</SortableTh>
                            <SortableTh sort-key="customer" :active="props.sort" class="hidden md:table-cell">Customer</SortableTh>
                            <SortableTh sort-key="agent" :active="props.sort" class="hidden lg:table-cell">Agent</SortableTh>
                        </template>
                        <template #row="{ item }">
                            <td class="px-4 py-2.5 font-medium">{{ item.completed_on }}</td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2">
                                    <EntityAvatar :src="item.pool_photo" type="pool" :name="item.pool" size="sm" />
                                    <span>{{ item.pool }}</span>
                                </div>
                            </td>
                            <td class="hidden px-4 py-2.5 text-muted-foreground md:table-cell">
                                <div class="flex items-center gap-2">
                                    <EntityAvatar :src="item.customer_photo" type="person" :name="item.customer" size="sm" shape="circle" />
                                    <span>{{ item.customer }}</span>
                                </div>
                            </td>
                            <td class="hidden px-4 py-2.5 text-muted-foreground lg:table-cell">
                                <div class="flex items-center gap-2">
                                    <EntityAvatar :src="item.agent_photo" type="person" :name="item.agent" size="sm" shape="circle" />
                                    <span>{{ item.agent }}</span>
                                </div>
                            </td>
                        </template>
                        <template #empty>
                            <FileText class="mx-auto mb-2 size-6 opacity-50" />
                            No completed visits yet.
                        </template>
                    </ListTable>
                </template>

                <template #detail>
                    <div v-if="props.selected">
                        <div class="mb-4 flex items-start justify-between gap-2">
                            <div>
                                <h2 class="text-lg font-semibold">{{ props.selected.pool }}</h2>
                                <p class="text-sm text-muted-foreground">{{ props.selected.completed_on }}</p>
                            </div>
                            <Button v-if="props.selected.can_edit && !editing" size="sm" variant="outline" @click="openEdit">
                                <Pencil class="mr-1 size-3.5" /> Edit
                            </Button>
                        </div>

                        <!-- edit form -->
                        <form v-if="editing" class="space-y-4 text-sm" @submit.prevent="submitEdit">
                            <div class="grid gap-1.5">
                                <Label for="r_date">Date</Label>
                                <Input id="r_date" v-model="form.completed_on" type="date" />
                            </div>
                            <div>
                                <span class="text-xs font-medium text-muted-foreground">Readings</span>
                                <div class="mt-1 grid grid-cols-3 gap-2">
                                    <div v-for="k in editReadingKeys" :key="k" class="grid gap-1">
                                        <Label :for="`r_${k}`" class="text-xs">{{ readingLabels[k] }}</Label>
                                        <Input :id="`r_${k}`" v-model="form.reading[k]" type="number" step="any" />
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div class="mb-1 flex items-center justify-between">
                                    <span class="text-xs font-medium text-muted-foreground">Treatments</span>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        @click="form.treatments.push({ name: '', amount: '', unit: '' })"
                                    >
                                        <Plus class="mr-1 size-3.5" /> Add
                                    </Button>
                                </div>
                                <div class="space-y-1.5">
                                    <div v-for="(t, i) in form.treatments" :key="i" class="flex gap-2">
                                        <Input v-model="t.name" placeholder="Chemical" class="flex-1" />
                                        <Input v-model="t.amount" type="number" step="any" placeholder="Amt" class="w-16" />
                                        <Input v-model="t.unit" placeholder="Unit" class="w-16" />
                                        <Button type="button" size="icon" variant="outline" @click="form.treatments.splice(i, 1)"
                                            ><X class="size-4"
                                        /></Button>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div class="mb-1 flex items-center justify-between">
                                    <span class="text-xs font-medium text-muted-foreground">Tasks</span>
                                    <Button type="button" size="sm" variant="outline" @click="form.tasks.push({ name: '', done: false })">
                                        <Plus class="mr-1 size-3.5" /> Add
                                    </Button>
                                </div>
                                <div class="space-y-1.5">
                                    <div v-for="(t, i) in form.tasks" :key="i" class="flex items-center gap-2">
                                        <input v-model="t.done" type="checkbox" />
                                        <Input v-model="t.name" placeholder="Task" class="flex-1" />
                                        <Button type="button" size="icon" variant="outline" @click="form.tasks.splice(i, 1)"
                                            ><X class="size-4"
                                        /></Button>
                                    </div>
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="r_notes">Notes</Label>
                                <textarea
                                    id="r_notes"
                                    v-model="form.notes"
                                    rows="3"
                                    class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                                ></textarea>
                            </div>
                            <div class="flex justify-end gap-2 pt-2">
                                <Button type="button" variant="outline" @click="editing = false">Cancel</Button>
                                <Button type="submit" :disabled="form.processing">Save</Button>
                            </div>
                        </form>

                        <div v-else class="space-y-5 text-sm">
                            <dl class="grid grid-cols-2 gap-3">
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Customer</dt>
                                    <dd class="mt-0.5">
                                        <Link
                                            v-if="props.selected.customer_id"
                                            :href="customerLink(props.selected.customer_id)"
                                            class="text-primary hover:underline"
                                            >{{ props.selected.customer }}</Link
                                        >
                                        <template v-else>{{ props.selected.customer ?? '—' }}</template>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Agent</dt>
                                    <dd class="mt-0.5">
                                        <Link
                                            v-if="props.selected.agent_id"
                                            :href="agentLink(props.selected.agent_id)"
                                            class="text-primary hover:underline"
                                            >{{ props.selected.agent }}</Link
                                        >
                                        <template v-else>{{ props.selected.agent ?? '—' }}</template>
                                    </dd>
                                </div>
                            </dl>
                            <section v-if="props.selected.reading">
                                <h3 class="mb-1 font-medium">Readings</h3>
                                <dl class="grid grid-cols-3 gap-2 text-muted-foreground">
                                    <template v-for="k in readingKeys" :key="k">
                                        <div v-if="props.selected.reading[k] !== null">
                                            <dt class="text-xs">{{ readingLabels[k] }}</dt>
                                            <dd>{{ props.selected.reading[k] }}</dd>
                                        </div>
                                    </template>
                                </dl>
                            </section>

                            <section v-if="props.selected.treatments.length">
                                <h3 class="mb-1 font-medium">Treatments</h3>
                                <ul class="space-y-1 text-muted-foreground">
                                    <li v-for="(t, i) in props.selected.treatments" :key="i" class="flex justify-between">
                                        <span>{{ t.name }}</span
                                        ><span>{{ t.amount }} {{ t.unit }}</span>
                                    </li>
                                </ul>
                            </section>

                            <section v-if="props.selected.tasks.length">
                                <h3 class="mb-1 font-medium">Tasks</h3>
                                <ul class="space-y-0.5 text-muted-foreground">
                                    <li v-for="(t, i) in props.selected.tasks" :key="i">{{ t.done ? '✓' : '○' }} {{ t.name }}</li>
                                </ul>
                            </section>

                            <section v-if="props.selected.notes">
                                <h3 class="mb-1 font-medium">Notes</h3>
                                <p class="text-muted-foreground">{{ props.selected.notes }}</p>
                            </section>
                        </div>
                    </div>
                </template>
            </MasterDetail>
        </div>
    </AppLayout>
</template>
