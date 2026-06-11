<script setup lang="ts">
import { Sheet, SheetContent } from '@/components/ui/sheet';
import { useMediaQuery } from '@vueuse/core';
import { computed } from 'vue';

/**
 * Persistent master-detail layout. On wide screens (>= `query`) the detail is
 * docked on the right as an always-present pane (with an empty state); below
 * that it falls back to an overlay Sheet. The `#detail` slot is authored once
 * and rendered into whichever host is live — no markup duplication. The content
 * swap animates on `selectionKey` change so navigating item-to-item glides.
 *
 * The pane is intentionally NOT a modal: no focus trap, no backdrop, and it must
 * not steal focus (that would hijack keyboard list navigation).
 */
const props = withDefaults(
    defineProps<{
        hasSelection: boolean;
        selectionKey?: string | number | null;
        query?: string;
        emptyText?: string;
        paneOpen?: boolean;
    }>(),
    {
        selectionKey: null,
        query: '(min-width: 1280px)',
        emptyText: 'Select an item to see details.',
        paneOpen: undefined,
    },
);

const emit = defineEmits<{ close: [] }>();

const isDocked = useMediaQuery(props.query);
const overlayOpen = computed(() => !isDocked.value && props.hasSelection && (props.paneOpen ?? true));
</script>

<template>
    <div class="flex min-h-0 flex-1 gap-4">
        <!-- master (list): grows, can shrink, scrolls on its own -->
        <div class="flex min-w-0 flex-1 flex-col">
            <slot name="list" />
        </div>

        <!-- detail (docked, xl+): clamped-width landmark, NOT a modal -->
        <aside
            v-if="isDocked"
            aria-label="Detail"
            class="hidden shrink-0 basis-1/3 flex-col overflow-hidden rounded-xl border border-border bg-card xl:flex xl:min-w-[340px] xl:max-w-[460px]"
        >
            <div class="min-h-0 flex-1 overflow-y-auto p-4" aria-live="polite">
                <Transition name="swap" mode="out-in">
                    <div :key="props.selectionKey ?? '__empty__'">
                        <slot v-if="props.hasSelection" name="detail" />
                        <slot v-else name="empty">
                            <div class="flex min-h-[60vh] items-center justify-center text-center text-sm text-muted-foreground">
                                {{ props.emptyText }}
                            </div>
                        </slot>
                    </div>
                </Transition>
            </div>
        </aside>
    </div>

    <!-- detail (overlay, below xl): same #detail slot, today's behavior -->
    <Sheet v-if="!isDocked" :open="overlayOpen" @update:open="(o: boolean) => !o && emit('close')">
        <SheetContent class="w-full overflow-y-auto sm:max-w-md">
            <Transition name="swap">
                <div :key="props.selectionKey ?? '__empty__'">
                    <slot name="detail" />
                </div>
            </Transition>
        </SheetContent>
    </Sheet>
</template>

<style scoped>
.swap-enter-active,
.swap-leave-active {
    transition:
        opacity 180ms ease,
        transform 180ms ease;
}
.swap-enter-from {
    opacity: 0;
    transform: translateX(8px);
}
.swap-leave-to {
    opacity: 0;
    transform: translateX(-8px);
}
@media (prefers-reduced-motion: reduce) {
    .swap-enter-from,
    .swap-leave-to {
        transform: none;
    }
}
</style>
