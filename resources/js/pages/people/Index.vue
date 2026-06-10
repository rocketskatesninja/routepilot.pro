<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type PersonType = 'customer' | 'agent';

interface PersonRow {
    id: number;
    person_type: PersonType;
    first_name: string;
    last_name: string | null;
    email: string | null;
    phone: string | null;
}

interface CustomerDetail {
    type: 'customer';
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    city: string | null;
    has_portal: boolean;
    pools: { id: number; name: string; type: string }[];
    recent_visits: { id: number; pool: string | null; completed_on: string | null }[];
}

interface AgentDetail {
    type: 'agent';
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    is_active: boolean;
    stats: { completed_visits: number; this_week: number };
}

interface Paginated<T> {
    data: T[];
    total: number;
}

const props = defineProps<{
    people: Paginated<PersonRow>;
    counts: { all: number; customers: number; agents: number };
    selected: CustomerDetail | AgentDetail | null;
    filters: { search: string; type: 'all' | 'customers' | 'agents' };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'People', href: '/people' }];

const tabs = [
    { key: 'all', label: 'All' },
    { key: 'customers', label: 'Customers' },
    { key: 'agents', label: 'Agents' },
] as const;

const search = ref(props.filters.search);
let timer: ReturnType<typeof setTimeout> | undefined;
watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => visit({ search: value || undefined, type: tab() }), 300);
});

const tab = () => (props.filters.type === 'all' ? undefined : props.filters.type);

function visit(params: Record<string, string | number | undefined>) {
    router.get('/people', params, { preserveState: true, replace: true, preserveScroll: true });
}

function setType(type: 'all' | 'customers' | 'agents') {
    visit({ search: search.value || undefined, type: type === 'all' ? undefined : type });
}

function openPerson(person: PersonRow) {
    router.get(
        '/people',
        { search: search.value || undefined, type: tab(), selected: person.id, selected_type: person.person_type },
        { preserveState: true, preserveScroll: true },
    );
}

function closeDrawer() {
    visit({ search: search.value || undefined, type: tab() });
}

const fullName = (p: PersonRow) => `${p.first_name} ${p.last_name ?? ''}`.trim();
const selectedKey = computed(() => (props.selected ? `${props.selected.type}-${props.selected.id}` : null));
</script>

<template>
    <Head title="People" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-xl font-semibold">People</h1>
                <Input v-model="search" type="search" placeholder="Search people…" class="max-w-xs" />
            </div>

            <div class="flex gap-1 text-sm">
                <button
                    v-for="t in tabs"
                    :key="t.key"
                    class="rounded-md px-3 py-1.5 font-medium transition-colors"
                    :class="props.filters.type === t.key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'"
                    @click="setType(t.key)"
                >
                    {{ t.label }} <span class="opacity-70">{{ props.counts[t.key] }}</span>
                </button>
            </div>

            <div class="overflow-hidden rounded-xl border border-border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Name</th>
                            <th class="px-4 py-2 font-medium">Type</th>
                            <th class="hidden px-4 py-2 font-medium md:table-cell">Email</th>
                            <th class="hidden px-4 py-2 font-medium lg:table-cell">Phone</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="person in props.people.data"
                            :key="`${person.person_type}-${person.id}`"
                            class="cursor-pointer border-t border-border transition-colors hover:bg-muted/40"
                            :class="{ 'bg-muted/60': selectedKey === `${person.person_type}-${person.id}` }"
                            @click="openPerson(person)"
                        >
                            <td class="px-4 py-2.5 font-medium">{{ fullName(person) }}</td>
                            <td class="px-4 py-2.5">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                    :class="
                                        person.person_type === 'agent'
                                            ? 'bg-sky-500/15 text-sky-600 dark:text-sky-400'
                                            : 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                    "
                                >
                                    {{ person.person_type }}
                                </span>
                            </td>
                            <td class="hidden px-4 py-2.5 text-muted-foreground md:table-cell">{{ person.email ?? '—' }}</td>
                            <td class="hidden px-4 py-2.5 text-muted-foreground lg:table-cell">{{ person.phone ?? '—' }}</td>
                        </tr>
                        <tr v-if="props.people.data.length === 0">
                            <td colspan="4" class="px-4 py-10 text-center text-muted-foreground">
                                <Users class="mx-auto mb-2 size-6 opacity-50" />
                                No people yet.
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
                            <SheetDescription class="capitalize">{{ props.selected.type }}</SheetDescription>
                        </SheetHeader>

                        <div class="mt-4 space-y-5 text-sm">
                            <section>
                                <h3 class="mb-1 font-medium">Contact</h3>
                                <dl class="space-y-1 text-muted-foreground">
                                    <div class="flex justify-between"><dt>Email</dt><dd>{{ props.selected.email ?? '—' }}</dd></div>
                                    <div class="flex justify-between"><dt>Phone</dt><dd>{{ props.selected.phone ?? '—' }}</dd></div>
                                </dl>
                            </section>

                            <template v-if="props.selected.type === 'customer'">
                                <section>
                                    <h3 class="mb-1 font-medium">Pools ({{ props.selected.pools.length }})</h3>
                                    <ul class="space-y-1 text-muted-foreground">
                                        <li v-for="pool in props.selected.pools" :key="pool.id" class="flex justify-between">
                                            <span>{{ pool.name }}</span><span class="capitalize">{{ pool.type.replace('_', ' ') }}</span>
                                        </li>
                                        <li v-if="props.selected.pools.length === 0">No pools.</li>
                                    </ul>
                                </section>
                                <section v-if="props.selected.recent_visits.length">
                                    <h3 class="mb-1 font-medium">Recent visits</h3>
                                    <ul class="space-y-1 text-muted-foreground">
                                        <li v-for="visit in props.selected.recent_visits" :key="visit.id" class="flex justify-between">
                                            <span>{{ visit.pool }}</span><span>{{ visit.completed_on }}</span>
                                        </li>
                                    </ul>
                                </section>
                            </template>

                            <template v-else>
                                <section>
                                    <h3 class="mb-1 font-medium">Activity</h3>
                                    <dl class="space-y-1 text-muted-foreground">
                                        <div class="flex justify-between"><dt>Status</dt><dd>{{ props.selected.is_active ? 'Active' : 'Inactive' }}</dd></div>
                                        <div class="flex justify-between"><dt>Completed visits</dt><dd>{{ props.selected.stats.completed_visits }}</dd></div>
                                        <div class="flex justify-between"><dt>This week</dt><dd>{{ props.selected.stats.this_week }}</dd></div>
                                    </dl>
                                </section>
                            </template>
                        </div>
                    </template>
                </SheetContent>
            </Sheet>
        </div>
    </AppLayout>
</template>
