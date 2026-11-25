<script setup lang="ts">
import EntityAvatar from '@/components/EntityAvatar.vue';
import ListTable from '@/components/ListTable.vue';
import MasterDetail from '@/components/MasterDetail.vue';
import SortableTh from '@/components/SortableTh.vue';
import EquipmentForms from '@/components/pools/EquipmentForms.vue';
import PlanForm from '@/components/pools/PlanForm.vue';
import PoolForm from '@/components/pools/PoolForm.vue';
import PoolPane, { type PoolFormRequest } from '@/components/pools/PoolPane.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useListSearch } from '@/composables/useListSearch';
import AppLayout from '@/layouts/AppLayout.vue';
import { POOL_TYPES } from '@/lib/labels';
import { visitQuery } from '@/lib/listQuery';
import { type BreadcrumbItem } from '@/types';
import { type Paginated } from '@/types/pagination';
import { type Option, type PoolDetail, type PoolRow, type ServiceType } from '@/types/pools';
import { Head } from '@inertiajs/vue3';
import { Plus, Waves } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    pools: Paginated<PoolRow>;
    selected: PoolDetail | null;
    filters: { search: string };
    sort: { key: string; dir: string };
    customers: Option[];
    serviceTypes: ServiceType[];
    agents: Option[];
    canManage: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Pools', href: '/pools' }];

const { search } = useListSearch(props.filters.search);

const openPool = (id: number) => visitQuery({ search: search.value || undefined, selected: id });
const closeDrawer = () => visitQuery({ search: search.value || undefined, selected: undefined });

// The detail pane is shared: it hosts one form at a time (a new pool, or one of the
// selected pool's forms), otherwise the selected pool's detail. Closing the pane
// cancels an open form first; with no form open it deselects the pool.
const form = ref<PoolFormRequest | { kind: 'new' } | null>(null);
const paneKey = computed(() => (form.value ? `form-${form.value.kind}` : (props.selected?.id ?? null)));
const closePane = () => (form.value ? (form.value = null) : closeDrawer());
</script>

<template>
    <Head title="Pools" />

    <AppLayout :breadcrumbs="breadcrumbs" :meta="`${props.pools.total} total`">
        <template #actions>
            <Input v-model="search" type="search" placeholder="Search pools…" class="h-9 min-w-0 flex-1 sm:w-44 sm:flex-none lg:w-56" />
            <Button v-if="props.canManage" size="sm" title="New pool" @click="form = { kind: 'new' }">
                <Plus class="size-4 sm:mr-1" /><span class="hidden sm:inline">Pool</span>
            </Button>
        </template>

        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <MasterDetail :has-selection="paneKey !== null" :selection-key="paneKey" empty-text="Select a pool to see details." @close="closePane">
                <template #list>
                    <ListTable
                        :meta="props.pools"
                        :columns="6"
                        :row-key="(p) => p.id"
                        :selected-key="props.selected?.id ?? null"
                        @select="(p) => openPool(p.id)"
                    >
                        <template #head>
                            <th class="w-8 px-4 py-2"><span class="sr-only">Health</span></th>
                            <SortableTh sort-key="name" :active="props.sort">Name</SortableTh>
                            <SortableTh sort-key="customer" :active="props.sort">Customer</SortableTh>
                            <SortableTh sort-key="type" :active="props.sort" class="hidden @2xl:table-cell">Type</SortableTh>
                            <th class="hidden px-4 py-2 font-medium @md:table-cell">Cadence</th>
                            <th class="hidden px-4 py-2 font-medium @xl:table-cell">Agent</th>
                        </template>
                        <template #row="{ item }">
                            <td class="px-4 py-2.5">
                                <span
                                    class="inline-block size-2.5 rounded-full"
                                    :class="
                                        item.health
                                            ? {
                                                  'bg-emerald-500': item.health.color === 'green',
                                                  'bg-amber-500': item.health.color === 'amber',
                                                  'bg-red-500': item.health.color === 'red',
                                              }
                                            : 'bg-muted'
                                    "
                                    :title="item.health?.label ?? 'No readings yet'"
                                />
                            </td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2.5">
                                    <EntityAvatar :src="item.photo_url" type="pool" :name="item.name" size="sm" />
                                    <span class="font-medium">{{ item.name }}</span>
                                </div>
                            </td>
                            <td class="text-muted-foreground px-4 py-2.5">
                                <div class="flex items-center gap-2">
                                    <EntityAvatar :src="item.customer_photo" type="person" :name="item.customer" size="sm" shape="circle" />
                                    <span>{{ item.customer }}</span>
                                </div>
                            </td>
                            <td class="text-muted-foreground hidden px-4 py-2.5 @2xl:table-cell">
                                {{ POOL_TYPES[item.type] ?? item.type }}
                            </td>
                            <td class="text-muted-foreground hidden px-4 py-2.5 @md:table-cell">{{ item.cadence ?? '—' }}</td>
                            <td class="text-muted-foreground hidden px-4 py-2.5 @xl:table-cell">
                                <div v-if="item.agent" class="flex items-center gap-2">
                                    <EntityAvatar :src="item.agent_photo" type="person" :name="item.agent" size="sm" shape="circle" />
                                    <span>{{ item.agent }}</span>
                                </div>
                                <span v-else>—</span>
                            </td>
                        </template>
                        <template #empty>
                            <Waves class="mx-auto mb-2 size-6 opacity-50" />
                            No pools yet.
                        </template>
                    </ListTable>
                </template>

                <template #detail>
                    <!-- Forms are hosted in the docked pane, not an overlay. -->
                    <PoolForm
                        v-if="form?.kind === 'new' || form?.kind === 'pool'"
                        :pool="form.kind === 'pool' ? props.selected : null"
                        :customers="props.customers"
                        :service-types="props.serviceTypes"
                        :agents="props.agents"
                        @close="form = null"
                    />
                    <template v-else-if="form && props.selected">
                        <PlanForm
                            v-if="form.kind === 'plan'"
                            :pool-id="props.selected.id"
                            :plan="form.plan"
                            :service-types="props.serviceTypes"
                            :agents="props.agents"
                            @close="form = null"
                        />
                        <EquipmentForms
                            v-else
                            :pool="props.selected"
                            :kind="form.kind"
                            :equipment-id="form.kind === 'service' ? form.equipmentId : undefined"
                            @close="form = null"
                        />
                    </template>
                    <PoolPane
                        v-else-if="props.selected"
                        :pool="props.selected"
                        :can-manage="props.canManage"
                        @open="(f) => (form = f)"
                        @gone="closeDrawer"
                    />
                </template>
            </MasterDetail>
        </div>
    </AppLayout>
</template>
