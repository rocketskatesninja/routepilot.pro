<script setup lang="ts">
import { DARK_MAP_STYLE, isDarkMode, loadGoogleMaps } from '@/composables/useGoogleMap';
import { MapPin } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { frame } from '../layout';
import { sectionHeading } from '../meta';
import SectionShell from '../primitives/SectionShell.vue';
import type { SectionProps } from '../types';

const props = defineProps<SectionProps>();
const area = computed(() => props.live.serviceArea);
const mapsKey = computed(() => props.live.mapsKey ?? null);

const coverage = computed(() => (props.content.radius_label as string | null) || 'Proudly serving your neighborhood');
const canMap = computed(() => typeof area.value?.lat === 'number' && typeof area.value?.lng === 'number' && !!mapsKey.value);

const mapEl = ref<HTMLElement | null>(null);

// Client-only: the map starts after mount; SSR renders the card and an empty map box.
onMounted(async () => {
    if (!canMap.value || !mapEl.value || !area.value) {
        return;
    }
    try {
        const g = await loadGoogleMaps(mapsKey.value as string);
        const m = g.maps;
        const center = { lat: area.value.lat as number, lng: area.value.lng as number };
        const brand = props.brand.color || '#0ea5e9';
        const map = new m.Map(mapEl.value, {
            center,
            zoom: 11,
            disableDefaultUI: true,
            zoomControl: true,
            gestureHandling: 'cooperative',
            styles: isDarkMode() ? DARK_MAP_STYLE : undefined,
        });
        new m.Marker({ position: center, map });
        new m.Circle({ map, center, radius: 16000, strokeColor: brand, strokeOpacity: 0.6, strokeWeight: 2, fillColor: brand, fillOpacity: 0.12 });
    } catch {
        /* leave the empty map box; the card still says where they serve */
    }
});
</script>

<template>
    <!-- With a map: it runs edge to edge, with the card over it (below it on a phone). -->
    <section v-if="canMap" id="service_area" class="relative scroll-mt-16">
        <div ref="mapEl" class="bg-muted h-[24rem] w-full @5xl:h-[34rem]"></div>
        <div class="@5xl:pointer-events-none @5xl:absolute @5xl:inset-0 @5xl:flex @5xl:items-center" :class="frame">
            <div
                class="border-border bg-background relative -mt-10 rounded-2xl border p-8 shadow-xl @5xl:pointer-events-auto @5xl:mt-0 @5xl:max-w-md"
            >
                <h2 class="site-heading text-foreground">{{ sectionHeading(content) }}</h2>
                <p v-if="area?.formattedAddress" class="text-foreground mt-5 flex items-start gap-2 text-lg font-medium">
                    <MapPin class="text-primary mt-1 size-5 shrink-0" /> {{ area.formattedAddress }}
                </p>
                <p class="text-muted-foreground mt-2">{{ coverage }}</p>
            </div>
        </div>
    </section>

    <!-- No map (no coordinates or no Maps key): just where they are. -->
    <SectionShell v-else :section="content" tinted>
        <p v-if="area?.formattedAddress" class="text-foreground flex items-start gap-2 text-xl font-medium">
            <MapPin class="text-primary mt-1 size-5 shrink-0" /> {{ area.formattedAddress }}
        </p>
        <p class="text-muted-foreground mt-2 text-lg">{{ coverage }}</p>
        <p v-if="editing" class="border-border text-muted-foreground mt-8 rounded-xl border border-dashed p-6 text-sm">
            Set a business address in Company settings to show an interactive service-area map here.
        </p>
    </SectionShell>
</template>
