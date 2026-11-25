<script setup lang="ts">
import EmailComposer from '@/components/EmailComposer.vue';
import EntityAvatar from '@/components/EntityAvatar.vue';
import ListTable from '@/components/ListTable.vue';
import MasterDetail from '@/components/MasterDetail.vue';
import SortableTh from '@/components/SortableTh.vue';
import AgentForm from '@/components/people/AgentForm.vue';
import AgentPane from '@/components/people/AgentPane.vue';
import CustomerForm from '@/components/people/CustomerForm.vue';
import CustomerPane from '@/components/people/CustomerPane.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { useCustomerBilling } from '@/composables/useCustomerBilling';
import { useEmailComposer, type Audience } from '@/composables/useEmailComposer';
import { useListSearch } from '@/composables/useListSearch';
import AppLayout from '@/layouts/AppLayout.vue';
import { visitQuery } from '@/lib/listQuery';
import { formatMoney } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { type Paginated } from '@/types/pagination';
import { type AgentDetail, type CustomerDetail, type PersonRow } from '@/types/people';
import { Head, usePage } from '@inertiajs/vue3';
import { Download, FileSpreadsheet, FileText, HardHat, Mail, UserPlus, Users } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    people: Paginated<PersonRow>;
    counts: { all: number; customers: number; agents: number; archived: number };
    selected: CustomerDetail | AgentDetail | null;
    filters: { search: string; type: 'all' | 'customers' | 'agents' | 'archived' };
    sort: { key: string; dir: string };
    canManage: boolean;
    canEmail: boolean;
    audiences: Audience[];
    /** The company signature (cleaned HTML) the email form can add; null for RoutePilot's own broadcasts. */
    signature: string | null;
    recent: {
        id: number;
        subject: string;
        audience: string;
        recipients: number;
        sent: number;
        failed: number;
        sent_on: string | null;
        files: string[];
    }[];
}>();

const billing = useCustomerBilling();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'People', href: '/people' }];

const keyFor = (p: PersonRow) => `${p.person_type}:${p.id}`;
const fullName = (p: PersonRow) => `${p.first_name} ${p.last_name ?? ''}`.trim();
const selectedKey = computed(() => (props.selected ? `${props.selected.type}-${props.selected.id}` : null));
const customer = computed(() => (props.selected?.type === 'customer' ? props.selected : null));
const agent = computed(() => (props.selected?.type === 'agent' ? props.selected : null));

// --- the pane: one form at a time (customer, agent or email), else the selected person ---
const form = ref<'new-customer' | 'edit-customer' | 'new-agent' | 'edit-agent' | null>(null);
const email = useEmailComposer(
    () => props.audiences,
    () => props.people.data.map(keyFor),
);
// Below the docked layout the email form covers the list; "Choose people" tucks it away
// so the boxes can be ticked, and the header's Email button brings it back.
const picking = ref(false);
function openEmail(keys?: string[]) {
    form.value = null;
    picking.value = false;
    email.start(keys);
}
function closeEmail() {
    picking.value = false;
    email.close();
}
function backToEmail() {
    picking.value = false;
    if (email.picked.length) email.form.audience = 'selected';
}
const toggleEmail = () => (!email.open ? openEmail() : picking.value ? backToEmail() : closeEmail());
// Sent: the form has put itself away.
watch(
    () => email.open,
    (open) => !open && (picking.value = false),
);
function openForm(which: NonNullable<typeof form.value>) {
    if (email.open) closeEmail();
    form.value = which;
}
const paneKey = computed(() => form.value ?? (email.open ? 'email' : selectedKey.value));
function closePane() {
    if (form.value) form.value = null;
    else if (email.open) closeEmail();
    else closeDrawer();
}

// A deep link (?compose=customer:5) opens the email panel pre-targeted at one
// person — used by the "email" links in other detail panes. Fires on fresh
// mounts and same-page navigations alike (page.url is reactive).
const page = usePage();
watch(
    () => page.url,
    (url) => {
        const compose = new URL(url, 'http://localhost').searchParams.get('compose');
        if (compose && /^(customer|agent):\d+$/.test(compose)) openEmail([compose]);
    },
    { immediate: true },
);

