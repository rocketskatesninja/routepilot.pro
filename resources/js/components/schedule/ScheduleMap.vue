<script setup lang="ts">
import { DARK_MAP_STYLE, isDarkMode, loadGoogleMaps, type GMap, type GoogleMaps } from '@/composables/useGoogleMap';
import { MapPin } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

interface StopMarker {
    id: number;
    lat: number;
    lng: number;
    order: number;
    pool: string | null;
    status: string;
    agent: string | null;
    agent_id: number | null;
}
interface Hq {
    lat: number;
    lng: number;
    label: string | null;
}

const props = defineProps<{
    mapsKey: string | null;
    hq: Hq | null;
    markers: StopMarker[];
    // Live per-agent colours, keyed by agent id — the schedule page mutates this
    // when the tenant picks a colour, and the map redraws.
    colors: Record<number, string>;
    // The stop id the list is focusing; its pin is enlarged + the map pans to it.
    focusId: number | null;
}>();

const canMap = computed(() => !!props.mapsKey && (props.markers.length > 0 || props.hq !== null));
const mapEl = ref<HTMLElement | null>(null);

// Completed/skipped stops override the agent colour, mirroring the legacy map.
const STATUS_COLOR: Record<string, string> = { completed: '#10b981', skipped: '#9ca3af' };
const UNASSIGNED = '#9ca3af';

const lineColorFor = (agentId: number | null): string => (agentId !== null ? (props.colors[agentId] ?? UNASSIGNED) : UNASSIGNED);
const markerColorFor = (mk: StopMarker): string => STATUS_COLOR[mk.status] ?? lineColorFor(mk.agent_id);

let g: GoogleMaps | null = null;
let map: GMap | null = null;
let creating = false;
// Drawn overlays we own, so a redraw can wipe the previous frame.
let overlays: Array<{ setMap: (m: unknown) => void }> = [];

function clearOverlays() {
    for (const o of overlays) {
        o.setMap(null);
    }
    overlays = [];
}

function draw(fit = true) {
    if (!g || !map) {
        return;
    }
    const m = g.maps;
    clearOverlays();
    const bounds = new m.LatLngBounds();

    // Group stops by agent so each route gets one threaded polyline.
    const groups: Record<string, StopMarker[]> = {};
    for (const mk of props.markers) {
        const position = { lat: mk.lat, lng: mk.lng };
        const focused = mk.id === props.focusId;
        const marker = new m.Marker({
            position,
            map,
            title: `${mk.order}. ${mk.pool ?? 'Stop'}${mk.agent ? ` — ${mk.agent}` : ''}`,
            label: { text: String(mk.order), color: '#ffffff', fontSize: focused ? '13px' : '11px', fontWeight: '600' },
            // The focused pin is enlarged with a gold ring so it stands out.
            icon: {
                path: m.SymbolPath.CIRCLE,
                scale: focused ? 17 : 12,
                fillColor: markerColorFor(mk),
                fillOpacity: 1,
                strokeColor: focused ? '#facc15' : '#ffffff',
                strokeWeight: focused ? 3.5 : 2,
            },
            zIndex: focused ? 999 : undefined,
        }) as unknown as { setMap: (x: unknown) => void };
        overlays.push(marker);
        bounds.extend(position);
        (groups[mk.agent_id ?? '__unassigned'] ??= []).push(mk);
    }

    // Dashed straight polyline per agent, in visit order (legacy-style — no
    // road-following Directions API).
    for (const [key, stops] of Object.entries(groups)) {
        if (stops.length < 2) {
            continue;
        }
        const agentId = key === '__unassigned' ? null : Number(key);
        const line = new m.Polyline({
            map,
            path: stops.map((s) => ({ lat: s.lat, lng: s.lng })),
            geodesic: true,
            strokeColor: lineColorFor(agentId),
            strokeOpacity: 0,
            icons: [{ icon: { path: 'M 0,-1 0,1', strokeOpacity: 0.75, scale: 3 }, offset: '0', repeat: '12px' }],
        }) as unknown as { setMap: (x: unknown) => void };
        overlays.push(line);
    }

    // HQ marker (dark star), so the office anchors the day.
    if (props.hq) {
        const position = { lat: props.hq.lat, lng: props.hq.lng };
        const hqMarker = new m.Marker({
            position,
            map,
            title: props.hq.label ?? 'Headquarters',
            label: { text: '★', color: '#ffffff', fontSize: '12px' },
            icon: { path: m.SymbolPath.CIRCLE, scale: 13, fillColor: '#0f172a', fillOpacity: 1, strokeColor: '#ffffff', strokeWeight: 2 },
        }) as unknown as { setMap: (x: unknown) => void };
        overlays.push(hqMarker);
        bounds.extend(position);
    }

    // A focus pan owns the viewport; only auto-fit on a normal (re)draw.
    if (!fit) {
        return;
    }
    const count = props.markers.length + (props.hq ? 1 : 0);
    if (count === 1) {
        const p = props.markers[0] ?? props.hq!;
        map.setCenter({ lat: p.lat, lng: p.lng });
        map.setZoom(13);
    } else if (count > 1) {
        map.fitBounds(bounds, 48);
    }
}

