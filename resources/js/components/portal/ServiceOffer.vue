<script setup lang="ts">
import { extras, iconFor, price, soldAs } from '@/lib/portal';
import { Check, Clock } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * A service the way customers see it in the portal's price list: what it is,
 * what the tech does, the price and how often. Staff see the same card as a
 * preview, so it can't drift from the real thing. The #action slot holds the
 * request button in the portal. Lays itself out by its own width, not the
 * screen's, so it fits a side panel too.
 */
export interface Offer {
    name: string;
    description: string | null;
    frequency: string;
    price: number;
    minutes: number | null;
    /** The checklist — only when the tech works through one on the visit. */
    tasks: string[];
    /** Visit sections that are on (tasks / chemistry / treatments / photos). */
    modules: string[];
}

const props = defineProps<{ service: Offer }>();

// Long checklists open on request.
const expanded = ref(false);
const longList = computed(() => props.service.tasks.length > 6);
const shownTasks = computed(() => (longList.value && !expanded.value ? props.service.tasks.slice(0, 4) : props.service.tasks));
const included = computed(() => extras(props.service.modules));
</script>

<template>
    <div class="@container">
        <div class="grid gap-5 @lg:grid-cols-[1fr_11rem]">
            <div class="flex gap-4">
                <span class="text-primary grid size-11 shrink-0 place-items-center rounded-xl bg-[hsl(var(--brand)/0.1)]">
                    <component :is="iconFor(service.modules, service.frequency)" class="size-5" />
                </span>
                <div class="min-w-0">
                    <h3 class="font-display text-lg leading-snug font-semibold">{{ service.name }}</h3>
                    <p v-if="service.description" class="text-muted-foreground mt-1 max-w-[60ch] text-[15px]">{{ service.description }}</p>

                    <ul v-if="service.tasks.length || included.length" class="mt-3 grid gap-x-6 gap-y-1.5 text-sm @md:grid-cols-2">
                        <li v-for="t in shownTasks" :key="`t-${t}`" class="flex gap-2">
                            <Check class="text-primary mt-0.5 size-4 shrink-0" />{{ t }}
                        </li>
                        <li v-for="x in included" :key="`x-${x}`" class="flex gap-2"><Check class="text-primary mt-0.5 size-4 shrink-0" />{{ x }}</li>
                    </ul>
                    <button
                        v-if="longList"
                        type="button"
                        class="text-primary focus-visible:ring-ring mt-2 rounded text-sm font-medium underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:outline-none"
                        @click="expanded = !expanded"
                    >
                        {{ expanded ? 'Show less' : `Show all ${service.tasks.length} steps` }}
                    </button>
                </div>
            </div>

            <div class="flex flex-wrap items-end justify-between gap-3 @lg:flex-col @lg:items-end @lg:justify-start @lg:text-right">
                <div>
                    <p>
                        <span class="font-display text-[1.75rem] leading-none font-semibold tracking-tight">{{ price(service.price) }}</span>
                        <span class="text-muted-foreground ml-1 text-sm">{{ service.frequency === 'one_time' ? 'for the job' : 'per visit' }}</span>
                    </p>
                    <p class="text-muted-foreground mt-1.5 text-sm">{{ soldAs(service.frequency) }}</p>
                    <p v-if="service.minutes" class="text-muted-foreground flex items-center gap-1 text-sm @lg:justify-end">
                        <Clock class="size-3.5" /> About {{ service.minutes }} minutes
                    </p>
                </div>
                <div v-if="$slots.action" class="@lg:mt-1">
                    <slot name="action" />
                </div>
            </div>
        </div>
    </div>
</template>
