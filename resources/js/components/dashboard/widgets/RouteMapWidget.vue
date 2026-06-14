<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import { DARK_MAP_STYLE, isDarkMode, loadGoogleMaps } from '@/composables/useGoogleMap';
import { MapPin } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface StopMarker {
    lat: number;
    lng: number;
    order: number;
    pool: string | null;
    status: string;
    agent: string | null;
    color: string;
}
interface RouteMapData {
    maps_key: string | null;
    hq: { lat: number; lng: number; label: string | null } | null;
    markers: StopMarker[];
}

const props = defineProps<{ data: RouteMapData }>();

const canMap = computed(() => !!props.data.maps_key && (props.data.markers.length > 0 || props.data.hq !== null));
const mapEl = ref<HTMLElement | null>(null);

onMounted(async () => {
    if (!canMap.value || !mapEl.value || !props.data.maps_key) {
        return;
    }
    try {
        const g = await loadGoogleMaps(props.data.maps_key);
        const m = g.maps;

        const points = [
            ...props.data.markers.map((mk) => ({ lat: mk.lat, lng: mk.lng })),
            ...(props.data.hq ? [{ lat: props.data.hq.lat, lng: props.data.hq.lng }] : []),
        ];
        const single = points.length === 1;

        const opts: Record<string, unknown> = { disableDefaultUI: true, zoomControl: true, gestureHandling: 'cooperative' };
        if (isDarkMode()) {
            opts.styles = DARK_MAP_STYLE;
        }
        if (single) {
            opts.center = points[0];
            opts.zoom = 13;
        }
        const map = new m.Map(mapEl.value, opts);
        const bounds = new m.LatLngBounds();

        // The day's stops: numbered pins, colored by their agent.
        const byAgent: Record<string, StopMarker[]> = {};
        for (const mk of props.data.markers) {
            const position = { lat: mk.lat, lng: mk.lng };
            new m.Marker({
                position,
                map,
                title: `${mk.order}. ${mk.pool ?? 'Stop'}${mk.agent ? ` — ${mk.agent}` : ''}`,
                label: { text: String(mk.order), color: '#ffffff', fontSize: '11px', fontWeight: '600' },
                icon: { path: m.SymbolPath.CIRCLE, scale: 12, fillColor: mk.color, fillOpacity: 1, strokeColor: '#ffffff', strokeWeight: 2 },
            });
            bounds.extend(position);
            (byAgent[mk.agent ?? '__unassigned'] ??= []).push(mk);
        }

        // One polyline per agent, threading their stops in visit order.
        for (const stops of Object.values(byAgent)) {
            if (stops.length < 2) {
                continue;
            }
            new m.Polyline({
                map,
                path: stops.map((s) => ({ lat: s.lat, lng: s.lng })),
                strokeColor: stops[0].color,
                strokeOpacity: 0.7,
                strokeWeight: 2.5,
            });
        }

        // The HQ marker (a dark star), so the office anchors the day at a glance.
        if (props.data.hq) {
            const position = { lat: props.data.hq.lat, lng: props.data.hq.lng };
            new m.Marker({
                position,
                map,
                title: props.data.hq.label ?? 'Headquarters',
                label: { text: '★', color: '#ffffff', fontSize: '12px' },
                icon: { path: m.SymbolPath.CIRCLE, scale: 13, fillColor: '#0f172a', fillOpacity: 1, strokeColor: '#ffffff', strokeWeight: 2 },
            });
            bounds.extend(position);
        }

        if (!single) {
            map.fitBounds(bounds, 48);
        }
    } catch {
        /* loading or the API failed — the fallback container stays in place */
    }
});
</script>

<template>
    <div class="h-full">
        <div v-if="canMap" ref="mapEl" class="h-full min-h-[200px] w-full overflow-hidden rounded-lg border border-border"></div>
        <EmptyState v-else class="min-h-[200px] rounded-lg border border-dashed border-border bg-muted/30 px-6">
            <template #icon><MapPin class="size-6 opacity-50" /></template>
            <p v-if="!data.maps_key">Maps aren't configured yet — add a Google Maps browser key to plot today's routes here.</p>
            <p v-else>No geocoded stops scheduled today. Stops appear once their pools have a service address.</p>
        </EmptyState>
    </div>
</template>
