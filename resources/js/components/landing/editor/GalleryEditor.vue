<script setup lang="ts">
import EditorField from '@/components/landing/editor/EditorField.vue';
import { Input } from '@/components/ui/input';
import { router } from '@inertiajs/vue3';
import { Star } from '@lucide/vue';

export interface RecentPhoto {
    id: number;
    url: string;
    is_showcase: boolean;
}

/** The landing editor's gallery panel: how many photos, and which visit photos to feature. */
const props = defineProps<{ section: { limit: number }; photos: RecentPhoto[] }>();
const s = props.section;

// Featuring a photo saves at once (it's live data, not part of the config). The tile flips
// straight away and back if the save fails; `async` keeps a quick run of taps from
// cancelling each other.
function toggleShowcase(p: RecentPhoto) {
    const next = !p.is_showcase;
    p.is_showcase = next;
    router.post(
        `/photos/${p.id}/showcase`,
        { is_showcase: next },
        { async: true, preserveScroll: true, preserveState: true, onError: () => (p.is_showcase = !next) },
    );
}
</script>

<template>
    <EditorField label="Max photos"><Input v-model.number="s.limit" type="number" min="1" max="24" class="w-24" /></EditorField>
    <EditorField label="Featured photos" hint="Tap a photo to feature it on your site.">
        <p v-if="!photos.length" class="text-muted-foreground text-xs">
            No visit photos yet. Photos your team takes on visits show up here to feature.
        </p>
        <div v-else class="grid grid-cols-4 gap-2">
            <button
                v-for="p in photos"
                :key="p.id"
                type="button"
                :aria-pressed="p.is_showcase"
                class="relative aspect-square overflow-hidden rounded-md border-2"
                :class="p.is_showcase ? 'border-primary' : 'border-border'"
                @click="toggleShowcase(p)"
            >
                <img :src="p.url" alt="" class="h-full w-full object-cover" :class="p.is_showcase ? '' : 'opacity-60'" />
                <span v-if="p.is_showcase" class="bg-primary text-primary-foreground absolute top-1 right-1 rounded-full p-0.5"
                    ><Star class="size-3"
                /></span>
            </button>
        </div>
    </EditorField>
</template>
