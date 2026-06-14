<script setup lang="ts">
import EntityAvatar from '@/components/EntityAvatar.vue';
import MasterDetail from '@/components/MasterDetail.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Building2, LogIn, Pencil, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface TenantRow {
    id: number;
    name: string;
    logo_url: string | null;
    slug: string;
    status: string;
    users: number;
    pools: number;
    created: string | null;
}

const props = defineProps<{ tenants: TenantRow[] }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Tenants', href: '/tenants' }];

const statusClass = (s: string) =>
    s === 'active'
        ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
        : s === 'suspended'
          ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400'
          : 'bg-red-500/15 text-red-600 dark:text-red-400';

function impersonate(t: TenantRow) {
    if (!confirm(`Sign in as ${t.name}'s admin? This is logged.`)) return;
    router.post(`/tenants/${t.id}/impersonate`);
}

// --- create tenant ---
const createOpen = ref(false);
const createForm = useForm({ company: '', first_name: '', last_name: '', email: '', password: '' });
function submitCreate() {
    createForm.post('/tenants', {
        preserveScroll: true,
        onSuccess: () => {
            createOpen.value = false;
            createForm.reset();
        },
    });
}

// --- edit tenant ---
const editOpen = ref(false);
const editId = ref<number | null>(null);
const editForm = useForm({ name: '', status: 'active' });
function openEdit(t: TenantRow) {
    editForm.name = t.name;
    editForm.status = t.status;
    editForm.clearErrors();
    editId.value = t.id;
    editOpen.value = true;
}
function submitEdit() {
    if (editId.value === null) return;
    editForm.patch(`/tenants/${editId.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            editOpen.value = false;
        },
    });
}

// The forms dock into the detail pane rather than overlaying. Only one is open
// at a time; closing the pane cancels whichever is open.
const anyFormOpen = computed(() => createOpen.value || editOpen.value);
const formKey = computed(() => (createOpen.value ? 'create' : editOpen.value ? `edit-${editId.value}` : null));
function closePane() {
    createOpen.value = false;
    editOpen.value = false;
}
</script>

<template>
    <Head title="Tenants" />

    <AppLayout :breadcrumbs="breadcrumbs" :meta="`${props.tenants.length} companies`">
        <template #actions>
            <Button size="sm" @click="createOpen = true"><Plus class="mr-1 size-4" /> Tenant</Button>
        </template>

        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <MasterDetail
                :has-selection="anyFormOpen"
                :selection-key="formKey"
                empty-text="Add a tenant, or edit a row to manage one."
                @close="closePane"
            >
                <template #list>
                    <div class="overflow-hidden rounded-xl border border-border">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-2 font-medium">Company</th>
                                    <th class="px-4 py-2 font-medium">Status</th>
                                    <th class="hidden px-4 py-2 font-medium md:table-cell">Pools</th>
                                    <th class="hidden px-4 py-2 font-medium md:table-cell">Users</th>
                                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="t in props.tenants" :key="t.id" class="border-t border-border">
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center gap-2.5">
                                            <EntityAvatar :src="t.logo_url" type="tenant" :name="t.name" size="sm" />
                                            <div>
                                                <div class="font-medium">{{ t.name }}</div>
                                                <div class="text-xs text-muted-foreground">{{ t.slug }}.routepilot.pro</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                            :class="statusClass(t.status)"
                                            >{{ t.status }}</span
                                        >
                                    </td>
                                    <td class="hidden px-4 py-2.5 text-muted-foreground md:table-cell">{{ t.pools }}</td>
                                    <td class="hidden px-4 py-2.5 text-muted-foreground md:table-cell">{{ t.users }}</td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex justify-end gap-1.5">
                                            <Button size="sm" variant="outline" @click="impersonate(t)"
                                                ><LogIn class="mr-1 size-3.5" /> Sign in</Button
                                            >
                                            <Button size="sm" variant="outline" @click="openEdit(t)"><Pencil class="size-3.5" /></Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="props.tenants.length === 0">
                                    <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">
                                        <Building2 class="mx-auto mb-2 size-6 opacity-50" />
                                        No tenants yet.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>

                <template #detail>
                    <!-- create tenant: hosted in the docked pane -->
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

                    <!-- edit tenant: hosted in the docked pane -->
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
                </template>
            </MasterDetail>
        </div>
    </AppLayout>
</template>
