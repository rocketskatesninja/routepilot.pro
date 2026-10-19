<script setup lang="ts">
import EntityAvatar from '@/components/EntityAvatar.vue';
import { visitStatusClass } from '@/lib/statusColors';
import { GripVertical, SkipForward, Undo2 } from 'lucide-vue-next';

/**
 * One stop on a Schedule card (a tech's route or the unassigned bucket). Pool name on its
 * own line; the customer shares the next with the status, which never shrinks; then the
 * service. A click on the row (falls through to the <li>) focuses the map.
 */
defineProps<{
    stop: { pool: string | null; pool_photo: string | null; customer: string | null; service: string | null; status: string };
    number: number;
    focused: boolean;
    /** Show the drag handle on a stop that's still due. */
    movable: boolean;
    /** Show skip / unskip. */
    skippable: boolean;
}>();

defineEmits<{ skip: []; unskip: [] }>();
</script>

<template>
    <li
        class="flex cursor-pointer items-center gap-2 px-4 py-2 transition-colors"
        :class="focused ? 'bg-primary/10 ring-primary/40 ring-1 ring-inset' : 'hover:bg-muted/40'"
    >
        <GripVertical
            v-if="movable && stop.status === 'pending'"
            class="drag-handle text-muted-foreground size-4 shrink-0 cursor-grab active:cursor-grabbing"
        />
        <span v-else class="size-4 shrink-0" aria-hidden="true" />
        <span class="text-muted-foreground shrink-0">{{ number }}.</span>
        <!-- No photo on a phone: the room goes to the names. -->
        <span class="hidden shrink-0 sm:block"><EntityAvatar :src="stop.pool_photo" type="pool" :name="stop.pool" size="sm" /></span>
        <div class="min-w-0 flex-1">
            <p class="truncate">{{ stop.pool }}</p>
            <div class="flex items-center gap-2 text-xs">
                <span class="text-muted-foreground min-w-0 flex-1 truncate">{{ stop.customer }}</span>
                <span class="shrink-0 rounded-full px-2 py-0.5 font-medium whitespace-nowrap capitalize" :class="visitStatusClass(stop.status)">{{
                    stop.status.replace('_', ' ')
                }}</span>
            </div>
            <p v-if="stop.service" class="text-muted-foreground truncate text-xs">{{ stop.service }}</p>
        </div>
        <button
            v-if="skippable && stop.status === 'pending'"
            class="text-muted-foreground hover:bg-muted shrink-0 rounded p-2 hover:text-amber-600 sm:p-1"
            title="Skip stop"
            @click.stop="$emit('skip')"
        >
            <SkipForward class="size-3.5" />
        </button>
        <button
            v-if="skippable && stop.status === 'skipped'"
            class="text-muted-foreground hover:bg-muted shrink-0 rounded p-2 hover:text-emerald-600 sm:p-1"
            title="Unskip stop"
            @click.stop="$emit('unskip')"
        >
            <Undo2 class="size-3.5" />
        </button>
    </li>
</template>
