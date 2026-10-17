<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Plain facts as calm rows in one rounded box — label on the left, value on the
 * right. Empty values are left out rather than shown as dashes.
 */
export interface Fact {
    label: string;
    value: string | number | null | undefined;
    /** Internal page link, or a mailto: / tel: / http link (opened as a plain anchor). */
    href?: string | null;
}

const props = defineProps<{ facts: Fact[] }>();
const shown = computed(() => props.facts.filter((f) => f.value !== null && f.value !== undefined && f.value !== ''));
const external = (href: string) => /^(mailto:|tel:|https?:)/.test(href);
</script>

<template>
    <dl v-if="shown.length" class="border-border divide-border divide-y rounded-2xl border">
        <div v-for="f in shown" :key="f.label" class="flex items-baseline justify-between gap-4 px-4 py-2.5 text-sm">
            <dt class="text-muted-foreground shrink-0">{{ f.label }}</dt>
            <dd class="min-w-0 truncate text-right font-medium">
                <a v-if="f.href && external(f.href)" :href="f.href" class="text-primary underline-offset-4 hover:underline">{{ f.value }}</a>
                <Link v-else-if="f.href" :href="f.href" class="text-primary underline-offset-4 hover:underline">{{ f.value }}</Link>
                <template v-else>{{ f.value }}</template>
            </dd>
        </div>
    </dl>
</template>
