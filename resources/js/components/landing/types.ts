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
}

export interface SectionConfig {
    key: string;
    enabled: boolean;
    [k: string]: unknown;
}

export interface SectionProps {
    content: SectionConfig;
    live: LiveData;
    brand: BrandContext;
    editing?: boolean;
}
