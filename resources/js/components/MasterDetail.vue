<script setup lang="ts">
import { Sheet, SheetContent } from '@/components/ui/sheet';
import { useMediaQuery, useMounted } from '@vueuse/core';
import { computed } from 'vue';

/**
 * Persistent master-detail layout. On wide screens (xl, 1280px+) the detail is
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
        emptyText?: string;
        paneOpen?: boolean;
    }>(),
    {
        selectionKey: null,
        emptyText: 'Select an item to see details.',
        paneOpen: undefined,
    },
);

const emit = defineEmits<{ close: [] }>();

// The server can't measure the screen, so it always renders the docked pane (CSS hides
// it below xl) and the first browser render matches it; once mounted, narrow screens
// swap it for the overlay. Deciding before mount made hydration mismatch on wide screens.
const isWide = useMediaQuery('(min-width: 1280px)');
const mounted = useMounted();
const isDocked = computed(() => !mounted.value || isWide.value);
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
            class="border-border bg-background hidden shrink-0 basis-[42%] flex-col overflow-hidden rounded-xl border xl:flex xl:max-w-[580px] xl:min-w-[400px]"
        >
            <div class="min-h-0 flex-1 overflow-y-auto p-5" aria-live="polite">
                <Transition name="swap" mode="out-in">
                    <div :key="props.selectionKey ?? '__empty__'">
                        <slot v-if="props.hasSelection" name="detail" />
                        <slot v-else name="empty">
                            <div class="text-muted-foreground flex min-h-[60vh] items-center justify-center text-center text-sm">
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
        <SheetContent class="w-full overflow-y-auto sm:max-w-lg">
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
