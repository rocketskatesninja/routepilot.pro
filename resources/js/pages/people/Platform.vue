<script setup lang="ts">
import EntityAvatar from '@/components/EntityAvatar.vue';
import MasterDetail from '@/components/MasterDetail.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Bot, LogIn, Mail, Pencil, Plus, Send, Users } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

type PlatformType = 'tenants' | 'agents' | 'customers';

interface Row {
    key: string;
    id: number;
    type: 'tenant' | 'agent' | 'customer';
    name: string;
    sub: string | null;
    meta: string | null;
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

const statusClass = (s: string) =>
    s === 'active'
        ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
        : s === 'suspended'
          ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400'
          : 'bg-red-500/15 text-red-600 dark:text-red-400';

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
function emailOne(key: string) {
    picked.value = [key];
    openEmail();
    emailForm.audience = 'selected';
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
const editForm = useForm({ name: '', status: 'active' });
function openEdit(t: TenantDetail) {
    emailOpen.value = false;
    createOpen.value = false;
    editForm.name = t.name;
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
                    <div class="overflow-hidden rounded-xl border border-border">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50 text-left text-muted-foreground">
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
                                    class="cursor-pointer border-t border-border transition-colors"
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
                                        </div>
                                    </td>
                                    <td class="hidden px-4 py-2.5 capitalize text-muted-foreground md:table-cell">{{ row.sub ?? '—' }}</td>
                                    <td class="hidden px-4 py-2.5 text-muted-foreground lg:table-cell">{{ row.meta ?? '—' }}</td>
                                </tr>
                                <tr v-if="props.people.data.length === 0">
                                    <td :colspan="emailOpen ? 4 : 3" class="px-4 py-10 text-center text-muted-foreground">
                                        <Users class="mx-auto mb-2 size-6 opacity-50" />
                                        Nobody here.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">Showing {{ props.people.data.length }} of {{ props.people.total }}.</p>
                </template>

                <template #detail>
                    <!-- add tenant -->
                    <form v-if="createOpen" class="space-y-4 text-sm" @submit.prevent="submitCreate">
                        <div class="mb-1">
                            <h2 class="text-lg font-semibold">New tenant</h2>
                            <p class="text-sm text-muted-foreground">Creates the company + its first admin (pre-verified).</p>
                        </div>
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
                            <Input id="pw" v-model="createForm.password" type="password" autocomplete="new-password" />
                            <p v-if="createForm.errors.password" class="text-xs text-red-600">{{ createForm.errors.password }}</p>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="createOpen = false">Cancel</Button>
                            <Button type="submit" :disabled="createForm.processing">Create tenant</Button>
                        </div>
                    </form>

                    <!-- edit tenant -->
                    <form v-else-if="editOpen" class="space-y-4 text-sm" @submit.prevent="submitEdit">
                        <div class="mb-1">
                            <h2 class="text-lg font-semibold">Edit tenant</h2>
                            <p class="text-sm text-muted-foreground">Suspending locks the company's staff out (you keep impersonation access).</p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="tn">Company name</Label>
                            <Input id="tn" v-model="editForm.name" />
                            <p v-if="editForm.errors.name" class="text-xs text-red-600">{{ editForm.errors.name }}</p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="st">Status</Label>
                            <select id="st" v-model="editForm.status" class="h-9 rounded-md border border-input bg-background px-2 text-sm">
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
                        <div class="mb-1">
                            <h2 class="text-lg font-semibold">Email people</h2>
                            <p class="text-sm text-muted-foreground">Tick people in the list, or send to a whole audience.</p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="aud">Audience</Label>
                            <select id="aud" v-model="emailForm.audience" class="h-9 rounded-md border border-input bg-background px-2 text-sm">
                                <option v-for="a in audienceOptions" :key="a.key" :value="a.key">{{ a.label }} ({{ a.count }})</option>
                            </select>
                            <p class="text-xs text-muted-foreground">{{ emailCount }} recipient(s). Tenant picks reach that company's admins.</p>
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
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                            ></textarea>
                            <p v-if="emailForm.errors.body" class="text-xs text-red-600">{{ emailForm.errors.body }}</p>
                        </div>
                        <div v-if="props.recent.length" class="border-t border-border pt-2">
                            <p class="mb-1 text-xs font-medium text-muted-foreground">Recent broadcasts</p>
                            <ul class="space-y-0.5 text-xs text-muted-foreground">
                                <li v-for="c in props.recent.slice(0, 5)" :key="c.id" class="flex justify-between gap-2">
                                    <span class="truncate">{{ c.subject }}</span
                                    ><span class="shrink-0 capitalize">{{ c.audience }} · {{ c.recipients }}</span>
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

                    <!-- tenant detail (manage) -->
                    <div v-else-if="tenant" class="space-y-6 text-sm">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2.5">
                                <EntityAvatar :src="tenant.logo_url" type="tenant" :name="tenant.name" size="md" />
                                <div>
                                    <h2 class="text-lg font-semibold">{{ tenant.name }}</h2>
                                    <p class="text-xs text-muted-foreground">routepilot.pro/t/{{ tenant.slug }}</p>
                                </div>
                            </div>
                            <div class="flex gap-1.5">
                                <Button size="sm" variant="outline" @click="impersonate(tenant)"><LogIn class="mr-1 size-3.5" /> Sign in</Button>
                                <Button size="sm" variant="outline" @click="openEdit(tenant)"><Pencil class="size-3.5" /></Button>
                            </div>
                        </div>

                        <dl class="grid grid-cols-2 gap-x-4 gap-y-3">
                            <div>
                                <dt class="text-xs text-muted-foreground">Status</dt>
                                <dd>
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                        :class="statusClass(tenant.status)"
                                        >{{ tenant.status }}</span
                                    >
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">Created</dt>
                                <dd>{{ tenant.created ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">Pools</dt>
                                <dd>{{ tenant.pools }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">Users</dt>
                                <dd>{{ tenant.users }}</dd>
                            </div>
                        </dl>

                        <div class="rounded-lg border border-border p-4">
                            <div class="mb-3 flex items-center gap-2 font-medium"><Bot class="size-4" /> AI assistant</div>
                            <div class="space-y-3">
                                <label class="flex items-center justify-between gap-2">
                                    <span>AI enabled</span>
                                    <input v-model="aiForm.enabled" type="checkbox" @change="saveTenantAi" />
                                </label>
                                <div class="flex items-center justify-between gap-2">
                                    <div>
                                        <Label for="t_quota">Monthly quota</Label>
                                        <p class="text-xs text-muted-foreground">Blank = platform default ({{ props.aiDefaultQuota }}).</p>
                                    </div>
                                    <Input
                                        id="t_quota"
                                        v-model.number="aiForm.quota"
                                        type="number"
                                        min="0"
                                        class="h-8 w-28"
                                        :placeholder="`${props.aiDefaultQuota}`"
                                        @change="saveTenantAi"
                                    />
                                </div>
                                <div class="text-xs text-muted-foreground">Used this month: {{ tenant.ai.used }} / {{ aiLimit }}</div>
                                <label class="flex items-center justify-between gap-2 border-t border-border pt-3">
                                    <span>
                                        May override
                                        <span class="block text-xs text-muted-foreground">Let this tenant supply their own provider/model/key.</span>
                                    </span>
                                    <input v-model="aiForm.allow_override" type="checkbox" @change="saveTenantAi" />
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- agent / customer (read-only card) -->
                    <div v-else-if="person" class="space-y-5 text-sm">
                        <div class="flex items-center gap-2.5">
                            <EntityAvatar :src="person.photo_url" type="person" :name="person.name" size="md" shape="circle" />
                            <div>
                                <h2 class="text-lg font-semibold">{{ person.name }}</h2>
                                <p class="text-xs capitalize text-muted-foreground">
                                    {{ person.type }}<span v-if="person.tenant"> · {{ person.tenant }}</span>
                                </p>
                            </div>
                        </div>
                        <dl class="grid grid-cols-1 gap-y-3">
                            <div>
                                <dt class="text-xs text-muted-foreground">Email</dt>
                                <dd>{{ person.email ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">Phone</dt>
                                <dd>{{ person.phone ?? '—' }}</dd>
                            </div>
                            <div v-if="person.type === 'agent'">
                                <dt class="text-xs text-muted-foreground">Active</dt>
                                <dd>{{ person.is_active ? 'Yes' : 'No' }}</dd>
                            </div>
                        </dl>
                        <Button size="sm" variant="outline" @click="emailOne(`${person.type}:${person.id}`)"
                            ><Mail class="mr-1 size-3.5" /> Email</Button
                        >
                    </div>
                </template>
            </MasterDetail>
        </div>
    </AppLayout>
</template>
