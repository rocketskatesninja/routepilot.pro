<script setup lang="ts">
import LeadPane from '@/components/inbox/LeadPane.vue';
import RequestPane from '@/components/inbox/RequestPane.vue';
import ListTable from '@/components/ListTable.vue';
import MasterDetail from '@/components/MasterDetail.vue';
import Chip from '@/components/pane/Chip.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { visitQuery } from '@/lib/listQuery';
import { formatDay } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { INBOX_TONE, LEAD_SOURCES, LEAD_STATUSES, type LeadDetail, type LeadRow, type RequestDetail, type RequestRow } from '@/types/inbox';
import { type Paginated } from '@/types/pagination';
import { Head, router } from '@inertiajs/vue3';
import { Inbox, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    tab: 'requests' | 'leads';
    status: 'open' | 'closed';
    rows: Paginated<RequestRow | LeadRow>;
    selected: RequestDetail | LeadDetail | null;
    counts: { requests: number; leads: number };
    agents: { id: number; name: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Requests', href: '/inbox' }];

const requestRows = computed(() => props.rows as Paginated<RequestRow>);
const leadRows = computed(() => props.rows as Paginated<LeadRow>);
const request = computed(() => (props.tab === 'requests' ? (props.selected as RequestDetail | null) : null));
const lead = computed(() => (props.tab === 'leads' ? (props.selected as LeadDetail | null) : null));

// The URL keeps only what differs from the defaults (requests tab, "To do").
const visit = (tab: string, status: string, selected?: number, keep = true) =>
    router.get(
        '/inbox',
        { tab: tab === 'leads' ? tab : undefined, status: status === 'closed' ? status : undefined, selected },
        { preserveState: keep, preserveScroll: keep },
    );
const setTab = (tab: string) => visit(tab, 'open', undefined, false);
const setStatus = (status: string) => visit(props.tab, status);
const open = (id: number) => visitQuery({ selected: id });
// Esc / the pane's close button: an open form first, then the pane itself.
const pane = ref<{ closeForm: () => boolean } | null>(null);
const closePane = () => {
    if (!pane.value?.closeForm()) visitQuery({ selected: undefined });
};

// Deleting: the ticked rows (this tab, this page) from the header, or the open one from its pane.
const ticked = ref<(string | number)[]>([]);
watch(
    () => [props.tab, props.status, props.rows.current_page],
    () => (ticked.value = []),
);
function remove(ids: (string | number)[]) {
    const noun = props.tab === 'leads' ? 'lead' : 'request';
    const what = ids.length === 1 ? `this ${noun}` : `these ${ids.length} ${noun}s`;
    if (!confirm(`Delete ${what}? This can’t be undone.`)) return;
    router.delete(props.tab === 'leads' ? '/leads' : '/requests', {
        data: { ids },
        preserveScroll: true,
        onSuccess: () => (ticked.value = ticked.value.filter((k) => !ids.includes(k))),
    });
}

const tabs = computed(() => [
    { key: 'requests', label: 'Customer requests', count: props.counts.requests },
    { key: 'leads', label: 'Website leads', count: props.counts.leads },
]);
</script>

<template>
    <Head title="Requests" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <template #filters>
            <button
                v-for="t in tabs"
                :key="t.key"
                class="shrink-0 rounded-md px-2.5 py-1 text-sm font-medium whitespace-nowrap transition-colors"
                :class="props.tab === t.key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'"
                @click="setTab(t.key)"
            >
                {{ t.label }} <span v-if="t.count" class="opacity-70">{{ t.count }}</span>
            </button>
        </template>

        <template #actions>
            <Button v-if="ticked.length" size="sm" variant="outline" class="text-red-600 hover:text-red-600 dark:text-red-400" @click="remove(ticked)"
                ><Trash2 class="mr-1 size-3.5" /> Delete {{ ticked.length }} selected</Button
            >
            <div class="border-input flex rounded-md border p-0.5">
                <button
                    v-for="s in ['open', 'closed']"
                    :key="s"
                    class="rounded px-2.5 py-1 text-sm font-medium capitalize transition-colors"
                    :class="props.status === s ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
                    @click="setStatus(s)"
                >
                    {{ s === 'open' ? 'To do' : 'Done' }}
                </button>
            </div>
        </template>

        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <MasterDetail
                :has-selection="props.selected !== null"
                :selection-key="props.selected ? `${props.tab}-${props.selected.id}` : null"
                :empty-text="props.tab === 'leads' ? 'Select a lead to see what they asked for.' : 'Select a request to answer it.'"
                @close="closePane"
            >
                <template #list>
                    <!-- customer requests -->
                    <ListTable
                        v-if="props.tab === 'requests'"
                        :meta="requestRows"
                        :columns="4"
                        v-model:checked="ticked"
                        :row-key="(r) => r.id"
                        :selected-key="props.selected?.id ?? null"
                        @select="(r) => open(r.id)"
                    >
                        <template #head>
                            <th class="px-4 py-2 text-left font-medium">Request</th>
                            <th class="px-4 py-2 text-left font-medium">Customer</th>
                            <th class="hidden px-4 py-2 text-left font-medium @md:table-cell">Received</th>
                            <th class="px-4 py-2 text-left font-medium">Status</th>
                        </template>
                        <template #row="{ item: r }">
                            <td class="px-4 py-2.5 font-medium">{{ r.title }}</td>
                            <td class="text-muted-foreground px-4 py-2.5">
                                {{ r.customer }}<span v-if="r.pool" class="hidden lg:inline"> · {{ r.pool }}</span>
                            </td>
                            <td class="text-muted-foreground hidden px-4 py-2.5 whitespace-nowrap @md:table-cell">{{ formatDay(r.on) }}</td>
                            <td class="px-4 py-2.5">
                                <Chip :tone="INBOX_TONE[r.status] ?? 'muted'">{{ r.status_label }}</Chip>
                            </td>
                        </template>
                        <template #empty>
                            <Inbox class="mx-auto mb-2 size-6 opacity-50" />
                            {{
                                props.status === 'open'
                                    ? 'Nothing waiting — customers request services from their portal.'
                                    : 'No answered requests yet.'
                            }}
                        </template>
                    </ListTable>

                    <!-- website leads -->
                    <ListTable
                        v-else
                        :meta="leadRows"
                        :columns="4"
                        v-model:checked="ticked"
                        :row-key="(l) => l.id"
                        :selected-key="props.selected?.id ?? null"
                        @select="(l) => open(l.id)"
                    >
                        <template #head>
                            <th class="px-4 py-2 text-left font-medium">Name</th>
                            <th class="px-4 py-2 text-left font-medium">Asked for</th>
                            <th class="hidden px-4 py-2 text-left font-medium @md:table-cell">Received</th>
                            <th class="px-4 py-2 text-left font-medium">Status</th>
                        </template>
                        <template #row="{ item: l }">
                            <td class="px-4 py-2.5">
                                <div class="font-medium">{{ l.name }}</div>
                                <div class="text-muted-foreground text-xs">{{ l.contact ?? '—' }}</div>
                            </td>
                            <td class="text-muted-foreground px-4 py-2.5">{{ l.service ?? LEAD_SOURCES[l.source] ?? l.source }}</td>
                            <td class="text-muted-foreground hidden px-4 py-2.5 whitespace-nowrap @md:table-cell">{{ formatDay(l.on) }}</td>
                            <td class="px-4 py-2.5">
                                <Chip :tone="INBOX_TONE[l.status] ?? 'muted'">{{ LEAD_STATUSES[l.status] ?? l.status }}</Chip>
                            </td>
                        </template>
                        <template #empty>
                            <Inbox class="mx-auto mb-2 size-6 opacity-50" />
                            {{ props.status === 'open' ? 'No new leads — they arrive from your website.' : 'No converted or archived leads yet.' }}
                        </template>
                    </ListTable>
                </template>

                <template #detail>
                    <!-- ===== a customer request ===== -->
                    <RequestPane
                        v-if="request"
                        ref="pane"
                        :key="`r${request.id}`"
                        :request="request"
                        :agents="props.agents"
                        @remove="remove([request.id])"
                    />
                    <LeadPane v-else-if="lead" ref="pane" :key="`l${lead.id}`" :lead="lead" @remove="remove([lead.id])" />
                </template>
            </MasterDetail>
        </div>
    </AppLayout>
</template>
