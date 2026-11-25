/** What the People page gets for a selected customer or agent (PeopleController). */

export type PersonType = 'customer' | 'agent';

export interface PersonRow {
    id: number;
    person_type: PersonType;
    first_name: string;
    last_name: string | null;
    email: string | null;
    phone: string | null;
    balance: number | null;
    last_visit: string | null;
    portal_status: 'none' | 'active' | 'revoked' | null;
    photo_url: string | null;
}

export interface CustomerDetail {
    type: 'customer';
    id: number;
    name: string;
    photo_url: string | null;
    email: string | null;
    phone: string | null;
    city: string | null;
    address: string | null;
    since: string | null;
    balance: number;
    next_visit: { date: string; agent: string | null; pool: string | null } | null;
    portal_status: 'none' | 'active' | 'revoked';
    emails: Record<string, boolean>;
    archived: boolean;
    archived_on: string | null;
    anonymized_on: string | null;
    summary: { pools: number; visits: number; invoices: number; charges: number } | null;
    pools: { id: number; name: string; type: string; volume: number | null; services: string[] }[];
    recent_visits: { id: number; pool: string | null; service: string | null; agent: string | null; completed_on: string | null }[];
    fields: {
        first_name: string;
        last_name: string | null;
        email: string | null;
        phone: string | null;
        address_line1: string | null;
        city: string | null;
        state: string | null;
        zip: string | null;
        notes: string | null;
        bill_chemicals: boolean;
    };
}

export interface AgentDetail {
    type: 'agent';
    id: number;
    name: string;
    photo_url: string | null;
    email: string | null;
    phone: string | null;
    is_active: boolean;
    agent_plus: boolean;
    stats: { completed_visits: number; this_week: number };
    fields: {
        first_name: string;
        last_name: string | null;
        email: string | null;
        phone: string | null;
        map_color: string | null;
        is_active: boolean;
        agent_plus: boolean;
    };
}