// Export: the list exactly as shown — same tab, search and sort, every page.
function exportUrl(format: 'csv' | 'pdf') {
    const q = new URLSearchParams({ format, sort: props.sort.key, dir: props.sort.dir });
    if (props.filters.type !== 'all') q.set('type', props.filters.type);
    if (props.filters.search) q.set('search', props.filters.search);
    return `/people/export?${q}`;
}

const tabs = [
    { key: 'all', label: 'All' },
    { key: 'customers', label: 'Customers' },
    { key: 'agents', label: 'Agents' },
    { key: 'archived', label: 'Archived' },
] as const;

const { search } = useListSearch(props.filters.search, { extra: () => ({ type: tab() }) });

const tab = () => (props.filters.type === 'all' ? undefined : props.filters.type);

// Switching tabs keeps the search and sort; selection and page reset.
function setType(type: 'all' | 'customers' | 'agents' | 'archived') {
    visitQuery(
        {
            search: search.value || undefined,
            type: type === 'all' ? undefined : type,
            selected: undefined,
            selected_type: undefined,
            page: undefined,
        },
        { replace: true },
    );
}

function openPerson(person: PersonRow) {
    visitQuery({ search: search.value || undefined, type: tab(), selected: person.id, selected_type: person.person_type });
}

function closeDrawer() {
    visitQuery({ search: search.value || undefined, type: tab(), selected: undefined, selected_type: undefined });
}
</script>

