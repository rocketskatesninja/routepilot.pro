<script setup lang="ts">
import { Select } from '@/components/ui/select';
import FormField from '@/components/FormField.vue';
import EmailComposer from '@/components/EmailComposer.vue';
import EntityAvatar from '@/components/EntityAvatar.vue';
import MasterDetail from '@/components/MasterDetail.vue';
import Chip from '@/components/pane/Chip.vue';
import IconTile from '@/components/pane/IconTile.vue';
import PaneFacts from '@/components/pane/PaneFacts.vue';
import PaneHeader from '@/components/pane/PaneHeader.vue';
import PaneSection from '@/components/pane/PaneSection.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { saveRecord } from '@/composables/useCrudForm';
import { useEmailComposer, type Audience } from '@/composables/useEmailComposer';
import AppLayout from '@/layouts/AppLayout.vue';
import { telLink } from '@/lib/links';
import { cap, monthYear } from '@/lib/portal';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Building2, LogIn, Mail, Pencil, Plus, Users } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';

type PlatformType = 'tenants' | 'agents' | 'customers';

interface Row {
    key: string;
    id: number;
    type: 'tenant' | 'agent' | 'customer';
    name: string;
    sub: string | null;
    meta: string | null;
    portal?: boolean; // customers: has a portal login to sign in as
}

interface TenantDetail {
    type: 'tenant';
    id: number;
    name: string;
    slug: string;
    status: string;
    pools: number;
    users: number;
    created: string | null;
    logo_url: string | null;
    ai: { enabled: boolean; quota: number | string | null; limit: number; used: number; cost: number };
}
interface PersonDetail {
    type: 'agent' | 'customer';
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    tenant: string | null;
    photo_url: string | null;
    is_active?: boolean;
    login_id: number | null; // the user to sign in as; null = a customer with no portal login
}

interface Campaign {
    id: number;
    subject: string;
    audience: string;
    recipients: number;
    sent: number;
    failed: number;
    sent_on: string | null;
    files: string[];
}

const props = defineProps<{
    audiences: Audience[];
    counts: { tenants: number; agents: number; customers: number };
    people: { data: Row[]; total: number };
    filters: { type: PlatformType; search: string };
    selected: TenantDetail | PersonDetail | null;
    aiDefaultQuota: number;
    recent: Campaign[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'People', href: '/people' }];

const tabs = [
    { key: 'tenants', label: 'Tenants' },
    { key: 'agents', label: 'Agents' },
    { key: 'customers', label: 'Customers' },
] as const;

// --- list navigation ---
const search = ref(props.filters.search);
let timer: ReturnType<typeof setTimeout> | undefined;
watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(
        () =>
            router.get(
                '/people',
                { type: props.filters.type, search: value || undefined },
                { preserveState: true, replace: true, preserveScroll: true },
            ),
        300,
    );
});
function setType(type: PlatformType) {
    router.get('/people', { type, search: search.value || undefined }, { preserveState: true, preserveScroll: true });
}
function openRowDetail(row: Row) {
    router.get(
        '/people',
        { type: props.filters.type, search: search.value || undefined, selected: row.id, selected_type: row.type },
        { preserveState: true, preserveScroll: true },
    );
}
function closeDrawer() {
    router.get('/people', { type: props.filters.type, search: search.value || undefined }, { preserveState: true, preserveScroll: true });
}

// --- email mode (checkboxes appear; composer docks in the pane) ---
const email = useEmailComposer(
    () => props.audiences,
    () => props.people.data.map((r) => r.key),
);
function openEmail() {
    form.value = null;
    email.start();
}

// --- tenant create / edit (super-admin): one form in the pane at a time ---
const form = ref<'create' | 'edit' | null>(null);
const createForm = useForm({ company: '', first_name: '', last_name: '', email: '', password: '' });
function openCreate() {
    email.close();
    createForm.reset();
    createForm.clearErrors();
    form.value = 'create';
}
const submitCreate = () =>
    saveRecord(createForm, '/tenants', null, () => {
        form.value = null;
        createForm.reset();
    });

const editId = ref<number | null>(null);
const editForm = useForm({ name: '', slug: '', status: 'active' });
const slugChanged = computed(() => tenant.value !== null && editForm.slug !== tenant.value.slug);
function openEdit(t: TenantDetail) {
    email.close();
    editForm.name = t.name;
    editForm.slug = t.slug;
    editForm.status = t.status;
    editForm.clearErrors();
    editId.value = t.id;
    form.value = 'edit';
}
const submitEdit = () => saveRecord(editForm, '/tenants', editId.value, () => (form.value = null));

