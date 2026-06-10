<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ClipboardList } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface ServiceRow {
    id: number;
    name: string;
    category: string | null;
    frequency: string;
    price: string;
    pools: number;
    is_active: boolean;
}

interface ServiceDetail {
    id: number;
    name: string;
    category: string | null;
    frequency: string;
    duration_minutes: number;
    price: string;
    chemicals_included: boolean;
    description: string | null;
    modules: string[];
    tasks: string[];
    pools: number;
    is_active: boolean;
}

interface Paginated<T> {
    data: T[];
    total: number;
}

const props = defineProps<{
    services: Paginated<ServiceRow>;
    selected: ServiceDetail | null;
    filters: { search: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Services', href: '/services' }];

const search = ref(props.filters.search);
let timer: ReturnType<typeof setTimeout> | undefined;
watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get('/services', { search: value || undefined }, { preserveState: true, replace: true, preserveScroll: true }), 300);
});

function open(id: number) {
    router.get('/services', { search: search.value || undefined, selected: id }, { preserveState: true, preserveScroll: true });
}

function closeDrawer() {
    router.get('/services', { search: search.value || undefined }, { preserveState: true, preserveScroll: true });
}

const money = (price: string) => `$${Number(price).toFixed(2)}`;
</script>

<template>
    <Head title="Services" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold">Services</h1>
                    <p class="text-sm text-muted-foreground">Service-type catalog</p>
                </div>
                <Input v-model="search" type="search" placeholder="Search services…" class="max-w-xs" />
            </div>

            <div class="overflow-hidden rounded-xl border border-border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Name</th>
                            <th class="hidden px-4 py-2 font-medium md:table-cell">Category</th>
                            <th class="px-4 py-2 font-medium">Price</th>
                            <th class="hidden px-4 py-2 font-medium md:table-cell">Pools</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="service in props.services.data"
                            :key="service.id"
                            class="cursor-pointer border-t border-border transition-colors hover:bg-muted/40"
                            :class="{ 'bg-muted/60': props.selected?.id === service.id }"
                            @click="open(service.id)"
                        >
                            <td class="px-4 py-2.5 font-medium">{{ service.name }}</td>
                            <td class="hidden px-4 py-2.5 capitalize text-muted-foreground md:table-cell">{{ service.category ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-muted-foreground">{{ money(service.price) }}</td>
                            <td class="hidden px-4 py-2.5 text-muted-foreground md:table-cell">{{ service.pools }}</td>
                            <td class="px-4 py-2.5">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="service.is_active ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-muted text-muted-foreground'"
                                >
                                    {{ service.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="props.services.data.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">
                                <ClipboardList class="mx-auto mb-2 size-6 opacity-50" />
                                No service types yet.
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
                            <SheetDescription class="capitalize">{{ props.selected.category ?? 'Service' }} · {{ props.selected.frequency }}</SheetDescription>
                        </SheetHeader>

                        <div class="mt-4 space-y-5 text-sm">
                            <section>
                                <dl class="space-y-1 text-muted-foreground">
                                    <div class="flex justify-between"><dt>Price</dt><dd>{{ money(props.selected.price) }}</dd></div>
                                    <div class="flex justify-between"><dt>Duration</dt><dd>{{ props.selected.duration_minutes }} min</dd></div>
                                    <div class="flex justify-between"><dt>Chemicals</dt><dd>{{ props.selected.chemicals_included ? 'Included' : 'Billed separately' }}</dd></div>
                                    <div class="flex justify-between"><dt>Active pools</dt><dd>{{ props.selected.pools }}</dd></div>
                                </dl>
                                <p v-if="props.selected.description" class="mt-2 text-muted-foreground">{{ props.selected.description }}</p>
                            </section>

                            <section>
                                <h3 class="mb-1 font-medium">Field steps</h3>
                                <div class="flex flex-wrap gap-1.5">
                                    <span v-for="m in props.selected.modules" :key="m" class="rounded-md bg-muted px-2 py-0.5 text-xs">{{ m }}</span>
                                </div>
                            </section>

                            <section v-if="props.selected.tasks.length">
                                <h3 class="mb-1 font-medium">Task checklist</h3>
                                <ul class="list-inside list-disc space-y-0.5 text-muted-foreground">
                                    <li v-for="(task, i) in props.selected.tasks" :key="i">{{ task }}</li>
                                </ul>
                            </section>
                        </div>
                    </template>
                </SheetContent>
            </Sheet>
        </div>
    </AppLayout>
</template>
