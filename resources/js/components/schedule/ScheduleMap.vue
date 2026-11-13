<script setup lang="ts">
import { DARK_MAP_BG, DARK_MAP_STYLE, hqIcon, isDarkMode, loadGoogleMaps, type GMap, type GoogleMaps } from '@/composables/useGoogleMap';
import { MapPin } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import type { AgentDot, MapRoute } from './types';

const props = defineProps<{
    mapsKey: string | null;
    hq: { lat: number; lng: number; label: string | null } | null;
    /** The routes shown (hidden ones left out), each in its own colour. */
    routes: MapRoute[];
    /** Live tech positions, drawn as ringed dots that move in place. */
    agents: (AgentDot & { color: string })[];
    /** The picked stop: its pin is enlarged and the map pans to it. */
    focusId: number | null;
    /** Every route is switched off in the legend, so there's nothing to plot. */
    allHidden: boolean;
}>();

/** A pin was clicked: pick its stop, as clicking it in the list does. */
const emit = defineEmits<{ select: [id: number] }>();

const HIDDEN_NOTE = 'Every route is hidden. Use Clear filters above the map to show them again.';
// Finished, skipped and missed stops show their state instead of the route's colour.
const STATUS_COLOR: Record<string, string> = { completed: '#10b981', skipped: '#9ca3af', missed: '#ef4444' };

const stops = computed(() => props.routes.flatMap((r) => r.stops));
const canMap = computed(() => !!props.mapsKey && (stops.value.length > 0 || props.hq !== null || props.agents.length > 0));
const mapEl = ref<HTMLElement | null>(null);

type Overlay = { setMap: (m: unknown) => void; addListener: (ev: string, cb: () => void) => void };
let g: GoogleMaps | null = null;
let map: GMap | null = null;
let creating = false;
// What the last draw put on the map, so the next one can wipe it.
let overlays: Overlay[] = [];

function clear() {
    for (const o of overlays) o.setMap(null);
    overlays = [];
}

function draw(fit = true) {
    if (!g || !map) return;
    const m = g.maps;
    clear();
    const bounds = new m.LatLngBounds();
    const place = (Kind: GoogleMaps['maps']['Marker'], opts: Record<string, unknown>): Overlay => {
        const o = new Kind({ map, ...opts }) as Overlay;
        overlays.push(o);
        return o;
    };

    for (const r of props.routes) {
        for (const s of r.stops) {
            const focused = s.id === props.focusId;
            const position = { lat: s.lat, lng: s.lng };
            place(m.Marker, {
                position,
                title: `${s.order}. ${s.pool ?? 'Stop'}${r.agent ? ` — ${r.agent}` : ''}`,
                label: { text: String(s.order), color: '#ffffff', fontSize: focused ? '13px' : '11px', fontWeight: '600' },
                // The picked pin is bigger, with a gold ring.
                icon: {
                    path: m.SymbolPath.CIRCLE,
                    scale: focused ? 17 : 12,
                    fillColor: STATUS_COLOR[s.status] ?? r.color,
                    fillOpacity: 1,
                    strokeColor: focused ? '#facc15' : '#ffffff',
                    strokeWeight: focused ? 3.5 : 2,
                },
                zIndex: focused ? 999 : undefined,
            }).addListener('click', () => emit('select', s.id));
            bounds.extend(position);
        }
        // A dashed straight line through the route in visit order (no road-following Directions API).
        if (r.stops.length > 1) {
            place(m.Polyline, {
                path: r.stops.map((s) => ({ lat: s.lat, lng: s.lng })),
                geodesic: true,
                strokeColor: r.color,
                strokeOpacity: 0,
                icons: [{ icon: { path: 'M 0,-1 0,1', strokeOpacity: 0.75, scale: 3 }, offset: '0', repeat: '12px' }],
            });
        }
    }

    // The office anchors the day.
    const points = stops.value.map((s) => ({ lat: s.lat, lng: s.lng }));
    if (props.hq) {
        const position = { lat: props.hq.lat, lng: props.hq.lng };
        place(m.Marker, { position, title: props.hq.label ?? 'Headquarters', icon: hqIcon(m) });
        bounds.extend(position);
        points.push(position);
    }

    // Live techs: a dark-ringed dot in the route's colour with their initial, on top of the pins.
    for (const a of props.agents) {
        const position = { lat: a.lat, lng: a.lng };
        place(m.Marker, {
            position,
            title: `${a.name} (live)`,
            label: { text: (a.name?.[0] ?? '?').toUpperCase(), color: '#ffffff', fontSize: '11px', fontWeight: '700' },
            icon: { path: m.SymbolPath.CIRCLE, scale: 13, fillColor: a.color, fillOpacity: 1, strokeColor: '#0f172a', strokeWeight: 3 },
            zIndex: 1000,
        });
        bounds.extend(position);
        points.push(position);
    }

    // A focus pan owns the view; otherwise fit everything drawn.
    if (!fit || points.length === 0) return;
    if (points.length === 1) {
        map.setCenter(points[0]);
        map.setZoom(13);
    } else {
        map.fitBounds(bounds, 48);
    }
}

