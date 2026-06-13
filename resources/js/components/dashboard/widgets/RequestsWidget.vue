<script setup lang="ts">
import EntityAvatar from '@/components/EntityAvatar.vue';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';

interface Req {
    id: number;
    type: string;
    message: string | null;
    customer: string | null;
    customer_photo: string | null;
    pool: string | null;
    preferred_date: string | null;
    on: string | null;
}

defineProps<{ data: Req[] }>();

const resolveRequest = (id: number) => router.post(`/requests/${id}/resolve`, {}, { preserveScroll: true });
</script>

<template>
    <div class="h-full overflow-y-auto">
        <ul v-if="data.length" class="divide-y divide-border text-sm">
            <li v-for="r in data" :key="r.id" class="flex items-start justify-between gap-3 px-1 py-2.5">
                <div class="min-w-0">
                    <span
                        class="rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                        :class="
                            r.type === 'hold'
                                ? 'bg-sky-500/15 text-sky-600 dark:text-sky-400'
                                : 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                        "
                    >
                        {{ r.type === 'hold' ? 'Vacation hold' : 'New service' }}
                    </span>
                    <span class="ml-2 inline-flex items-center gap-1.5 align-middle font-medium"
                        ><EntityAvatar :src="r.customer_photo" type="person" :name="r.customer" size="sm" shape="circle" />{{ r.customer }}</span
                    >
                    <span v-if="r.pool" class="text-muted-foreground"> · {{ r.pool }}</span>
                    <p v-if="r.message" class="mt-0.5 text-muted-foreground">{{ r.message }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ r.on }}<span v-if="r.preferred_date"> · prefers {{ r.preferred_date }}</span>
                    </p>
                </div>
                <Button size="sm" variant="outline" @click="resolveRequest(r.id)">Resolve</Button>
            </li>
        </ul>
        <div v-else class="flex h-full items-center justify-center py-6 text-center text-sm text-muted-foreground">No pending requests.</div>
    </div>
</template>
