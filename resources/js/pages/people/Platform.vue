<script setup lang="ts">
import MasterDetail from '@/components/MasterDetail.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Mail, Send, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type PlatformType = 'tenants' | 'agents' | 'customers';

interface PlatformRow {
    key: string;
    name: string;
    sub: string | null;
    meta: string | null;
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
    people: { data: PlatformRow[]; total: number };
    filters: { type: PlatformType; search: string };
    recent: Campaign[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'People', href: '/people' }];

const tabs = [
    { key: 'tenants', label: 'Tenants' },
    { key: 'agents', label: 'Agents' },
    { key: 'customers', label: 'Customers' },
] as const;

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
const setType = (type: PlatformType) =>
    router.get('/people', { type, search: search.value || undefined }, { preserveState: true, preserveScroll: true });

// --- selection + email ---
const selected = ref<string[]>([]);
const isSelected = (key: string) => selected.value.includes(key);
function toggleSelect(key: string) {
    const i = selected.value.indexOf(key);
    if (i === -1) selected.value.push(key);
    else selected.value.splice(i, 1);
}
const pageKeys = computed(() => props.people.data.map((r) => r.key));
const allOnPage = computed(() => pageKeys.value.length > 0 && pageKeys.value.every((k) => selected.value.includes(k)));
function toggleAll() {
    if (allOnPage.value) selected.value = selected.value.filter((k) => !pageKeys.value.includes(k));
    else selected.value = [...new Set([...selected.value, ...pageKeys.value])];
}

const emailOpen = ref(false);
const emailForm = useForm<{ audience: string; subject: string; body: string; recipients: string[] }>({
    audience: 'tenants',
    subject: '',
    body: '',
    recipients: [],
});
const audienceOptions = computed(() => [
    ...(selected.value.length ? [{ key: 'selected', label: 'Selected people', count: selected.value.length }] : []),
    ...props.audiences,
]);
const emailCount = computed(() => audienceOptions.value.find((a) => a.key === emailForm.audience)?.count ?? 0);
function openEmail() {
    emailForm.audience = selected.value.length ? 'selected' : (props.audiences[0]?.key ?? 'tenants');
    emailForm.clearErrors();
    emailOpen.value = true;
}
function submitEmail() {
    emailForm.recipients = emailForm.audience === 'selected' ? [...selected.value] : [];
    emailForm.post('/people/email', {
        preserveScroll: true,
        onSuccess: () => {
            emailOpen.value = false;
            emailForm.reset();
            selected.value = [];
        },
    });
}

// The compose form docks into the detail pane rather than overlaying.
function closePane() {
    emailOpen.value = false;
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
            <Button size="sm" variant="outline" @click="openEmail"
                ><Mail class="mr-1 size-4" /> Email<span v-if="selected.length"> ({{ selected.length }})</span></Button
            >
        </template>

        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <MasterDetail
                :has-selection="emailOpen"
                :selection-key="emailOpen ? 'email' : null"
                empty-text="Tick people or pick an audience, then compose an email."
                @close="closePane"
            >
                <template #list>
                    <div class="overflow-hidden rounded-xl border border-border">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th class="w-10 px-4 py-2">
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
                                    class="border-t border-border transition-colors hover:bg-muted/40"
                                >
                                    <td class="px-4 py-2.5">
                                        <input
                                            type="checkbox"
                                            :checked="isSelected(row.key)"
                                            :aria-label="`Select ${row.name}`"
                                            @change="toggleSelect(row.key)"
                                        />
                                    </td>
                                    <td class="px-4 py-2.5 font-medium">{{ row.name }}</td>
                                    <td class="hidden px-4 py-2.5 capitalize text-muted-foreground md:table-cell">{{ row.sub ?? '—' }}</td>
                                    <td class="hidden px-4 py-2.5 text-muted-foreground lg:table-cell">{{ row.meta ?? '—' }}</td>
                                </tr>
                                <tr v-if="props.people.data.length === 0">
                                    <td colspan="4" class="px-4 py-10 text-center text-muted-foreground">
                                        <Users class="mx-auto mb-2 size-6 opacity-50" />
                                        Nobody here.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Showing {{ props.people.data.length }} of {{ props.people.total }} — search to narrow, then tick people or use a preset.
                    </p>
                </template>

                <template #detail>
                    <!-- broadcast / selected email: hosted in the docked pane -->
                    <form class="space-y-4 text-sm" @submit.prevent="submitEmail">
                        <div class="mb-1">
                            <h2 class="text-lg font-semibold">Email people</h2>
                            <p class="text-sm text-muted-foreground">Send to the ticked people or a whole platform audience.</p>
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
                </template>
            </MasterDetail>
        </div>
    </AppLayout>
</template>