async function ensureMap() {
    if (map || creating || !canMap.value || !mapEl.value || !props.mapsKey) return;
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
            opts.backgroundColor = DARK_MAP_BG;
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

// Redraw on a change, creating the map first if the day had nothing to show.
// flush:'post' means the map element is mounted before we touch it.
const redraw = (fit: boolean) => (map ? draw(fit) : ensureMap());
// A new day, a drag or a colour pick: refit, unless a picked pin owns the view.
watch(
    () => props.routes,
    () => redraw(props.focusId === null),
    { deep: true, flush: 'post' },
);
// Live movement: redraw in place, so a moving dot never yanks the view.
watch(
    () => props.agents,
    () => redraw(false),
    { deep: true, flush: 'post' },
);

// The fallback replaced the map element: drop the stale map so it starts fresh.
watch(canMap, (now) => {
    if (!now) {
        clear();
        map = null;
        g = null;
    }
});

// A pick re-highlights the pin and pans/zooms to it; letting go zooms back out to the whole day.
watch(
    () => props.focusId,
    (id) => {
        if (!map) return;
        draw(id === null);
        const s = stops.value.find((x) => x.id === id);
        if (s) {
            map.panTo({ lat: s.lat, lng: s.lng });
            map.setZoom(15);
        }
    },
);
</script>

<template>
    <div class="min-h-0 flex-1">
        <div v-if="canMap" class="relative h-full min-h-[260px]">
            <div ref="mapEl" class="border-border h-full min-h-[260px] w-full overflow-hidden rounded-xl border"></div>
            <!-- The office pin (or a live tech) keeps the map up with every route hidden: say why it's empty. -->
            <div v-if="allHidden" class="pointer-events-none absolute inset-0 flex items-center justify-center p-6">
                <p class="bg-background/95 border-border max-w-xs rounded-xl border px-4 py-3 text-center text-xs shadow-md">{{ HIDDEN_NOTE }}</p>
            </div>
        </div>
        <div
            v-else
            class="border-border bg-muted/20 text-muted-foreground flex h-full min-h-[260px] flex-col items-center justify-center gap-2 rounded-xl border border-dashed px-6 text-center text-sm"
        >
            <MapPin class="size-7 opacity-40" />
            <template v-if="!mapsKey">
                <p class="font-medium">Maps aren't configured yet</p>
                <p class="max-w-xs text-xs">Add a Google Maps browser key to plot the day's routes here.</p>
            </template>
            <p v-else-if="allHidden" class="max-w-xs text-xs">{{ HIDDEN_NOTE }}</p>
            <p v-else class="max-w-xs text-xs">No geocoded stops for this day. Stops appear once their pools have a service address.</p>
        </div>
    </div>
</template>
