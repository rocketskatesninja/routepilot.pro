<script setup lang="ts">
import EntityAvatar from '@/components/EntityAvatar.vue';
import MasterDetail from '@/components/MasterDetail.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { requestForPoolLink } from '@/lib/links';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarPlus, Droplets } from 'lucide-vue-next';

interface VisitRow {
    id: number;
    pool: string | null;
    pool_photo: string | null;
    on: string | null;
}

interface VisitDetail {
    id: number;
    pool: string | null;
    pool_id: number | null;
    on: string | null;
    agent: string | null;
    notes: string | null;
    reading: {
        free_chlorine: number | null;
        ph: number | null;
        alkalinity: number | null;
        calcium_hardness: number | null;
        cyanuric_acid: number | null;
        salt: number | null;
        lsi_score: number | null;
    } | null;
    treatments: { name: string; amount: number; unit: string }[];
    tasks: { name: string; done: boolean }[];
    photos: string[];
}

const props = defineProps<{
    visits: { data: VisitRow[]; total: number };
    selected: VisitDetail | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Service History', href: '/history' }];

const open = (id: number) => router.get('/history', { selected: id }, { preserveState: true, preserveScroll: true });
const closeDrawer = () => router.get('/history', {}, { preserveState: true, preserveScroll: true });

const readingRows = (r: NonNullable<VisitDetail['reading']>) => [
    { label: 'Free chlorine', value: r.free_chlorine, suffix: ' ppm' },
    { label: 'pH', value: r.ph, suffix: '' },
    { label: 'Alkalinity', value: r.alkalinity, suffix: ' ppm' },
    { label: 'Calcium', value: r.calcium_hardness, suffix: ' ppm' },
    { label: 'CYA', value: r.cyanuric_acid, suffix: ' ppm' },
    { label: 'Salt', value: r.salt, suffix: ' ppm' },
];
</script>

<template>
    <Head title="Service History" />

    <AppLayout :breadcrumbs="breadcrumbs" :meta="`${props.visits.total} completed visits`">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <MasterDetail
                :has-selection="props.selected !== null"
                :selection-key="props.selected?.id ?? null"
                empty-text="Select a visit to see details."
                @close="closeDrawer"
            >
                <template #list>
                    <div class="overflow-hidden rounded-xl border border-border">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-2 font-medium">Date</th>
                                    <th class="px-4 py-2 font-medium">Pool</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="v in props.visits.data"
                                    :key="v.id"
                                    class="cursor-pointer border-t border-border transition-colors hover:bg-muted/40"
                                    :class="{ 'bg-muted/60': props.selected?.id === v.id }"
                                    @click="open(v.id)"
                                >
                                    <td class="px-4 py-2.5 font-medium">{{ v.on }}</td>
                                    <td class="px-4 py-2.5 text-muted-foreground">
                                        <div class="flex items-center gap-2">
                                            <EntityAvatar :src="v.pool_photo" type="pool" :name="v.pool" size="sm" />
                                            <span>{{ v.pool }}</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="props.visits.data.length === 0">
                                    <td colspan="2" class="px-4 py-10 text-center text-muted-foreground">
                                        <Droplets class="mx-auto mb-2 size-6 opacity-50" />
                                        No visits yet.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>

                <template #detail>
                    <div v-if="props.selected">
                        <div class="mb-4">
                            <h2 class="text-lg font-semibold">{{ props.selected.pool }}</h2>
                            <p class="text-sm text-muted-foreground">
                                {{ props.selected.on }}<template v-if="props.selected.agent"> · {{ props.selected.agent }}</template>
                            </p>
                            <Link
                                v-if="props.selected.pool_id"
                                :href="requestForPoolLink(props.selected.pool_id)"
                                class="mt-2 inline-flex items-center gap-1 text-sm text-primary hover:underline"
                            >
                                <CalendarPlus class="size-3.5" /> Request service for this pool
                            </Link>
                        </div>

                        <div class="space-y-5 text-sm">
                            <section v-if="props.selected.reading">
                                <h3 class="mb-2 font-medium">Water chemistry</h3>
                                <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-muted-foreground">
                                    <div v-for="row in readingRows(props.selected.reading)" :key="row.label" class="flex justify-between">
                                        <dt>{{ row.label }}</dt>
                                        <dd>{{ row.value !== null ? row.value + row.suffix : '—' }}</dd>
                                    </div>
                                    <div
                                        v-if="props.selected.reading.lsi_score !== null"
                                        class="col-span-2 mt-1 flex justify-between border-t border-border pt-1 font-medium text-foreground"
                                    >
                                        <dt>Water balance (LSI)</dt>
                                        <dd>{{ props.selected.reading.lsi_score }}</dd>
                                    </div>
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
                                <h3 class="mb-1 font-medium">Service checklist</h3>
                                <ul class="space-y-1 text-muted-foreground">
                                    <li v-for="(t, i) in props.selected.tasks" :key="i" class="flex items-center gap-2">
                                        <span :class="t.done ? 'text-emerald-600 dark:text-emerald-400' : ''">{{ t.done ? '✓' : '○' }}</span>
                                        {{ t.name }}
                                    </li>
                                </ul>
                            </section>

                            <section v-if="props.selected.notes">
                                <h3 class="mb-1 font-medium">Notes</h3>
                                <p class="text-muted-foreground">{{ props.selected.notes }}</p>
                            </section>

                            <section v-if="props.selected.photos.length">
                                <h3 class="mb-1 font-medium">Photos</h3>
                                <div class="grid grid-cols-3 gap-2">
                                    <a v-for="(photo, i) in props.selected.photos" :key="i" :href="photo" target="_blank">
                                        <img
                                            :src="photo"
                                            alt="Service photo"
                                            class="aspect-square w-full rounded-md border border-border object-cover"
                                        />
                                    </a>
                                </div>
                            </section>
                        </div>
                    </div>
                </template>
            </MasterDetail>
        </div>
    </AppLayout>
</template>
