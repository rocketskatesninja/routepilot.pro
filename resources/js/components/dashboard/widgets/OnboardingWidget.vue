<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CheckCircle2, Circle, PartyPopper } from 'lucide-vue-next';
import { computed } from 'vue';

interface Step {
    label: string;
    done: boolean;
    href: string;
}

const props = defineProps<{ data: { steps: Step[]; completed: number; total: number; complete: boolean } }>();

const pct = computed(() => (props.data.total ? Math.round((props.data.completed / props.data.total) * 100) : 0));
</script>

<template>
    <div class="flex h-full flex-col gap-3">
        <div v-if="data.complete" class="flex h-full flex-col items-center justify-center gap-2 text-center">
            <PartyPopper class="size-7 text-primary" />
            <p class="font-medium">You’re all set up.</p>
            <p class="text-xs text-muted-foreground">Remove this card from the dashboard whenever you like.</p>
        </div>

        <template v-else>
            <div class="shrink-0">
                <div class="flex items-center justify-between text-xs text-muted-foreground">
                    <span>Getting started</span>
                    <span class="tabular-nums">{{ data.completed }}/{{ data.total }}</span>
                </div>
                <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                    <div class="h-full rounded-full bg-primary transition-all" :style="{ width: pct + '%' }" />
                </div>
            </div>

            <ul class="flex flex-1 flex-col gap-1 overflow-y-auto">
                <li v-for="(s, i) in data.steps" :key="i">
                    <Link
                        :href="s.href"
                        class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-muted/60"
                        :class="s.done ? 'text-muted-foreground' : 'text-foreground'"
                    >
                        <CheckCircle2 v-if="s.done" class="size-4 shrink-0 text-emerald-500" />
                        <Circle v-else class="size-4 shrink-0 text-muted-foreground" />
                        <span :class="{ 'line-through': s.done }">{{ s.label }}</span>
                    </Link>
                </li>
            </ul>
        </template>
    </div>
</template>
