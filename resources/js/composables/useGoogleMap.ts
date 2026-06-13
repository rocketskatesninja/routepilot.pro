type Ctor = new (...args: unknown[]) => unknown;

export interface GLatLngBounds {
    extend(p: { lat: number; lng: number }): void;
}

export interface GMap {
    fitBounds(bounds: GLatLngBounds, padding?: number): void;
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
    const w = window as unknown as { google?: GoogleMaps; __rpMapsPromise?: Promise<void> };
    if (w.google?.maps) {
        return Promise.resolve(w.google);
    }
    if (!w.__rpMapsPromise) {
        w.__rpMapsPromise = new Promise<void>((resolve, reject) => {
            const s = document.createElement('script');
            s.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}`;
            s.async = true;
            s.onload = () => resolve();
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
