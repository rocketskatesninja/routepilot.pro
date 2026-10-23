<script setup lang="ts">
import EntityAvatar from '@/components/EntityAvatar.vue';
import MasterDetail from '@/components/MasterDetail.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import Chip from '@/components/pane/Chip.vue';
import IconTile from '@/components/pane/IconTile.vue';
import PaneFacts from '@/components/pane/PaneFacts.vue';
import PaneHeader from '@/components/pane/PaneHeader.vue';
import PaneSection from '@/components/pane/PaneSection.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { telLink } from '@/lib/links';
import { cap, monthYear } from '@/lib/portal';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Building2, LogIn, Mail, Pencil, Plus, Send, Users } from 'lucide-vue-next';
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
    ai: { enabled: boolean; allow_override: boolean; quota: number | string | null; limit: number; used: number };
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

interface Audience {
    key: string;
    label: string;
    count: number;
}
interface Campaign {
    id: number;
    subject: string;
    audience: string;
    recipients: number;
    sent: number;
    failed: number;
    sent_on: string | null;
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
const picked = ref<string[]>([]);
const isPicked = (row: Row) => picked.value.includes(row.key);
function togglePick(row: Row) {
    const i = picked.value.indexOf(row.key);
    if (i === -1) picked.value.push(row.key);
    else picked.value.splice(i, 1);
}
const pageKeys = computed(() => props.people.data.map((r) => r.key));
const allOnPage = computed(() => pageKeys.value.length > 0 && pageKeys.value.every((k) => picked.value.includes(k)));
function toggleAll() {
    if (allOnPage.value) picked.value = picked.value.filter((k) => !pageKeys.value.includes(k));
    else picked.value = [...new Set([...picked.value, ...pageKeys.value])];
}

const emailOpen = ref(false);
const emailForm = useForm<{ audience: string; subject: string; body: string; recipients: string[] }>({
    audience: 'tenants',
    subject: '',
    body: '',
    recipients: [],
});
const audienceOptions = computed(() => [
    ...(picked.value.length ? [{ key: 'selected', label: 'Selected people', count: picked.value.length }] : []),
    ...props.audiences,
]);
const emailCount = computed(() => audienceOptions.value.find((a) => a.key === emailForm.audience)?.count ?? 0);
function openEmail() {
    createOpen.value = false;
    editOpen.value = false;
    emailForm.audience = picked.value.length ? 'selected' : (props.audiences[0]?.key ?? 'tenants');
    emailForm.clearErrors();
    emailOpen.value = true;
}
function submitEmail() {
    emailForm.recipients = emailForm.audience === 'selected' ? [...picked.value] : [];
    emailForm.post('/people/email', {
        preserveScroll: true,
        onSuccess: () => {
            emailOpen.value = false;
            emailForm.reset();
            picked.value = [];
        },
    });
}

// --- tenant create / edit (super-admin) ---
const createOpen = ref(false);
const createForm = useForm({ company: '', first_name: '', last_name: '', email: '', password: '' });
function openCreate() {
    emailOpen.value = false;
    editOpen.value = false;
    createForm.reset();
    createForm.clearErrors();
    createOpen.value = true;
}
function submitCreate() {
    createForm.post('/tenants', {
        preserveScroll: true,
        onSuccess: () => {
            createOpen.value = false;
            createForm.reset();
        },
    });
}

const editOpen = ref(false);
const editId = ref<number | null>(null);
const editForm = useForm({ name: '', slug: '', status: 'active' });
const slugChanged = computed(() => tenant.value !== null && editForm.slug !== tenant.value.slug);
function openEdit(t: TenantDetail) {
    emailOpen.value = false;
    createOpen.value = false;
    editForm.name = t.name;
    editForm.slug = t.slug;
    editForm.status = t.status;
    editForm.clearErrors();
    editId.value = t.id;
    editOpen.value = true;
}
function submitEdit() {
    if (editId.value === null) return;
    editForm.patch(`/tenants/${editId.value}`, { preserveScroll: true, onSuccess: () => (editOpen.value = false) });
}

function impersonate(t: TenantDetail) {
    if (!confirm(`Sign in as ${t.name}'s admin? This is logged.`)) return;
    router.post(`/tenants/${t.id}/impersonate`);
}
function impersonatePerson(p: PersonDetail) {
    if (p.login_id === null || !confirm(`Sign in as ${p.name}? This is logged.`)) return;
    router.post(`/users/${p.login_id}/impersonate`);
}

// --- per-tenant AI (auto-save to the platform endpoint) ---
const aiForm = reactive<{ enabled: boolean; allow_override: boolean; quota: number | string | null }>({
    enabled: true,
    allow_override: false,
    quota: null,
});
watch(
    () => props.selected,
    (s) => {
        if (s && s.type === 'tenant') {
            aiForm.enabled = s.ai.enabled;
            aiForm.allow_override = s.ai.allow_override;
            aiForm.quota = s.ai.quota;
        }
    },
    { immediate: true },
);
const aiLimit = computed(() => (aiForm.quota !== null && aiForm.quota !== '' ? Number(aiForm.quota) : props.aiDefaultQuota));
function saveTenantAi() {
    if (!props.selected || props.selected.type !== 'tenant') return;
    const quota = aiForm.quota === null || aiForm.quota === '' ? null : Number(aiForm.quota);
    router.patch(
        `/platform/ai/tenants/${props.selected.id}`,
        { enabled: aiForm.enabled, allow_override: aiForm.allow_override, quota },
        { preserveScroll: true, preserveState: true },
    );
}

// --- shared pane ---
const tenant = computed(() => (props.selected?.type === 'tenant' ? (props.selected as TenantDetail) : null));
const person = computed(() => (props.selected && props.selected.type !== 'tenant' ? (props.selected as PersonDetail) : null));
/** "9 pools and 4 users. On RoutePilot since September 2026." */
const tenantSummary = (t: TenantDetail) =>
    `${t.pools} pool${t.pools === 1 ? '' : 's'} and ${t.users} user${t.users === 1 ? '' : 's'}.${t.created ? ` On RoutePilot since ${monthYear(t.created)}.` : ''}`;
const anyFormOpen = computed(() => createOpen.value || editOpen.value || emailOpen.value);
const paneKey = computed(() => {
    if (createOpen.value) return 'create';
    if (editOpen.value) return `edit-${editId.value}`;
    if (emailOpen.value) return 'email';
    return props.selected ? `${props.selected.type}-${props.selected.id}` : null;
});
const paneOpen = computed(() => anyFormOpen.value || props.selected !== null);
function closePane() {
    if (anyFormOpen.value) {
        createOpen.value = false;
        editOpen.value = false;
        emailOpen.value = false;
    } else {
        closeDrawer();
    }
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
                ><Mail class="mr-1 size-4" /> Email<span v-if="picked.length"> ({{ picked.length }})</span></Button
            >
        </template>

        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <MasterDetail
                :has-selection="paneOpen"
                :selection-key="paneKey"
                empty-text="Select someone to see details, or hit Email to broadcast."
                @close="closePane"
            >
                <template #list>
                    <div class="border-border overflow-hidden rounded-xl border">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50 text-muted-foreground text-left">
                                <tr>
                                    <th v-if="emailOpen" class="w-10 px-4 py-2">
                                        <input type="checkbox" :checked="allOnPage" aria-label="Select all" @change="toggleAll" />
                                    </th>
                                    <th class="px-4 py-2 font-medium">Name</th>
                                    <th class="hidden px-4 py-2 font-medium md:table-cell">Detail</th>
                                    <th class="hidden px-4 py-2 font-medium lg:table-cell">
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
                                        (emailOpen && isPicked(row)) ||
                                        (!emailOpen && props.selected?.type === row.type && props.selected?.id === row.id)
                                            ? 'bg-primary/10'
                                            : 'hover:bg-muted/40'
                                    "
                                    @click="emailOpen ? togglePick(row) : openRowDetail(row)"
                                >
                                    <td v-if="emailOpen" class="px-4 py-2.5" @click.stop>
                                        <input
                                            type="checkbox"
                                            :checked="isPicked(row)"
                                            :aria-label="`Select ${row.name}`"
                                            @change="togglePick(row)"
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
                                    <td class="text-muted-foreground hidden px-4 py-2.5 capitalize md:table-cell">{{ row.sub ?? '—' }}</td>
                                    <td class="text-muted-foreground hidden px-4 py-2.5 lg:table-cell">{{ row.meta ?? '—' }}</td>
                                </tr>
                                <tr v-if="props.people.data.length === 0">
                                    <td :colspan="emailOpen ? 4 : 3" class="text-muted-foreground px-4 py-10 text-center">
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
                    <form v-if="createOpen" class="space-y-4 text-sm" @submit.prevent="submitCreate">
                        <PaneHeader title="New tenant" summary="Creates the company and its first admin, already verified." />
                        <div class="grid gap-1.5">
                            <Label for="company">Company name</Label>
                            <Input id="company" v-model="createForm.company" />
                            <p v-if="createForm.errors.company" class="text-xs text-red-600">{{ createForm.errors.company }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5"><Label for="fn">Admin first name</Label><Input id="fn" v-model="createForm.first_name" /></div>
                            <div class="grid gap-1.5"><Label for="ln">Last name</Label><Input id="ln" v-model="createForm.last_name" /></div>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="em">Admin email</Label>
                            <Input id="em" v-model="createForm.email" type="email" />
                            <p v-if="createForm.errors.email" class="text-xs text-red-600">{{ createForm.errors.email }}</p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="pw">Temporary password</Label>
                            <PasswordInput id="pw" v-model="createForm.password" autocomplete="new-password" />
                            <p v-if="createForm.errors.password" class="text-xs text-red-600">{{ createForm.errors.password }}</p>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="createOpen = false">Cancel</Button>
                            <Button type="submit" :disabled="createForm.processing">Create tenant</Button>
                        </div>
                    </form>

                    <!-- edit tenant -->
                    <form v-else-if="editOpen" class="space-y-4 text-sm" @submit.prevent="submitEdit">
                        <PaneHeader title="Edit tenant" summary="Suspending locks their staff out. You can still sign in as them." />
                        <div class="grid gap-1.5">
                            <Label for="tn">Company name</Label>
                            <Input id="tn" v-model="editForm.name" />
                            <p v-if="editForm.errors.name" class="text-xs text-red-600">{{ editForm.errors.name }}</p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="sl">Public slug</Label>
                            <div class="flex items-center gap-1.5">
                                <span class="text-muted-foreground shrink-0 text-xs">routepilot.pro/t/</span>
                                <Input id="sl" v-model="editForm.slug" class="font-mono" />
                            </div>
                            <p v-if="editForm.errors.slug" class="text-xs text-red-600">{{ editForm.errors.slug }}</p>
                            <p v-else-if="slugChanged" class="text-xs text-amber-600 dark:text-amber-500">
                                ⚠ Changing the slug changes this company's public URL — existing links to the old address will stop working.
                            </p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="st">Status</Label>
                            <select id="st" v-model="editForm.status" class="border-input bg-background h-9 rounded-md border px-2 text-sm">
                                <option value="active">Active</option>
                                <option value="suspended">Suspended</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="editOpen = false">Cancel</Button>
                            <Button type="submit" :disabled="editForm.processing">Save</Button>
                        </div>
                    </form>

                    <!-- broadcast / selected email -->
                    <form v-else-if="emailOpen" class="space-y-4 text-sm" @submit.prevent="submitEmail">
                        <PaneHeader title="Email people" summary="Tick people in the list, or send to a whole group." />
                        <div class="grid gap-1.5">
                            <Label for="aud">Audience</Label>
                            <select id="aud" v-model="emailForm.audience" class="border-input bg-background h-9 rounded-md border px-2 text-sm">
                                <option v-for="a in audienceOptions" :key="a.key" :value="a.key">{{ a.label }} ({{ a.count }})</option>
                            </select>
                            <p class="text-muted-foreground text-xs">{{ emailCount }} recipient(s). Tenant picks reach that company's admins.</p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="esub">Subject</Label>
                            <Input id="esub" v-model="emailForm.subject" />
                            <p v-if="emailForm.errors.subject" class="text-xs text-red-600">{{ emailForm.errors.subject }}</p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="ebody">Message</Label>
                            <textarea
                                id="ebody"
                                v-model="emailForm.body"
                                rows="7"
                                class="border-input bg-background rounded-md border px-3 py-2 text-sm"
                            ></textarea>
                            <p v-if="emailForm.errors.body" class="text-xs text-red-600">{{ emailForm.errors.body }}</p>
                        </div>
                        <div v-if="props.recent.length" class="border-border border-t pt-2">
                            <p class="text-muted-foreground mb-1 text-xs font-medium">Recent broadcasts</p>
                            <ul class="text-muted-foreground space-y-0.5 text-xs">
                                <li v-for="c in props.recent.slice(0, 5)" :key="c.id" class="flex justify-between gap-2">
                                    <span class="truncate">{{ c.subject }}</span
                                    ><span class="shrink-0"
                                        ><span class="capitalize">{{ c.audience }}</span> · {{ c.sent }}/{{ c.recipients
                                        }}<span v-if="c.failed" class="text-red-500"> · {{ c.failed }} failed</span></span
                                    >
                                </li>
                            </ul>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="emailOpen = false">Cancel</Button>
                            <Button type="submit" :disabled="emailForm.processing || emailCount === 0"
                                ><Send class="mr-1 size-4" /> Send to {{ emailCount }}</Button
                            >
                        </div>
                    </form>

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
                                        <input v-model="aiForm.enabled" type="checkbox" class="accent-primary size-5" @change="saveTenantAi" />
                                    </label>
                                </li>
                                <li class="flex items-center justify-between gap-4 px-4 py-3">
                                    <span>
                                        <Label for="t_quota" class="font-medium">Monthly allowance</Label>
                                        <span class="text-muted-foreground block"
                                            >Used {{ tenant.ai.used }} of {{ aiLimit }} this month. Leave blank for the platform default ({{
                                                props.aiDefaultQuota
                                            }}).</span
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
                                <li>
                                    <label class="flex cursor-pointer items-center justify-between gap-4 px-4 py-3">
                                        <span>
                                            <span class="block font-medium">Own AI provider</span>
                                            <span class="text-muted-foreground">Let them use their own provider, model and key.</span>
                                        </span>
                                        <input v-model="aiForm.allow_override" type="checkbox" class="accent-primary size-5" @change="saveTenantAi" />
                                    </label>
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
