/** The Schedule page's data, as ScheduleController sends it. */

export interface StopReading {
    tested_on: string | null;
    free_chlorine: number | null;
    ph: number | null;
    alkalinity: number | null;
    cyanuric_acid: number | null;
    calcium_hardness: number | null;
    salt: number | null;
}

export interface Stop {
    id: number;
    pool: string | null;
    pool_photo: string | null;
    customer: string | null;
    service: string | null;
    status: string;
    pool_id: number | null;
    customer_id: number | null;
    skip_reason: string | null;
    report_id: number | null;
    reading: StopReading | null;
    /** Null when the pool has no geocoded service address (no pin). */
    lat: number | null;
    lng: number | null;
    address: string | null;
}

/** One card: a tech's route, or the unassigned bucket (agent_id null). */
export interface RouteCard {
    /** Null until the card first holds a stop (the bucket, or an admin's own route). */
    id: number | null;
    agent: string | null;
    agent_id: number | null;
    agent_photo: string | null;
    color: string;
    stops: Stop[];
}

/** A tech's live position on the map. */
export interface AgentDot {
    agent_id: number;
    name: string;
    lat: number;
    lng: number;
}

/** One route as the map draws it: its placed stops, numbered by their place on the card. */
export interface MapRoute {
    agent: string | null;
    color: string;
    stops: { id: number; order: number; lat: number; lng: number; pool: string | null; status: string }[];
}
