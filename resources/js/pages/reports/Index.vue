<script setup lang="ts">
import EntityAvatar from '@/components/EntityAvatar.vue';
import MasterDetail from '@/components/MasterDetail.vue';
import Pagination from '@/components/Pagination.vue';
import SortableTh from '@/components/SortableTh.vue';
import { useFitRows } from '@/composables/useFitRows';
import AppLayout from '@/layouts/AppLayout.vue';
import { agentLink, customerLink } from '@/lib/links';
import { type BreadcrumbItem } from '@/types';
import { type Paginated } from '@/types/pagination';
import { Head, Link, router } from '@inertiajs/vue3';
import { FileText } from 'lucide-vue-next';

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
}

const props = defineProps<{
    visits: Paginated<VisitRow>;
    selected: VisitDetail | null;
    sort: { key: string; dir: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Reports', href: '/reports' }];
const { listRef } = useFitRows(
    () => props.visits.per_page,
    () => props.visits.total,
);

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
                    <div class="flex min-h-0 flex-col gap-3">
                        <div ref="listRef" class="overflow-hidden rounded-xl border border-border">
                            <table class="w-full text-sm">
                                <thead class="bg-muted/50 text-left text-muted-foreground">
                                    <tr>
                                        <SortableTh sort-key="date" :active="props.sort">Date</SortableTh>
                                        <SortableTh sort-key="pool" :active="props.sort">Pool</SortableTh>
                                        <SortableTh sort-key="customer" :active="props.sort" class="hidden md:table-cell">Customer</SortableTh>
                                        <SortableTh sort-key="agent" :active="props.sort" class="hidden lg:table-cell">Agent</SortableTh>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="visit in props.visits.data"
                                        :key="visit.id"
                                        class="cursor-pointer border-t border-border transition-colors hover:bg-muted/40"
                                        :class="{ 'bg-muted/60': props.selected?.id === visit.id }"
                                        @click="open(visit.id)"
                                    >
                                        <td class="px-4 py-2.5 font-medium">{{ visit.completed_on }}</td>
                                        <td class="px-4 py-2.5">
                                            <div class="flex items-center gap-2">
                                                <EntityAvatar :src="visit.pool_photo" type="pool" :name="visit.pool" size="sm" />
                                                <span>{{ visit.pool }}</span>
                                            </div>
                                        </td>
                                        <td class="hidden px-4 py-2.5 text-muted-foreground md:table-cell">
                                            <div class="flex items-center gap-2">
                                                <EntityAvatar
                                                    :src="visit.customer_photo"
                                                    type="person"
                                                    :name="visit.customer"
                                                    size="sm"
                                                    shape="circle"
                                                />
                                                <span>{{ visit.customer }}</span>
                                            </div>
                                        </td>
                                        <td class="hidden px-4 py-2.5 text-muted-foreground lg:table-cell">
                                            <div class="flex items-center gap-2">
                                                <EntityAvatar :src="visit.agent_photo" type="person" :name="visit.agent" size="sm" shape="circle" />
                                                <span>{{ visit.agent }}</span>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="props.visits.data.length === 0">
                                        <td colspan="4" class="px-4 py-10 text-center text-muted-foreground">
                                            <FileText class="mx-auto mb-2 size-6 opacity-50" />
                                            No completed visits yet.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <Pagination :meta="props.visits" />
                    </div>
                </template>

                <template #detail>
                    <div v-if="props.selected">
                        <div class="mb-4">
                            <h2 class="text-lg font-semibold">{{ props.selected.pool }}</h2>
                            <p class="text-sm text-muted-foreground">{{ props.selected.completed_on }}</p>
                        </div>

                        <div class="space-y-5 text-sm">
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
