/** What the Pools page gets for its list and for a selected pool (PoolController). */

export interface PoolRow {
    id: number;
    name: string;
    photo_url: string | null;
    type: string;
    sanitizer: string;
    customer: string;
    customer_photo: string | null;
    city: string | null;
    cadence: string | null;
    agent: string | null;
    agent_photo: string | null;
    health: Health | null;
}

export interface Health {
    color: 'red' | 'amber' | 'green';
    label: string;
}

export interface PoolFields {
    customer_id: number;
    name: string;
    type: string;
    volume_gallons: number | null;
    surface_type: string | null;
    sanitizer_type: string;
    filter_type: string | null;
    pump_type: string | null;
    has_heater: boolean;
    has_automation: boolean;
    has_pool_cleaner: boolean;
    has_cover: boolean;
    has_water_feature: boolean;
    has_auto_fill: boolean;
    notes: string | null;
    address_line1: string | null;
    city: string | null;
    state: string | null;
    zip: string | null;
    gate_code: string | null;
    access_notes: string | null;
    report_cc_email: string | null;
}

export interface PoolDetail {
    id: number;
    name: string;
    photo_url: string | null;
    type: string;
    volume_gallons: number | null;
    sanitizer: string;
    filter: string | null;
    equipment: string[];
    features: string[];
    customer: { id: number | null; name: string; email: string | null; phone: string | null };
    report_cc: string | null; // who else gets every visit report
    location: {
        address: string | null;
        city: string | null;
        full_address: string | null; // with state and ZIP, for Google Maps (null with no street)
        coords: [number, number] | null;
        gate_code: string | null;
        access_notes: string | null;
    } | null;
    subscriptions: {
        id: number;
        service: string;
        schedule: string;
        agent: string;
        status: string;
        service_type_id: number;
        price: number;
        agent_id: number | null;
        agent_is_tech: boolean;
        frequency: string;
        preferred_day: string | null;
        starts_on: string | null;
        ends_on: string | null;
        day_of_month: number | null;
        hold_label: string | null;
        hold_starts_at: string | null;
        hold_ends_at: string | null;
    }[];
    missed: { count: number; recent: { id: number; on: string | null; service: string | null }[] };
    equipment_items: {
        id: number;
        type: string;
        make: string | null;
        model: string | null;
        serial: string | null;
        installed_on: string | null;
        warranty_until: string | null;
        notes: string | null;
        service_log: { id: number; on: string | null; description: string; cost: number }[];
    }[];
    targets: Record<string, { min?: number; max?: number }>;
    latest_reading: {
        taken_on: string | null;
        free_chlorine: number | null;
        ph: number | null;
        alkalinity: number | null;
        calcium_hardness: number | null;
        cyanuric_acid: number | null;
        salt: number | null;
        health: Health | null;
        last_report_id: number | null;
    } | null;
    fields: PoolFields;
}

export type Subscription = PoolDetail['subscriptions'][number];
export type Equipment = PoolDetail['equipment_items'][number];
export interface ServiceType {
    id: number;
    name: string;
    frequency: string;
    price: number;
}
export interface Option {
    id: number;
    name: string;
}
