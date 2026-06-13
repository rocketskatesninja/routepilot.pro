<script setup lang="ts">
import { CalendarDays } from 'lucide-vue-next';

defineProps<{ data: { pool: string | null; date: string | null } | null }>();
</script>

<template>
    <div class="flex h-full flex-col items-center justify-center gap-1 text-center">
        <template v-if="data">
            <CalendarDays class="size-7 text-primary" />
            <div class="text-lg font-semibold">{{ data.date }}</div>
            <div class="text-sm text-muted-foreground">{{ data.pool }}</div>
        </template>
        <div v-else class="text-sm text-muted-foreground">No upcoming visit scheduled.</div>
    </div>
</template>
