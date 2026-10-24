<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import Chip from '@/components/pane/Chip.vue';
import { chemicalLink } from '@/lib/links';
import { formatAmount } from '@/lib/utils';
import { Link } from '@inertiajs/vue3';

interface Chemical {
    id: number;
    name: string;
    unit: string;
    stock: number;
    reorder_at: number;
}

// `tracked`: how many chemicals the company keeps in Inventory, so an empty list can say why.
defineProps<{ data: { items: Chemical[]; tracked: number } }>();
</script>

<template>
    <div class="h-full overflow-y-auto">
        <ul v-if="data.items.length" class="divide-border divide-y text-sm">
            <li v-for="c in data.items" :key="c.id">
                <Link :href="chemicalLink(c.id)" class="hover:bg-muted/50 flex items-center justify-between gap-3 rounded-md px-1 py-2.5">
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ c.name }}</p>
                        <p class="text-muted-foreground truncate text-xs">Reorder at {{ formatAmount(c.reorder_at, c.unit) }}</p>
                    </div>
                    <Chip v-if="c.stock <= 0" tone="bad">Out</Chip>
                    <span v-else class="shrink-0 text-sm font-medium text-amber-700 dark:text-amber-400"
                        >{{ formatAmount(c.stock, c.unit) }} left</span
                    >
                </Link>
            </li>
        </ul>
        <EmptyState v-else-if="data.tracked" class="py-6">Nothing's running low.</EmptyState>
        <EmptyState v-else class="py-6">
            No chemicals tracked yet. <Link href="/inventory" class="text-primary font-medium">Add them in Inventory</Link>
        </EmptyState>
    </div>
</template>