<template>
    <Head title="People" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <template #filters>
            <button
                v-for="t in tabs"
                :key="t.key"
                class="shrink-0 rounded-md px-2.5 py-1 text-sm font-medium whitespace-nowrap transition-colors"
                :class="props.filters.type === t.key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'"
                @click="setType(t.key)"
            >
                {{ t.label }} <span class="opacity-70">{{ props.counts[t.key] }}</span>
            </button>
        </template>

        <template #actions>
            <Input v-model="search" type="search" placeholder="Search people…" class="h-9 min-w-0 flex-1 sm:w-40 sm:flex-none lg:w-52" />
            <Button v-if="props.canManage" size="sm" title="New customer" @click="openForm('new-customer')">
                <UserPlus class="size-4 sm:mr-1" /><span class="hidden sm:inline">Customer</span>
            </Button>
            <Button v-if="props.canManage" size="sm" variant="outline" title="New agent" @click="openForm('new-agent')">
                <HardHat class="size-4 sm:mr-1" /><span class="hidden sm:inline">Agent</span>
            </Button>
            <Button
                v-if="props.canEmail"
                size="sm"
                :variant="email.open ? 'default' : 'outline'"
                :aria-pressed="email.open"
                :title="picking ? 'Back to the email form' : email.open ? 'Close the email form' : 'Email people'"
                @click="toggleEmail"
            >
                <Mail class="size-4 sm:mr-1" />
                <!-- While choosing, say how to get back even when the crowded header drops its words. -->
                <span v-if="picking" class="ml-1">Back to email</span>
                <span v-else class="hidden sm:inline">Email</span>
                <span v-if="email.picked.length" class="ml-1">({{ email.picked.length }})</span>
            </Button>
            <DropdownMenu v-if="props.canManage">
                <DropdownMenuTrigger as-child>
                    <Button size="sm" variant="outline" title="Export this list">
                        <Download class="size-4 sm:mr-1" /><span class="hidden sm:inline">Export</span>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem as-child>
                        <a :href="exportUrl('csv')"><FileSpreadsheet class="mr-2 size-4" /> Spreadsheet (CSV)</a>
                    </DropdownMenuItem>
                    <DropdownMenuItem as-child>
                        <a :href="exportUrl('pdf')"><FileText class="mr-2 size-4" /> PDF</a>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </template>

        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <MasterDetail
                :has-selection="paneKey !== null"
                :selection-key="paneKey"
                empty-text="Select a person to see details."
                :pane-open="!picking"
                @close="closePane"
            >
                <template #list>
                    <ListTable
                        :meta="props.people"
                        :columns="email.open ? 8 : 7"
                        :row-key="(p) => `${p.person_type}-${p.id}`"
                        :selected-key="selectedKey"
                        @select="openPerson"
                    >
                        <template #head>
                            <th v-if="email.open" class="w-10 px-4 py-2">
                                <input type="checkbox" :checked="email.allOnPage" aria-label="Select all" @change="email.toggleAll()" />
                            </th>
                            <SortableTh sort-key="name" :active="props.sort">Name</SortableTh>
                            <SortableTh sort-key="type" :active="props.sort">Type</SortableTh>
                            <SortableTh sort-key="email" :active="props.sort" class="hidden @xl:table-cell">Email</SortableTh>
                            <SortableTh sort-key="phone" :active="props.sort" class="hidden @2xl:table-cell">Phone</SortableTh>
                            <th class="hidden px-4 py-2 font-medium @4xl:table-cell">Portal</th>
                            <th class="hidden px-4 py-2 font-medium @3xl:table-cell">Last visit</th>
                            <th v-if="billing" class="px-4 py-2 text-right font-medium">Balance</th>
                        </template>
                        <template #row="{ item: person }">
                            <td v-if="email.open" class="px-4 py-2.5" @click.stop>
                                <input
                                    type="checkbox"
                                    :checked="email.isPicked(keyFor(person))"
                                    :aria-label="`Select ${fullName(person)}`"
                                    @change="email.toggle(keyFor(person))"
                                />
                            </td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2.5">
                                    <EntityAvatar :src="person.photo_url" type="person" :name="fullName(person)" size="sm" shape="circle" />
                                    <span class="font-medium">{{ fullName(person) }}</span>
                                </div>
                            </td>
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
                            <td class="text-muted-foreground hidden px-4 py-2.5 @xl:table-cell">{{ person.email ?? '—' }}</td>
                            <td class="text-muted-foreground hidden px-4 py-2.5 @2xl:table-cell">
                                {{ person.phone ?? '—' }}
                            </td>
                            <td class="hidden px-4 py-2.5 @4xl:table-cell">
                                <template v-if="person.person_type === 'customer'">
                                    <span
                                        v-if="person.portal_status === 'active'"
                                        class="inline-flex rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400"
                                        >Yes</span
                                    >
                                    <span
                                        v-else-if="person.portal_status === 'revoked'"
                                        class="inline-flex rounded-full bg-amber-500/15 px-2 py-0.5 text-xs font-medium text-amber-600 dark:text-amber-400"
                                        >Revoked</span
                                    >
                                    <span v-else class="text-muted-foreground text-xs">No</span>
                                </template>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="text-muted-foreground hidden px-4 py-2.5 @3xl:table-cell">
                                {{ person.last_visit ?? '—' }}
                            </td>
                            <td
                                v-if="billing"
                                class="px-4 py-2.5 text-right font-medium"
                                :class="person.balance && person.balance > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'"
                            >
                                {{ person.balance != null ? formatMoney(person.balance) : '—' }}
                            </td>
                        </template>
                        <template #empty>
                            <Users class="mx-auto mb-2 size-6 opacity-50" />
                            No people yet.
                        </template>
                    </ListTable>
                </template>

                <template #detail="{ docked }">
                    <!-- Forms are hosted in the docked pane, not an overlay. -->
                    <CustomerForm
                        v-if="form === 'new-customer' || form === 'edit-customer'"
                        :customer="form === 'edit-customer' ? customer : null"
                        @close="form = null"
                    />
                    <AgentForm v-else-if="form" :agent="form === 'edit-agent' ? agent : null" @close="form = null" />
                    <EmailComposer
                        v-else-if="email.open"
                        :composer="email"
                        summary="Send to a whole group at once. Customers who opted out won’t get it."
                        :signature="props.signature"
                        :recent="props.recent"
                        :covering="!docked"
                        @choose="picking = true"
                        @cancel="closeEmail"
                    />
                    <CustomerPane
                        v-else-if="customer"
                        :customer="customer"
                        :can-manage="props.canManage"
                        @edit="openForm('edit-customer')"
                        @gone="closeDrawer"
                    />
                    <AgentPane v-else-if="agent" :agent="agent" :can-manage="props.canManage" @edit="openForm('edit-agent')" @gone="closeDrawer" />
                </template>
            </MasterDetail>
        </div>
    </AppLayout>
</template>
