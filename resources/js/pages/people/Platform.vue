<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Building2, Mail, Send, Users, Waves } from 'lucide-vue-next';
import { computed } from 'vue';

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
    recent: Campaign[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'People', href: '/people' }];

const form = useForm({ audience: 'tenants', subject: '', body: '' });
const selectedCount = computed(() => props.audiences.find((a) => a.key === form.audience)?.count ?? 0);

function submit() {
    form.post('/people/email', { preserveScroll: true, onSuccess: () => form.reset('subject', 'body') });
}
</script>

<template>
    <Head title="People" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <h1 class="text-xl font-semibold">People</h1>

            <div class="grid grid-cols-3 gap-3">
                <div class="flex items-center gap-3 rounded-xl border border-border p-4">
                    <Building2 class="size-6 text-muted-foreground" />
                    <div>
                        <div class="text-2xl font-semibold">{{ props.counts.tenants }}</div>
                        <div class="text-sm text-muted-foreground">Tenants</div>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-border p-4">
                    <Users class="size-6 text-muted-foreground" />
                    <div>
                        <div class="text-2xl font-semibold">{{ props.counts.agents }}</div>
                        <div class="text-sm text-muted-foreground">Agents</div>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-border p-4">
                    <Waves class="size-6 text-muted-foreground" />
                    <div>
                        <div class="text-2xl font-semibold">{{ props.counts.customers }}</div>
                        <div class="text-sm text-muted-foreground">Customers</div>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-5">
                <!-- broadcast composer -->
                <form class="space-y-4 rounded-xl border border-border p-4 text-sm lg:col-span-2" @submit.prevent="submit">
                    <h2 class="flex items-center gap-2 font-medium"><Mail class="size-4" /> Broadcast email</h2>
                    <div class="grid gap-1.5">
                        <Label for="audience">Audience</Label>
                        <select id="audience" v-model="form.audience" class="h-9 rounded-md border border-input bg-background px-2 text-sm">
                            <option v-for="a in props.audiences" :key="a.key" :value="a.key">{{ a.label }} ({{ a.count }})</option>
                        </select>
                        <p class="text-xs text-muted-foreground">{{ selectedCount }} recipient(s) — platform-wide.</p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="subject">Subject</Label>
                        <Input id="subject" v-model="form.subject" />
                        <p v-if="form.errors.subject" class="text-xs text-red-600">{{ form.errors.subject }}</p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="body">Message</Label>
                        <textarea
                            id="body"
                            v-model="form.body"
                            rows="8"
                            class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        ></textarea>
                        <p v-if="form.errors.body" class="text-xs text-red-600">{{ form.errors.body }}</p>
                    </div>
                    <Button type="submit" :disabled="form.processing || selectedCount === 0"
                        ><Send class="mr-1 size-4" /> Send to {{ selectedCount }}</Button
                    >
                </form>

                <!-- history -->
                <div class="overflow-hidden rounded-xl border border-border lg:col-span-3">
                    <h2 class="border-b border-border px-4 py-2 font-medium">Recent broadcasts</h2>
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 font-medium">Subject</th>
                                <th class="px-4 py-2 font-medium">Audience</th>
                                <th class="px-4 py-2 font-medium">Recipients</th>
                                <th class="hidden px-4 py-2 font-medium md:table-cell">Sent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="c in props.recent" :key="c.id" class="border-t border-border">
                                <td class="px-4 py-2.5 font-medium">{{ c.subject }}</td>
                                <td class="px-4 py-2.5 capitalize text-muted-foreground">{{ c.audience }}</td>
                                <td class="px-4 py-2.5 text-muted-foreground">{{ c.recipients }}</td>
                                <td class="hidden px-4 py-2.5 text-muted-foreground md:table-cell">{{ c.sent_on }}</td>
                            </tr>
                            <tr v-if="props.recent.length === 0">
                                <td colspan="4" class="px-4 py-10 text-center text-muted-foreground">
                                    <Mail class="mx-auto mb-2 size-6 opacity-50" />
                                    No broadcasts sent yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