function impersonate(t: TenantDetail) {
    if (!confirm(`Sign in as ${t.name}'s admin? This is logged.`)) return;
    router.post(`/tenants/${t.id}/impersonate`);
}
function impersonatePerson(p: PersonDetail) {
    if (p.login_id === null || !confirm(`Sign in as ${p.name}? This is logged.`)) return;
    router.post(`/users/${p.login_id}/impersonate`);
}

// --- per-tenant AI (auto-save to the platform endpoint) ---
const aiForm = reactive<{ enabled: boolean; quota: number | string | null }>({
    enabled: true,
    quota: null,
});
watch(
    () => props.selected,
    (s) => {
        if (s && s.type === 'tenant') {
            aiForm.enabled = s.ai.enabled;
            aiForm.quota = s.ai.quota;
        }
    },
    { immediate: true },
);
const aiLimit = computed(() => (aiForm.quota !== null && aiForm.quota !== '' ? Number(aiForm.quota) : props.aiDefaultQuota));
function saveTenantAi() {
    if (!props.selected || props.selected.type !== 'tenant') return;
    const quota = aiForm.quota === null || aiForm.quota === '' ? null : Number(aiForm.quota);
    router.patch(`/platform/ai/tenants/${props.selected.id}`, { enabled: aiForm.enabled, quota }, { preserveScroll: true, preserveState: true });
}

// --- shared pane ---
const tenant = computed(() => (props.selected?.type === 'tenant' ? (props.selected as TenantDetail) : null));
const person = computed(() => (props.selected && props.selected.type !== 'tenant' ? (props.selected as PersonDetail) : null));
/** "9 pools and 4 users. On RoutePilot since September 2026." */
const tenantSummary = (t: TenantDetail) =>
    `${t.pools} pool${t.pools === 1 ? '' : 's'} and ${t.users} user${t.users === 1 ? '' : 's'}.${t.created ? ` On RoutePilot since ${monthYear(t.created)}.` : ''}`;
const paneKey = computed(() => {
    if (form.value === 'edit') return `edit-${editId.value}`;
    if (form.value) return form.value;
    if (email.open) return 'email';
    return props.selected ? `${props.selected.type}-${props.selected.id}` : null;
});
function closePane() {
    if (form.value) form.value = null;
    else if (email.open) email.close();
    else closeDrawer();
}
</script>

