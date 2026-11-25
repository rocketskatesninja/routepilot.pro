<script setup lang="ts">
import { flip } from '@/components/landing/editor/choice';
import EditorField from '@/components/landing/editor/EditorField.vue';
import Segmented from '@/components/landing/editor/Segmented.vue';
import { ALIGN_OPTIONS } from '@/components/landing/meta';
import { STAT_METRICS, statOverrides } from '@/components/landing/stats';
import type { LiveData, SectionConfig } from '@/components/landing/types';
import { Input } from '@/components/ui/input';

/** The landing editor's numbers panel: which counts to show, and a company's own number in place of a live one. */
const props = defineProps<{ section: SectionConfig & { align: string; metrics: string[] }; live: LiveData }>();
const s = props.section;

// A stat's own number: blank (or not a number) = the live count, which Reset also goes back to.
function setOverride(key: string, raw: string | number) {
    const n = Number.parseInt(String(raw), 10);
    if (Number.isNaN(n) || n < 0) delete statOverrides(s)[key];
    else statOverrides(s)[key] = n;
}
</script>

<template>
    <p class="text-muted-foreground text-xs">
        Tick the numbers to show. Each shows your live count unless you type your own; Reset goes back to the live count. A number at 0 stays hidden.
    </p>
    <EditorField label="Alignment"><Segmented v-model="s.align" :options="ALIGN_OPTIONS" /></EditorField>
    <ul class="grid gap-2">
        <li v-for="m in STAT_METRICS" :key="m.key" class="flex items-center gap-2">
            <label class="flex min-w-0 flex-1 items-center gap-2">
                <input type="checkbox" :checked="s.metrics.includes(m.key)" @change="flip(s.metrics, m.key)" />
                <span class="truncate">{{ m.label }}</span>
            </label>
            <Input
                type="number"
                min="0"
                inputmode="numeric"
                class="h-8 w-28"
                :aria-label="`Your own number for ${m.label}`"
                :placeholder="`${live.stats?.[m.key] ?? 0} live`"
                :model-value="statOverrides(s)[m.key] ?? ''"
                @update:model-value="(v) => setOverride(m.key, v)"
            />
            <button
                type="button"
                class="text-muted-foreground hover:text-foreground w-10 text-xs disabled:invisible"
                :disabled="statOverrides(s)[m.key] === undefined"
                @click="setOverride(m.key, '')"
            >
                Reset
            </button>
        </li>
    </ul>
</template>
