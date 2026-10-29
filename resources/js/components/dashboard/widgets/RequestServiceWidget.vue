<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import { price, soldAs } from '@/lib/portal';
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';

interface Offered {
    id: number;
    name: string;
    price: number;
    frequency: string;
}

// A few of the services on offer, cheapest first; `total` says whether there are more.
defineProps<{ data: { services: Offered[]; total: number } }>();
</script>

<template>
    <div class="flex h-full flex-col">
        <ul v-if="data.services.length" class="divide-border min-h-0 flex-1 divide-y overflow-y-auto text-sm">
            <li v-for="s in data.services" :key="s.id">
                <Link :href="`/requests?service=${s.id}`" class="hover:bg-muted/50 flex items-center justify-between gap-3 rounded-md px-1 py-2.5">
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ s.name }}</p>
                        <p class="text-muted-foreground truncate text-xs">{{ price(s.price) }} · {{ soldAs(s.frequency) }}</p>
                    </div>
                    <span class="text-primary flex shrink-0 items-center text-xs font-medium">Request <ChevronRight class="size-3.5" /></span>
                </Link>
            </li>
        </ul>
        <EmptyState v-else class="flex-1 py-6">
            Nothing to request online yet. <Link href="/requests" class="text-primary font-medium">Send a note instead</Link>
        </EmptyState>
        <Link v-if="data.total > data.services.length" href="/requests" class="text-primary border-border border-t px-1 pt-2 text-sm font-medium">
            See all {{ data.total }} services
        </Link>
    </div>
</template>
