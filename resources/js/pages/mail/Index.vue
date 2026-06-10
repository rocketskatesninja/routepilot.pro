<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Mail, Send } from 'lucide-vue-next';
import { computed } from 'vue';

interface Campaign {
    id: number;
    subject: string;
    audience: string;
    recipients: number;
    sent: number;
    failed: number;
    sent_on: string | null;
    by: string | null;
}

interface Audience {
    key: string;
    label: string;
    count: number;
}

const props = defineProps<{
    campaigns: Campaign[];
    audiences: Audience[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Mail', href: '/mail' }];

const form = useForm({ audience: 'customers', subject: '', body: '' });
const selectedCount = computed(() => props.audiences.find((a) => a.key === form.audience)?.count ?? 0);

function submit() {
    form.post('/mail', { preserveScroll: true, onSuccess: () => form.reset('subject', 'body') });
}
</script>

<template>
    <Head title="Mail" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <h1 class="text-xl font-semibold">Mail</h1>

            <div class="grid gap-4 lg:grid-cols-5">
                <!-- composer -->
                <form class="space-y-4 rounded-xl border border-border p-4 text-sm lg:col-span-2" @submit.prevent="submit">
                    <h2 class="font-medium">Compose</h2>
                    <div class="grid gap-1.5">
                        <Label for="audience">Audience</Label>
                        <select id="audience" v-model="form.audience" class="h-9 rounded-md border border-input bg-background px-2 text-sm">
                            <option v-for="a in props.audiences" :key="a.key" :value="a.key">{{ a.label }} ({{ a.count }})</option>
                        </select>
                        <p class="text-xs text-muted-foreground">{{ selectedCount }} recipient(s) — opt-outs are excluded.</p>
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
                    <h2 class="border-b border-border px-4 py-2 font-medium">Campaign history</h2>
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 font-medium">Subject</th>
                                <th class="hidden px-4 py-2 font-medium md:table-cell">Audience</th>
                                <th class="px-4 py-2 font-medium">Recipients</th>
                                <th class="hidden px-4 py-2 font-medium lg:table-cell">Sent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="c in props.campaigns" :key="c.id" class="border-t border-border">
                                <td class="px-4 py-2.5 font-medium">{{ c.subject }}</td>
                                <td class="hidden px-4 py-2.5 capitalize text-muted-foreground md:table-cell">{{ c.audience }}</td>
                                <td class="px-4 py-2.5 text-muted-foreground">
                                    {{ c.sent }}/{{ c.recipients }}<span v-if="c.failed" class="text-red-600"> · {{ c.failed }} failed</span>
                                </td>
                                <td class="hidden px-4 py-2.5 text-muted-foreground lg:table-cell">
                                    {{ c.sent_on }} <span v-if="c.by">· {{ c.by }}</span>
                                </td>
                            </tr>
                            <tr v-if="props.campaigns.length === 0">
                                <td colspan="4" class="px-4 py-10 text-center text-muted-foreground">
                                    <Mail class="mx-auto mb-2 size-6 opacity-50" />
                                    No campaigns sent yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
