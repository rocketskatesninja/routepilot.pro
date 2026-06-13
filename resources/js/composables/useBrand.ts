/**
 * Per-tenant brand overlay.
 *
 * Each tenant may set a brand color (hex). We convert it to the
 * "H S% L%" channel format the token system uses and override the
 * `--brand` custom property on <html>, so brand-aware components
 * (`hsl(var(--brand))`) pick up the tenant color on top of either theme.
 */
function hexToHslChannels(hex: string): string | null {
    const m = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex.trim());
    if (!m) {
        return null;
    }

    const r = parseInt(m[1], 16) / 255;
    const g = parseInt(m[2], 16) / 255;
    const b = parseInt(m[3], 16) / 255;

    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const l = (max + min) / 2;
    let h = 0;
    let s = 0;

    if (max !== min) {
        const d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        switch (max) {
            case r:
                h = (g - b) / d + (g < b ? 6 : 0);
                break;
            case g:
                h = (b - r) / d + 2;
                break;
            default:
                h = (r - g) / d + 4;
        }
        h /= 6;
    }

    return `${Math.round(h * 360)} ${Math.round(s * 100)}% ${Math.round(l * 100)}%`;
}

export function applyBrand(brandColor: string | null | undefined): void {
    if (typeof document === 'undefined') {
        return;
    }

    const root = document.documentElement.style;
    const channels = brandColor ? hexToHslChannels(brandColor) : null;
    if (channels) {
        root.setProperty('--brand', channels);
        root.setProperty('--primary', channels);
        root.setProperty('--ring', channels);
    } else {
        // No tenant brand (or it was cleared, e.g. stopping impersonation) — fall
        // back to the theme's default tokens instead of keeping the prior color.
        root.removeProperty('--brand');
        root.removeProperty('--primary');
        root.removeProperty('--ring');
    }
}
