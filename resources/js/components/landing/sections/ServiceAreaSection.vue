<script setup lang="ts">
import { MapPin } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import SectionShell from '../primitives/SectionShell.vue';
import type { SectionProps } from '../types';

const props = defineProps<SectionProps>();
const c = computed(() => props.content as { heading?: string; radius_label?: string });
const area = computed(() => props.live.serviceArea);
const mapsKey = computed(() => props.live.mapsKey ?? null);

const hasCoords = computed(() => typeof area.value?.lat === 'number' && typeof area.value?.lng === 'number');
const canMap = computed(() => hasCoords.value && !!mapsKey.value);
const subheading = computed(() => area.value?.radiusLabel || c.value.radius_label || 'Proudly serving your neighborhood');

const mapEl = ref<HTMLElement | null>(null);

type Ctor = new (...args: unknown[]) => unknown;
interface GoogleMaps {
    maps: { Map: Ctor; Marker: Ctor; Circle: Ctor };
}

function loadMaps(key: string): Promise<void> {
    const w = window as unknown as { google?: GoogleMaps; __rpMapsPromise?: Promise<void> };
    if (w.google?.maps) {
        return Promise.resolve();
    }
    if (w.__rpMapsPromise) {
        return w.__rpMapsPromise;
    }
    w.__rpMapsPromise = new Promise<void>((resolve, reject) => {
        const s = document.createElement('script');
        s.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}`;
        s.async = true;
        s.onload = () => resolve();
        s.onerror = () => reject(new Error('Google Maps failed to load'));
        document.head.appendChild(s);
    });
    return w.__rpMapsPromise;
}

// Client-only: the JS map is initialized after mount; SSR renders the address
// + container only (no window/document at setup).
onMounted(async () => {
    if (!canMap.value || !mapEl.value || !area.value) {
        return;
    }
    try {
        await loadMaps(mapsKey.value as string);
        const g = (window as unknown as { google?: GoogleMaps }).google;
        if (!g) {
            return;
        }
        const m = g.maps;
        const center = { lat: area.value.lat as number, lng: area.value.lng as number };
        const brand = props.brand.color || '#0ea5e9';
        const map = new m.Map(mapEl.value, { center, zoom: 11, disableDefaultUI: true, zoomControl: true, gestureHandling: 'cooperative' });
        const layers: unknown[] = [];
        layers.push(new m.Marker({ position: center, map }));
        layers.push(
            new m.Circle({
                map,
                center,
                radius: 16000,
                strokeColor: brand,
                strokeOpacity: 0.6,
                strokeWeight: 2,
                fillColor: brand,
                fillOpacity: 0.12,
            }),
        );
    } catch {
        /* leave the fallback container in place */
    }
});
</script>

<template>
    <SectionShell
        v-if="hasCoords || area?.formattedAddress || editing"
        id="service_area"
        tinted
        :heading="c.heading || 'Where we serve'"
        :subheading="subheading"
    >
        <div class="grid gap-6 md:grid-cols-2 md:items-center">
            <div class="reveal">
                <div
                    class="inline-flex size-12 items-center justify-center rounded-xl"
                    style="background: color-mix(in srgb, hsl(var(--brand, 199 89% 48%)) 14%, transparent)"
                >
                    <MapPin class="size-6 text-primary" />
                </div>
                <p v-if="area?.formattedAddress" class="mt-4 text-lg font-medium text-foreground">{{ area.formattedAddress }}</p>
                <p class="mt-2 text-muted-foreground">{{ subheading }}</p>
            </div>
            <div class="reveal">
                <div v-if="canMap" ref="mapEl" class="h-72 w-full overflow-hidden rounded-xl border border-border shadow-sm"></div>
                <div
                    v-else
                    class="flex h-72 w-full items-center justify-center rounded-xl border border-dashed border-border bg-muted/40 px-6 text-center text-sm text-muted-foreground"
                >
                    Set a business address in Company settings to show an interactive service-area map here.
                </div>
            </div>
        </div>
    </SectionShell>
</template>
