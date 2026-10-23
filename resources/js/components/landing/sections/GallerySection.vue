<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { balancedCols } from '../layout';
import SectionShell from '../primitives/SectionShell.vue';
import type { SectionProps } from '../types';

const props = defineProps<SectionProps>();
const photos = computed(() => props.live.gallery ?? []);
const cols = computed(() => balancedCols(photos.value.length, 4));

// Client-only (null during SSR). Escape closes it, as does a click anywhere.
const lightbox = ref<number | null>(null);
const onKey = (e: KeyboardEvent) => e.key === 'Escape' && (lightbox.value = null);
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <SectionShell :section="content" layout="stack">
        <ul
            v-if="photos.length"
            class="grid grid-cols-2 gap-2 @3xl:grid-cols-[repeat(var(--cols),minmax(0,1fr))] @3xl:gap-3"
            :style="{ '--cols': cols }"
        >
            <li v-for="(p, i) in photos" :key="i" :class="photos.length === 1 ? 'col-span-2' : ''">
                <button
                    type="button"
                    class="focus-visible:ring-primary block w-full overflow-hidden rounded-xl focus-visible:ring-3 focus-visible:outline-none"
                    :class="photos.length === 1 ? 'aspect-[16/9]' : 'aspect-square'"
                    :aria-label="p.caption ? `View photo: ${p.caption}` : 'View photo'"
                    @click="lightbox = i"
                >
                    <img :src="p.url" :alt="p.caption || 'Recent pool service work'" loading="lazy" class="h-full w-full object-cover" />
                </button>
            </li>
        </ul>
        <p v-else class="border-border text-muted-foreground rounded-xl border border-dashed p-6 text-sm">
            Feature photos from completed visits to fill this gallery.
        </p>
    </SectionShell>

    <div
        v-if="lightbox !== null"
        class="fixed inset-0 z-[60] flex flex-col items-center justify-center gap-4 bg-black/90 p-4"
        @click="lightbox = null"
    >
        <button type="button" class="absolute top-4 right-4 text-white/80 hover:text-white" aria-label="Close" @click="lightbox = null">
            <X class="size-7" />
        </button>
        <img
            :src="photos[lightbox].url"
            :alt="photos[lightbox].caption || ''"
            class="max-h-[85vh] max-w-full rounded-lg object-contain"
            @click.stop
        />
        <p v-if="photos[lightbox].caption" class="text-center text-white/80">{{ photos[lightbox].caption }}</p>
    </div>
</template>
