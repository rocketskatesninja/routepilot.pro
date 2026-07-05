// Shared contract for landing section components — used by both the public SSR
// page and (later) the editor's live preview.

export interface BrandContext {
    name: string;
    slug: string;
    logoUrl: string | null;
    color: string | null;
}

export interface LandingService {
    id: number;
    name: string;
    price: number;
    frequency: string;
    description: string | null;
}

export interface LiveData {
    stats?: { pools_serviced: number; visits_completed: number; years_active: number };
    gallery?: { url: string; caption: string | null }[];
    team?: { user_id: number; name: string; title: string | null; bio: string | null; avatar: string | null }[];
    serviceArea?: { lat: number | null; lng: number | null; formattedAddress: string | null; radiusLabel: string | null };
    services?: LandingService[];
    mapsKey?: string | null;
    contactAction?: string;
    chatAction?: string;
    chatEnabled?: boolean;
}

export interface SectionConfig {
    key: string;
    enabled: boolean;
    [k: string]: unknown;
}

/** Header company-title styling (mirrors the `title` block in App\Support\LandingConfig). */
export interface TitleConfig {
    text: string | null;
    font: string;
    size: string;
    weight: string;
    tracking: string;
    color_type: 'solid' | 'gradient';
    color: string | null;
    gradient_start: string;
    gradient_via: string;
    gradient_end: string;
    gradient_angle: number;
    outline: boolean;
    outline_color: string;
    outline_width: number;
    shadow: string;
}

export interface SectionProps {
    content: SectionConfig;
    live: LiveData;
    brand: BrandContext;
    editing?: boolean;
}
