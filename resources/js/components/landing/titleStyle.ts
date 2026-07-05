// Shared styling for the landing header company title — used by both the public
// page (resources/js/pages/public/Landing.vue) and the builder's live preview
// (resources/js/pages/settings/Landing.vue) so they never drift.
import type { TitleConfig } from './types';

type FontDef = { name: string; slug: string; stack: string };

// Fonts offered for the title. `slug` is the bunny.net family; Inter is already
// loaded app-wide, so it needs no extra stylesheet <link>.
export const TITLE_FONTS: FontDef[] = [
    { name: 'Inter', slug: 'inter', stack: "'Inter', sans-serif" },
    { name: 'Poppins', slug: 'poppins', stack: "'Poppins', sans-serif" },
    { name: 'Montserrat', slug: 'montserrat', stack: "'Montserrat', sans-serif" },
    { name: 'Raleway', slug: 'raleway', stack: "'Raleway', sans-serif" },
    { name: 'Nunito', slug: 'nunito', stack: "'Nunito', sans-serif" },
    { name: 'Lato', slug: 'lato', stack: "'Lato', sans-serif" },
    { name: 'Roboto', slug: 'roboto', stack: "'Roboto', sans-serif" },
    { name: 'Oswald', slug: 'oswald', stack: "'Oswald', sans-serif" },
    { name: 'Bebas Neue', slug: 'bebas-neue', stack: "'Bebas Neue', sans-serif" },
    { name: 'Playfair Display', slug: 'playfair-display', stack: "'Playfair Display', serif" },
    { name: 'Merriweather', slug: 'merriweather', stack: "'Merriweather', serif" },
];

export const TITLE_SIZES = [
    { value: 'sm', label: 'Small' },
    { value: 'md', label: 'Medium' },
    { value: 'lg', label: 'Large' },
    { value: 'xl', label: 'X-Large' },
];
export const TITLE_WEIGHTS = [
    { value: '400', label: 'Normal' },
    { value: '500', label: 'Medium' },
    { value: '600', label: 'Semibold' },
    { value: '700', label: 'Bold' },
    { value: '800', label: 'Extrabold' },
];
export const TITLE_TRACKINGS = [
    { value: 'tight', label: 'Tight' },
    { value: 'normal', label: 'Normal' },
    { value: 'wide', label: 'Wide' },
    { value: 'wider', label: 'Wider' },
];
export const TITLE_SHADOWS = [
    { value: 'none', label: 'None' },
    { value: 'soft', label: 'Soft' },
    { value: 'glow', label: 'Glow' },
];

// Title sizes (px). Default is `md`.
const SIZE_PX: Record<string, string> = { sm: '22px', md: '26px', lg: '30px', xl: '36px' };
const TRACK_EM: Record<string, string> = { tight: '-0.03em', normal: 'normal', wide: '0.05em', wider: '0.1em' };
const SHADOW_CSS: Record<string, string> = {
    none: '',
    soft: '0 1px 3px rgba(0,0,0,0.28)',
    glow: '0 0 14px rgba(56,189,248,0.55)',
};

function fontStack(name: string): string {
    return TITLE_FONTS.find((f) => f.name === name)?.stack ?? "'Inter', sans-serif";
}

/** Combined bunny.net URL loading every title font (so the builder's font dropdown can
 *  render each option in its own typeface). Inter is excluded — it's already loaded app-wide. */
export function allTitleFontsHref(): string {
    const families = TITLE_FONTS.filter((f) => f.slug !== 'inter').map((f) => `${f.slug}:400`);
    return `https://fonts.bunny.net/css?family=${families.join('|')}`;
}

/** A bunny.net stylesheet URL for the selected font, loaded across the full offered weight
 *  range so Bold vs Extrabold etc. render as real weights — Inter's app-wide stylesheet only
 *  ships 400–700, so 800 needs its own load. bunny gracefully omits weights a font lacks. */
export function titleFontHref(t: TitleConfig): string | null {
    const f = TITLE_FONTS.find((x) => x.name === t.font);
    if (!f) return null;
    return `https://fonts.bunny.net/css?family=${f.slug}:400,500,600,700,800`;
}

/** Inline CSS string for the title (Vue `:style` accepts a string). */
export function titleStyle(t: TitleConfig): string {
    const parts: string[] = [
        `font-family: ${fontStack(t.font)}`,
        `font-size: ${SIZE_PX[t.size] ?? SIZE_PX.md}`,
        `font-weight: ${t.weight || '700'}`,
        `letter-spacing: ${TRACK_EM[t.tracking] ?? 'normal'}`,
        'line-height: 1',
    ];

    if (t.color_type === 'gradient') {
        parts.push(
            `background-image: linear-gradient(${t.gradient_angle ?? 135}deg, ${t.gradient_start}, ${t.gradient_via}, ${t.gradient_end})`,
            '-webkit-background-clip: text',
            'background-clip: text',
            'color: transparent',
        );
    } else if (t.color) {
        parts.push(`color: ${t.color}`);
    }

    if (t.outline && t.outline_width > 0) {
        parts.push(`-webkit-text-stroke: ${t.outline_width}px ${t.outline_color}`);
    }
    const shadow = SHADOW_CSS[t.shadow];
    if (shadow) {
        parts.push(`text-shadow: ${shadow}`);
    }

    return parts.join('; ');
}
