type Ctor = new (...args: unknown[]) => unknown;

/** Whether the site is currently in dark mode (the .dark class on <html>). */
export function isDarkMode(): boolean {
    return typeof document !== 'undefined' && document.documentElement.classList.contains('dark');
}

/** A dark Google Maps style array, applied when the site is in dark mode. */
export const DARK_MAP_STYLE: Array<Record<string, unknown>> = [
    { elementType: 'geometry', stylers: [{ color: '#1f2733' }] },
    { elementType: 'labels.icon', stylers: [{ visibility: 'off' }] },
    { elementType: 'labels.text.fill', stylers: [{ color: '#8a97a8' }] },
    { elementType: 'labels.text.stroke', stylers: [{ color: '#141a22' }] },
    { featureType: 'administrative', elementType: 'geometry', stylers: [{ color: '#5a6678' }] },
    { featureType: 'administrative.locality', elementType: 'labels.text.fill', stylers: [{ color: '#aeb8c6' }] },
    { featureType: 'poi', elementType: 'labels.text.fill', stylers: [{ color: '#7c8696' }] },
    { featureType: 'poi.park', elementType: 'geometry', stylers: [{ color: '#16202b' }] },
    { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#2b3543' }] },
    { featureType: 'road', elementType: 'labels.text.fill', stylers: [{ color: '#9aa6b4' }] },
    { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#3a4656' }] },
    { featureType: 'transit', elementType: 'geometry', stylers: [{ color: '#2b3543' }] },
    { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#0e161f' }] },
    { featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: '#4a5664' }] },
];

export interface GLatLngBounds {
    extend(p: { lat: number; lng: number }): void;
}

export interface GMap {
    fitBounds(bounds: GLatLngBounds, padding?: number): void;
    setCenter(latLng: { lat: number; lng: number }): void;
    panTo(latLng: { lat: number; lng: number }): void;
    setZoom(zoom: number): void;
}

export interface GoogleMaps {
    maps: {
        Map: new (el: HTMLElement, opts?: Record<string, unknown>) => GMap;
        Marker: Ctor;
        Circle: Ctor;
        Polyline: Ctor;
        LatLngBounds: new () => GLatLngBounds;
        SymbolPath: { CIRCLE: number };
    };
}

/**
 * Load the Google Maps JS API exactly once per page — every map widget shares a
 * single window-scoped promise / script tag — and resolve with the `google`
 * global. Client-only: call from onMounted, never at setup (touches
 * window/document, which would break SSR).
 */
export function loadGoogleMaps(key: string): Promise<GoogleMaps> {
    const w = window as unknown as {
        google?: GoogleMaps;
        __rpMapsPromise?: Promise<void>;
        __rpMapsReady?: () => void;
    };
    if (w.google?.maps) {
        return Promise.resolve(w.google);
    }
    if (!w.__rpMapsPromise) {
        w.__rpMapsPromise = new Promise<void>((resolve, reject) => {
            // Google's documented async pattern: load with loading=async and a
            // global callback that fires once google.maps is ready. Avoids the
            // "loaded without loading=async" performance warning and the
            // onload-fires-before-maps-is-ready race.
            w.__rpMapsReady = () => resolve();
            const s = document.createElement('script');
            s.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&loading=async&callback=__rpMapsReady`;
            s.async = true;
            s.onerror = () => reject(new Error('Google Maps failed to load'));
            document.head.appendChild(s);
        });
    }
    return w.__rpMapsPromise.then(() => {
        const g = (window as unknown as { google?: GoogleMaps }).google;
        if (!g?.maps) {
            throw new Error('Google Maps unavailable');
        }
        return g;
    });
}