<template>
    <Head title="People" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <template #filters>
            <button
                v-for="t in tabs"
                :key="t.key"
                class="rounded-md px-2.5 py-1 text-sm font-medium transition-colors"
                :class="props.filters.type === t.key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'"
                @click="setType(t.key)"
            >
                {{ t.label }} <span class="opacity-70">{{ props.counts[t.key] }}</span>
            </button>
        </template>

        <template #actions>
            <Input v-model="search" type="search" placeholder="Search people…" class="h-9 w-44 lg:w-56" />
            <Button v-if="props.filters.type === 'tenants'" size="sm" @click="openCreate"><Plus class="mr-1 size-4" /> Tenant</Button>
            <Button size="sm" variant="outline" @click="openEmail"
                ><Mail class="mr-1 size-4" /> Email<span v-if="email.picked.length"> ({{ email.picked.length }})</span></Button
            >
        </template>

        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <MasterDetail
                :has-selection="paneKey !== null"
                :selection-key="paneKey"
                empty-text="Select someone to see details, or hit Email to broadcast."
                @close="closePane"
            >
                <template #list>
                    <div class="border-border @container overflow-x-auto rounded-xl border">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50 text-muted-foreground text-left">
                                <tr>
                                    <th v-if="email.open" class="w-10 px-4 py-2">
                                        <input type="checkbox" :checked="email.allOnPage" aria-label="Select all" @change="email.toggleAll()" />
                                    </th>
                                    <th class="px-4 py-2 font-medium">Name</th>
                                    <th class="hidden px-4 py-2 font-medium @md:table-cell">Detail</th>
                                    <th class="hidden px-4 py-2 font-medium @xl:table-cell">
                                        {{ props.filters.type === 'tenants' ? 'Slug' : 'Tenant' }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in props.people.data"
                                    :key="row.key"
                                    class="border-border cursor-pointer border-t transition-colors"
                                    :class="
                                        (email.open && email.isPicked(row.key)) ||
                                        (!email.open && props.selected?.type === row.type && props.selected?.id === row.id)
                                            ? 'bg-primary/10'
                                            : 'hover:bg-muted/40'
                                    "
                                    @click="email.open ? email.toggle(row.key) : openRowDetail(row)"
                                >
                                    <td v-if="email.open" class="px-4 py-2.5" @click.stop>
                                        <input
                                            type="checkbox"
                                            :checked="email.isPicked(row.key)"
                                            :aria-label="`Select ${row.name}`"
                                            @change="email.toggle(row.key)"
                                        />
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center gap-2.5">
                                            <EntityAvatar
                                                :src="null"
                                                :type="row.type === 'tenant' ? 'tenant' : 'person'"
                                                :name="row.name"
                                                size="sm"
                                                :shape="row.type === 'tenant' ? 'square' : 'circle'"
                                            />
                                            <span class="font-medium">{{ row.name }}</span>
                                            <Chip v-if="row.portal" tone="info" title="Has a portal login you can sign in as">Portal</Chip>
                                        </div>
                                    </td>
                                    <td class="text-muted-foreground hidden px-4 py-2.5 capitalize @md:table-cell">{{ row.sub ?? '—' }}</td>
                                    <td class="text-muted-foreground hidden px-4 py-2.5 @xl:table-cell">{{ row.meta ?? '—' }}</td>
                                </tr>
                                <tr v-if="props.people.data.length === 0">
                                    <td :colspan="email.open ? 4 : 3" class="text-muted-foreground px-4 py-10 text-center">
                                        <Users class="mx-auto mb-2 size-6 opacity-50" />
                                        Nobody here.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted-foreground mt-2 text-xs">Showing {{ props.people.data.length }} of {{ props.people.total }}.</p>
                </template>

                <template #detail>
                    <!-- add tenant -->
                    <form v-if="form === 'create'" class="space-y-4 text-sm" @submit.prevent="submitCreate">
                        <PaneHeader title="New tenant" summary="Creates the company and its first admin, already verified." />
                        <FormField label="Company name" for="company" :error="createForm.errors.company"
                            ><Input id="company" v-model="createForm.company"
                        /></FormField>
                        <div class="grid grid-cols-2 gap-3">
                            <FormField label="Admin first name" for="fn"><Input id="fn" v-model="createForm.first_name" /></FormField>
                            <FormField label="Last name" for="ln"><Input id="ln" v-model="createForm.last_name" /></FormField>
                        </div>
                        <FormField label="Admin email" for="em" :error="createForm.errors.email"
                            ><Input id="em" v-model="createForm.email" type="email"
                        /></FormField>
                        <FormField label="Temporary password" for="pw" :error="createForm.errors.password"
                            ><PasswordInput id="pw" v-model="createForm.password" autocomplete="new-password"
                        /></FormField>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="form = null">Cancel</Button>
                            <Button type="submit" :disabled="createForm.processing">Create tenant</Button>
                        </div>
                    </form>

                    <!-- edit tenant -->
                    <form v-else-if="form === 'edit'" class="space-y-4 text-sm" @submit.prevent="submitEdit">
                        <PaneHeader title="Edit tenant" summary="Suspending locks their staff out. You can still sign in as them." />
                        <FormField label="Company name" for="tn" :error="editForm.errors.name"><Input id="tn" v-model="editForm.name" /></FormField>
                        <div class="grid gap-1.5">
                            <Label for="sl">Public slug</Label>
                            <div class="flex items-center gap-1.5">
                                <span class="text-muted-foreground shrink-0 text-xs">routepilot.pro/t/</span>
                                <Input id="sl" v-model="editForm.slug" class="font-mono" />
                            </div>
                            <p v-if="editForm.errors.slug" class="text-xs text-red-600">{{ editForm.errors.slug }}</p>
                            <p v-else-if="slugChanged" class="text-xs text-amber-600 dark:text-amber-500">
                                Changing the slug changes this company's public URL — existing links to the old address will stop working.
                            </p>
                        </div>
                        <FormField label="Status" for="st"
                            ><Select id="st" v-model="editForm.status">
                                <option value="active">Active</option>
                                <option value="suspended">Suspended</option>
                                <option value="cancelled">Cancelled</option>
                            </Select></FormField
                        >
                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="form = null">Cancel</Button>
                            <Button type="submit" :disabled="editForm.processing">Save</Button>
                        </div>
                    </form>

                    <!-- broadcast / selected email -->
                    <EmailComposer
                        v-else-if="email.open"
                        :composer="email"
                        summary="Tick people in the list, or send to a whole group."
                        note="Tenant picks reach that company's admins."
                        :recent="props.recent"
                        @cancel="email.close()"
                    />

                    <!-- a tenant (manage) -->
                    <div v-else-if="tenant" class="space-y-6">
                        <PaneHeader :title="tenant.name" :summary="tenantSummary(tenant)">
                            <template #media>
                                <img
                                    v-if="tenant.logo_url"
                                    :src="tenant.logo_url"
                                    :alt="tenant.name"
                                    class="size-16 shrink-0 rounded-2xl object-cover"
                                />
                                <IconTile v-else :icon="Building2" size="lg" />
                            </template>
                            <template #badge>
                                <Chip :tone="tenant.status === 'active' ? 'good' : tenant.status === 'suspended' ? 'warn' : 'muted'">{{
                                    cap(tenant.status)
                                }}</Chip>
                            </template>
                            <p class="mt-2 text-[15px]">
                                <a
                                    :href="`/t/${tenant.slug}`"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-primary font-medium underline-offset-4 hover:underline"
                                    >routepilot.pro/t/{{ tenant.slug }}</a
                                >
                            </p>
                            <template #actions>
                                <Button size="sm" variant="outline" @click="impersonate(tenant)"
                                    ><LogIn class="mr-1 size-3.5" /> Sign in as them</Button
                                >
                                <Button size="sm" variant="outline" @click="openEdit(tenant)"><Pencil class="mr-1 size-3.5" /> Edit</Button>
                            </template>
                        </PaneHeader>

                        <PaneSection title="AI assistant">
                            <ul class="border-border divide-border divide-y rounded-2xl border text-sm">
                                <li>
                                    <label class="flex cursor-pointer items-center justify-between gap-4 px-4 py-3">
                                        <span>
                                            <span class="block font-medium">Turned on</span>
                                            <span class="text-muted-foreground">Their team can use the assistant.</span>
                                        </span>
                                        <input v-model="aiForm.enabled" type="checkbox" class="size-5" @change="saveTenantAi" />
                                    </label>
                                </li>
                                <li class="flex items-center justify-between gap-4 px-4 py-3">
                                    <span>
                                        <Label for="t_quota" class="font-medium">Monthly allowance</Label>
                                        <span class="text-muted-foreground block"
                                            >Used {{ tenant.ai.used }} of {{ aiLimit }} this month (${{
                                                tenant.ai.cost.toFixed(tenant.ai.cost < 1 ? 4 : 2)
                                            }}). Leave blank for the platform default ({{ props.aiDefaultQuota }}).</span
                                        >
                                    </span>
                                    <Input
                                        id="t_quota"
                                        v-model.number="aiForm.quota"
                                        type="number"
                                        min="0"
                                        class="h-9 w-24 shrink-0"
                                        :placeholder="`${props.aiDefaultQuota}`"
                                        @change="saveTenantAi"
                                    />
                                </li>
                            </ul>
                        </PaneSection>
                    </div>

                    <!-- an agent or customer (read-only) -->
                    <div v-else-if="person" class="space-y-6">
                        <PaneHeader :title="person.name" :summary="`${cap(person.type)}${person.tenant ? ` at ${person.tenant}` : ''}.`">
                            <template #media>
                                <EntityAvatar :src="person.photo_url" type="person" :name="person.name" size="lg" shape="circle" />
                            </template>
                            <template v-if="person.type === 'agent'" #badge>
                                <Chip :tone="person.is_active ? 'good' : 'muted'">{{ person.is_active ? 'Active' : 'Inactive' }}</Chip>
                            </template>
                            <p v-if="person.login_id === null" class="text-muted-foreground mt-2 text-sm">
                                No portal login yet, so there's no account to sign in as.
                            </p>
                            <template v-if="person.login_id !== null" #actions>
                                <Button size="sm" variant="outline" @click="impersonatePerson(person)"
                                    ><LogIn class="mr-1 size-3.5" /> Sign in as them</Button
                                >
                            </template>
                        </PaneHeader>
                        <PaneSection title="Contact">
                            <PaneFacts
                                :facts="[
                                    { label: 'Email', value: person.email, href: person.email ? `mailto:${person.email}` : null },
                                    { label: 'Phone', value: person.phone, href: person.phone ? telLink(person.phone) : null },
                                ]"
                            />
                            <p v-if="!person.email && !person.phone" class="text-muted-foreground text-sm">No contact details.</p>
                        </PaneSection>
                    </div>
                </template>
            </MasterDetail>
        </div>
    </AppLayout>
</template>
