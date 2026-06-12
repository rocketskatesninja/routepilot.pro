<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Inbox } from 'lucide-vue-next';

interface ServiceRequest {
    id: number;
    type: string;
    message: string;
    status: string;
    pool: string | null;
    preferred_date: string | null;
    on: string | null;
}

const props = defineProps<{
    requests: ServiceRequest[];
    pools: { id: number; name: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Requests', href: '/requests' }];

const form = useForm<{ type: string; pool_id: number | string; message: string; preferred_date: string }>({
    type: 'service',
    pool_id: '',
    message: '',
    preferred_date: '',
});

function submit() {
    form.post('/requests', { preserveScroll: true, onSuccess: () => form.reset() });
}
</script>

<template>
    <Head title="Requests" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-5 p-4">
            <!-- new request -->
            <form class="space-y-4 rounded-xl border border-border p-4 text-sm" @submit.prevent="submit">
                <h2 class="font-medium">Ask your service company</h2>
                <div class="grid gap-1.5">
                    <Label for="type">What do you need?</Label>
                    <select id="type" v-model="form.type" class="h-9 rounded-md border border-input bg-background px-2 text-sm">
                        <option value="service">Request a new / extra service</option>
                        <option value="hold">Request a vacation hold</option>
                    </select>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="pool">Pool (optional)</Label>
                        <select id="pool" v-model="form.pool_id" class="h-9 rounded-md border border-input bg-background px-2 text-sm">
                            <option value="">Any / all</option>
                            <option v-for="p in props.pools" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="date">Preferred date (optional)</Label>
                        <Input id="date" v-model="form.preferred_date" type="date" />
                        <p v-if="form.errors.preferred_date" class="text-xs text-red-600">{{ form.errors.preferred_date }}</p>
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label for="message">Details</Label>
                    <textarea
                        id="message"
                        v-model="form.message"
                        rows="3"
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        placeholder="Tell us what you need…"
                    ></textarea>
                    <p v-if="form.errors.message" class="text-xs text-red-600">{{ form.errors.message }}</p>
                </div>
                <Button type="submit" :disabled="form.processing">Send request</Button>
            </form>

            <!-- history -->
            <div class="overflow-hidden rounded-xl border border-border">
                <h2 class="border-b border-border px-4 py-2 font-medium">Your requests</h2>
                <ul class="divide-y divide-border text-sm">
                    <li v-for="r in props.requests" :key="r.id" class="flex items-start justify-between gap-3 px-4 py-2.5">
                        <div>
                            <span class="font-medium capitalize">{{ r.type === 'hold' ? 'Vacation hold' : 'New service' }}</span>
                            <span v-if="r.pool" class="text-muted-foreground"> · {{ r.pool }}</span>
                            <p class="text-muted-foreground">{{ r.message }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ r.on }}<span v-if="r.preferred_date"> · prefers {{ r.preferred_date }}</span>
                            </p>
                        </div>
                        <span
                            class="rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                            :class="
                                r.status === 'resolved'
                                    ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                    : 'bg-amber-500/15 text-amber-600 dark:text-amber-400'
                            "
                        >
                            {{ r.status }}
                        </span>
                    </li>
                    <li v-if="props.requests.length === 0" class="px-4 py-8 text-center text-muted-foreground">
                        <Inbox class="mx-auto mb-2 size-6 opacity-50" />
                        No requests yet.
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
