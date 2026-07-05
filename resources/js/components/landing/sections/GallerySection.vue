<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { galleryItem, galleryRow } from '../gridLayout';
import SectionShell from '../primitives/SectionShell.vue';
import type { SectionProps } from '../types';

const props = defineProps<SectionProps>();
const c = computed(() => props.content as { heading?: string });
const photos = computed(() => props.live.gallery ?? []);

const lightbox = ref<number | null>(null);
</script>

<template>
    <SectionShell
        v-if="photos.length || editing"
        id="gallery"
        :heading="c.heading || 'Recent work'"
        subheading="See the quality our team delivers, every visit"
    >
        <div v-if="photos.length" class="stagger-children reveal" :class="galleryRow">
            <button
                v-for="(p, i) in photos"
                :key="i"
                type="button"
                class="group relative aspect-square overflow-hidden rounded-xl border border-border"
                :class="galleryItem(photos.length)"
                @click="lightbox = i"
            >
                <img
                    :src="p.url"
                    :alt="p.caption || 'Recent pool service work'"
                    loading="lazy"
                    class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110"
                />
                <span
                    v-if="p.caption"
                    class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-2 text-left text-xs text-white opacity-0 transition-opacity group-hover:opacity-100"
                    >{{ p.caption }}</span
                >
            </button>
        </div>
        <p v-else class="rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
            Feature photos from completed visits to fill this gallery.
        </p>
    </SectionShell>

    <!-- Lightbox — client-only (lightbox is null during SSR). -->
    <div v-if="lightbox !== null" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/90 p-4" @click="lightbox = null">
        <button type="button" class="absolute right-4 top-4 text-white/80 hover:text-white" aria-label="Close" @click="lightbox = null">
            <X class="size-7" />
        </button>
        <img
            :src="photos[lightbox].url"
            :alt="photos[lightbox].caption || ''"
            class="max-h-[90vh] max-w-full rounded-lg object-contain"
            @click.stop
        />
    </div>
</template>
