/** What the Requests page lists and opens: customers' requests, and leads from the company's website. */

export interface RequestRow {
    id: number;
    type: string;
    title: string;
    customer: string | null;
    pool: string | null;
    preferred_date: string | null;
    on: string | null;
    status: string;
    status_label: string;
}

export interface PlanLine {
    id: number;
    service_type_id: number;
    service: string | null;
    schedule: string;
    status: string;
    agent: string | null;
}

export interface RequestDetail extends RequestRow {
    customer_id: number;
    pool_id: number | null;
    for_service: boolean;
    service: { id: number; name: string; offer: string; description: string | null; is_active: boolean } | null;
    ends_on: string | null;
    message: string | null;
    visit: { id: number; on: string | null } | null; // sent from this visit's report email
    reply: string | null;
    answered_on: string | null;
    plan: string | null;
    plan_service: string | null;
    pool_plans: PlanLine[];
    defaults: {
        frequency: string;
        preferred_day: string;
        day_of_month: number;
        starts_on: string;
        price: number;
        assigned_agent_id: number | null;
    } | null;
}

export interface LeadRow {
    id: number;
    name: string;
    contact: string | null;
    source: string;
    service: string | null;
    on: string | null;
    status: string;
}

// The detail's `service` is the full pick (name + price), not just the name the row shows.
export interface LeadDetail extends Omit<LeadRow, 'service'> {
    email: string | null;
    phone: string | null;
    message: string | null;
    details: { label: string; value: string }[];
    service: { name: string; offer: string } | null;
    customer: { id: number; name: string } | null;
    convert: { first_name: string; last_name: string; email: string; phone: string; pool_type: string; pool_volume: number | null };
}

/** Chip colour for a request's or a lead's status. */
export const INBOX_TONE: Record<string, 'good' | 'warn' | 'info' | 'muted'> = {
    pending: 'warn',
    new: 'warn',
    approved: 'good',
    converted: 'good',
    contacted: 'info',
};

export const LEAD_SOURCES: Record<string, string> = {
    quote: 'Website quote',
    booking: 'Booking form',
    contact: 'Contact form',
    chatbot: 'Website chat',
};
export const LEAD_STATUSES: Record<string, string> = { new: 'New', contacted: 'Contacted', converted: 'Customer', archived: 'Archived' };

export const firstWord = (name: string | null) => name?.split(' ')[0] ?? null;
