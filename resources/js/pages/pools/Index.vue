<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Waves } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface PoolRow {
    id: number;
    name: string;
    type: string;
    sanitizer: string;
    customer: string;
    city: string | null;
    cadence: string | null;
    agent: string | null;
}

interface Health {
    status: string;
    color: 'red' | 'amber' | 'green';
    label: string;
    description: string;
}

interface PoolDetail {
    id: number;
    name: string;
    type: string;
    volume_gallons: number | null;
    sanitizer: string;
    filter: string | null;
    equipment: string[];
    customer: { name: string; email: string | null; phone: string | null };
    location: { city: string | null; gate_code: string | null; access_notes: string | null } | null;
    subscriptions: { id: number; schedule: string; agent: string }[];
    latest_reading: {
        taken_on: string | null;
        free_chlorine: number | null;
        ph: number | null;
        alkalinity: number | null;
        health: Health | null;
    } | null;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    pools: Paginated<PoolRow>;
    selected: PoolDetail | null;
    filters: { search: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Pools', href: '/pools' }];

const search = ref(props.filters.search);
let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get('/pools', { search: value || undefined }, { preserveState: true, replace: true, preserveScroll: true });
    }, 300);
});

function openPool(id: number) {
    router.get('/pools', { search: search.value || undefined, selected: id }, { preserveState: true, preserveScroll: true });
}

function closeDrawer() {
    router.get('/pools', { search: search.value || undefined }, { preserveState: true, preserveScroll: true });
}

const healthClasses: Record<Health['color'], string> = {
    green: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    amber: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
    red: 'bg-red-500/15 text-red-600 dark:text-red-400',
};
</script>

<template>
    <Head title="Pools" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold">Pools</h1>
                    <p class="text-sm text-muted-foreground">{{ props.pools.total }} total</p>
                </div>
                <Input v-model="search" type="search" placeholder="Search pools…" class="max-w-xs" />
            </div>

            <div class="overflow-hidden rounded-xl border border-border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Name</th>
                            <th class="px-4 py-2 font-medium">Customer</th>
                            <th class="hidden px-4 py-2 font-medium md:table-cell">Type</th>
                            <th class="hidden px-4 py-2 font-medium md:table-cell">Cadence</th>
                            <th class="hidden px-4 py-2 font-medium lg:table-cell">Agent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="pool in props.pools.data"
                            :key="pool.id"
                            class="cursor-pointer border-t border-border transition-colors hover:bg-muted/40"
                            :class="{ 'bg-muted/60': props.selected?.id === pool.id }"
                            @click="openPool(pool.id)"
                        >
                            <td class="px-4 py-2.5 font-medium">{{ pool.name }}</td>
                            <td class="px-4 py-2.5 text-muted-foreground">{{ pool.customer }}</td>
                            <td class="hidden px-4 py-2.5 capitalize text-muted-foreground md:table-cell">{{ pool.type.replace('_', ' ') }}</td>
                            <td class="hidden px-4 py-2.5 text-muted-foreground md:table-cell">{{ pool.cadence ?? '—' }}</td>
                            <td class="hidden px-4 py-2.5 text-muted-foreground lg:table-cell">{{ pool.agent ?? '—' }}</td>
                        </tr>
                        <tr v-if="props.pools.data.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">
                                <Waves class="mx-auto mb-2 size-6 opacity-50" />
                                No pools yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Sheet :open="props.selected !== null" @update:open="(open: boolean) => !open && closeDrawer()">
                <SheetContent class="w-full overflow-y-auto sm:max-w-md">
                    <template v-if="props.selected">
                        <SheetHeader>
                            <SheetTitle>{{ props.selected.name }}</SheetTitle>
                            <SheetDescription>{{ props.selected.customer.name }}</SheetDescription>
                        </SheetHeader>

                        <div class="mt-4 space-y-5 text-sm">
                            <section v-if="props.selected.latest_reading?.health" class="rounded-lg border border-border p-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="healthClasses[props.selected.latest_reading.health.color]"
                                >
                                    {{ props.selected.latest_reading.health.label }}
                                </span>
                                <dl class="mt-2 grid grid-cols-3 gap-2 text-muted-foreground">
                                    <div>
                                        <dt class="text-xs">Free Cl</dt>
                                        <dd>{{ props.selected.latest_reading.free_chlorine ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs">pH</dt>
                                        <dd>{{ props.selected.latest_reading.ph ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs">Alk</dt>
                                        <dd>{{ props.selected.latest_reading.alkalinity ?? '—' }}</dd>
                                    </div>
                                </dl>
                            </section>

                            <section>
                                <h3 class="mb-1 font-medium">Specs</h3>
                                <dl class="space-y-1 text-muted-foreground">
                                    <div class="flex justify-between">
                                        <dt>Type</dt>
                                        <dd class="capitalize">{{ props.selected.type.replace('_', ' ') }}</dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt>Volume</dt>
                                        <dd>{{ props.selected.volume_gallons ? props.selected.volume_gallons.toLocaleString() + ' gal' : '—' }}</dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt>Sanitizer</dt>
                                        <dd class="capitalize">{{ props.selected.sanitizer }}</dd>
                                    </div>
                                    <div v-if="props.selected.equipment.length" class="flex justify-between">
                                        <dt>Equipment</dt>
                                        <dd>{{ props.selected.equipment.join(', ') }}</dd>
                                    </div>
                                </dl>
                            </section>

                            <section v-if="props.selected.location">
                                <h3 class="mb-1 font-medium">Location</h3>
                                <dl class="space-y-1 text-muted-foreground">
                                    <div class="flex justify-between">
                                        <dt>City</dt>
                                        <dd>{{ props.selected.location.city ?? '—' }}</dd>
                                    </div>
                                    <div v-if="props.selected.location.gate_code" class="flex justify-between">
                                        <dt>Gate code</dt>
                                        <dd class="font-mono">{{ props.selected.location.gate_code }}</dd>
                                    </div>
                                    <p v-if="props.selected.location.access_notes" class="pt-1 text-xs italic">
                                        {{ props.selected.location.access_notes }}
                                    </p>
                                </dl>
                            </section>

                            <section v-if="props.selected.subscriptions.length">
                                <h3 class="mb-1 font-medium">Service</h3>
                                <ul class="space-y-1 text-muted-foreground">
                                    <li v-for="sub in props.selected.subscriptions" :key="sub.id" class="flex justify-between">
                                        <span>{{ sub.schedule }}</span
                                        ><span>{{ sub.agent }}</span>
                                    </li>
                                </ul>
                            </section>
                        </div>
                    </template>
                </SheetContent>
            </Sheet>
        </div>
    </AppLayout>
</template>
