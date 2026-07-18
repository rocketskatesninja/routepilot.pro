<script setup lang="ts">
import { Card } from '@/components/ui/card';
import { type Onboarding } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { ArrowRight, Check, Rocket, User } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{ data: Onboarding }>();

const pct = computed(() => Math.round((props.data.done / props.data.total) * 100));

// Number the required steps 1..N in order; optional steps carry no number (they
// show a person icon instead) so the sequence still reads cleanly.
const rows = computed(() => {
    let n = 0;
    return props.data.steps.map((s) => ({ ...s, num: s.optional ? null : ++n }));
});

// The "next" action is the first not-yet-done REQUIRED step — an optional step is
// never the thing standing between the tenant and a finished setup.
const nextKey = computed(() => props.data.steps.find((s) => !s.optional && !s.done)?.key ?? null);

// Dismiss writes a per-tenant TenantSetting flag; the reload drops the onboarding
// prop so the panel gates off (server no longer marks it show-able).
const dismiss = () => {
    router.post('/dashboard/onboarding/dismiss', {}, { preserveScroll: true });
};
</script>

<template>
    <Card class="overflow-hidden border-sky-500/30 bg-gradient-to-br from-sky-500/[0.07] to-orange-500/[0.05]">
        <div class="flex flex-wrap items-center gap-3 border-b border-border/60 px-4 py-3">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-sky-500/15 text-sky-600 dark:text-sky-400">
                <Rocket class="size-5" />
            </span>
            <div class="min-w-0 flex-1">
                <h2 class="text-sm font-semibold text-foreground">Getting started</h2>
                <p class="text-xs text-muted-foreground">Finish setting up RoutePilot — {{ data.done }} of {{ data.total }} done</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="hidden h-1.5 w-28 overflow-hidden rounded-full bg-border sm:block" role="progressbar" :aria-valuenow="pct">
                    <div class="h-full rounded-full bg-sky-500 transition-[width] duration-500" :style="{ width: pct + '%' }" />
                </div>
                <button
                    type="button"
                    class="shrink-0 rounded px-2 py-1 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground"
                    @click="dismiss"
                >
                    Dismiss for now
                </button>
            </div>
        </div>

        <ul class="divide-y divide-border/60">
            <li v-for="step in rows" :key="step.key">
                <Link
                    :href="step.href"
                    class="flex items-center gap-3 px-4 py-2.5 transition-colors hover:bg-muted/50"
                    :class="{ 'bg-sky-500/[0.06]': step.key === nextKey }"
                >
                    <span
                        class="flex size-6 shrink-0 items-center justify-center rounded-full border text-xs font-semibold"
                        :class="
                            step.done
                                ? 'border-emerald-500/40 bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                : step.key === nextKey
                                  ? 'border-sky-500 bg-sky-500 text-white'
                                  : 'border-border text-muted-foreground'
                        "
                    >
                        <Check v-if="step.done" class="size-3.5" />
                        <User v-else-if="step.optional" class="size-3.5" />
                        <template v-else>{{ step.num }}</template>
                    </span>
                    <span
                        class="flex flex-1 items-center gap-2 text-sm"
                        :class="step.done ? 'text-muted-foreground line-through' : 'font-medium text-foreground'"
                    >
                        {{ step.label }}
                        <span
                            v-if="step.optional"
                            class="rounded-full border border-border px-1.5 py-px text-[10px] font-medium uppercase tracking-wide text-muted-foreground no-underline"
                        >
                            Optional
                        </span>
                    </span>
                    <ArrowRight v-if="!step.done" class="size-4 shrink-0 text-muted-foreground" />
                </Link>
            </li>
        </ul>
    </Card>
</template>
