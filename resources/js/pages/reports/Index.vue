<script setup lang="ts">
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { FileText } from 'lucide-vue-next';

interface VisitRow {
    id: number;
    completed_on: string | null;
    pool: string | null;
    customer: string | null;
    agent: string | null;
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
    agent: string | null;
    completed_on: string | null;
    notes: string | null;
    reading: Reading | null;
    treatments: { name: string; amount: number; unit: string }[];
    tasks: { name: string; done: boolean }[];
}

const props = defineProps<{
    visits: { data: VisitRow[]; total: number };
    selected: VisitDetail | null;
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
</script>

<template>
    <Head title="Reports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-xl font-semibold">Reports</h1>
                <p class="text-sm text-muted-foreground">{{ props.visits.total }} completed visits</p>
            </div>

            <div class="overflow-hidden rounded-xl border border-border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Date</th>
                            <th class="px-4 py-2 font-medium">Pool</th>
                            <th class="hidden px-4 py-2 font-medium md:table-cell">Customer</th>
                            <th class="hidden px-4 py-2 font-medium lg:table-cell">Agent</th>
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
                            <td class="px-4 py-2.5">{{ visit.pool }}</td>
                            <td class="hidden px-4 py-2.5 text-muted-foreground md:table-cell">{{ visit.customer }}</td>
                            <td class="hidden px-4 py-2.5 text-muted-foreground lg:table-cell">{{ visit.agent }}</td>
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

            <Sheet :open="props.selected !== null" @update:open="(o: boolean) => !o && closeDrawer()">
                <SheetContent class="w-full overflow-y-auto sm:max-w-md">
                    <template v-if="props.selected">
                        <SheetHeader>
                            <SheetTitle>{{ props.selected.pool }}</SheetTitle>
                            <SheetDescription>{{ props.selected.customer }} · {{ props.selected.completed_on }} · {{ props.selected.agent }}</SheetDescription>
                        </SheetHeader>

                        <div class="mt-4 space-y-5 text-sm">
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
                                        <span>{{ t.name }}</span><span>{{ t.amount }} {{ t.unit }}</span>
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
                    </template>
                </SheetContent>
            </Sheet>
        </div>
    </AppLayout>
</template>