async function ensureMap() {
    if (map || creating || !canMap.value || !mapEl.value || !props.mapsKey) {
        return;
    }
    creating = true;
    try {
        g = await loadGoogleMaps(props.mapsKey);
        const opts: Record<string, unknown> = {
            disableDefaultUI: true,
            zoomControl: true,
            gestureHandling: 'cooperative',
            center: { lat: 39.5, lng: -98.35 },
            zoom: 4,
        };
        if (isDarkMode()) {
            opts.styles = DARK_MAP_STYLE;
        }
        map = new g.maps.Map(mapEl.value, opts);
        draw();
    } catch {
        /* loading or the API failed — the fallback container stays in place */
    } finally {
        creating = false;
    }
}

onMounted(ensureMap);

// Date navigation / drag / colour change refresh the props; redraw (creating the
// map first if a previously-empty day now has something to show). flush:'post'
// guarantees the map element ref is mounted before we touch it.
watch(
    () => [props.markers, props.colors] as const,
    async () => {
        if (!map) {
            await ensureMap();
        } else {
            // Keep a focused pan steady through drags/recolours; refit otherwise.
            draw(props.focusId === null);
        }
    },
    { deep: true, flush: 'post' },
);

// If the day empties out (fallback shown, element unmounted) drop the stale map
// so it re-inits cleanly when stops return.
watch(canMap, (now) => {
    if (!now) {
        clearOverlays();
        map = null;
        g = null;
    }
});

// Click-to-focus: re-highlight the pin (without refitting bounds) and pan/zoom
// the map to it. Clearing focus (null) just drops the highlight, leaving the view.
watch(
    () => props.focusId,
    (id) => {
        if (!map) {
            return;
        }
        draw(false);
        if (id === null) {
            return;
        }
        const mk = props.markers.find((m) => m.id === id);
        if (mk) {
            map.panTo({ lat: mk.lat, lng: mk.lng });
            map.setZoom(15);
        }
    },
);
</script>

<template>
    <div class="min-h-0 flex-1">
        <div v-if="canMap" ref="mapEl" class="h-full min-h-[260px] w-full overflow-hidden rounded-xl border border-border"></div>
        <div
            v-else
            class="flex h-full min-h-[260px] flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border bg-muted/20 px-6 text-center text-sm text-muted-foreground"
        >
            <MapPin class="size-7 opacity-40" />
            <p v-if="!mapsKey" class="font-medium">Maps aren't configured yet</p>
            <p v-if="!mapsKey" class="max-w-xs text-xs">Add a Google Maps browser key to plot the day's routes here.</p>
            <p v-else class="max-w-xs text-xs">No geocoded stops for this day. Stops appear once their pools have a service address.</p>
        </div>
    </div>
</template>
