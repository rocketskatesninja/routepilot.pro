<script setup lang="ts">
import Chip from '@/components/pane/Chip.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Link } from '@inertiajs/vue3';

interface TenantRow {
    id: number;
    name: string;
    status: string | null;
    logo: string | null;
}

defineProps<{ data: TenantRow[] }>();
</script>

<template>
    <div class="h-full overflow-y-auto">
        <ul v-if="data.length" class="divide-border divide-y text-sm">
            <li v-for="t in data" :key="t.id">
                <Link
                    :href="`/people?selected=${t.id}&selected_type=tenant`"
                    class="hover:bg-muted/40 flex items-center justify-between gap-2 rounded px-1 py-2 transition-colors"
                >
                    <span class="flex min-w-0 items-center gap-2">
                        <span class="bg-muted flex size-7 shrink-0 items-center justify-center overflow-hidden rounded-md text-xs font-semibold">
                            <img v-if="t.logo" :src="t.logo" alt="" class="size-full object-cover" />
                            <span v-else>{{ t.name.charAt(0) }}</span>
                        </span>
                        <span class="truncate" :title="t.name">{{ t.name }}</span>
                    </span>
                    <Chip :tone="t.status === 'active' ? 'good' : 'muted'" class="capitalize">{{ t.status }}</Chip>
                </Link>
            </li>
        </ul>
        <EmptyState v-else class="p-0">No tenants yet.</EmptyState>
    </div>
</template>
