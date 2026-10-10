<script setup lang="ts">
import { CalendarDays } from 'lucide-vue-next';

defineProps<{ data: { pool: string | null; date: string | null } | null }>();
</script>

<template>
    <div class="flex h-full flex-col items-center justify-center gap-1 text-center">
        <template v-if="data">
            <CalendarDays class="text-primary size-7" />
            <div class="text-lg font-semibold">{{ data.date }}</div>
            <div class="text-muted-foreground text-sm">{{ data.pool }}</div>
        </template>
        <div v-else class="text-muted-foreground text-sm">No upcoming visit scheduled.</div>
    </div>
</template>
