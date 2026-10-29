/**
 * Shared builders for the back-office cross-panel deep links. Keeping the URL
 * shapes in one place means the people/reports pages and every detail pane that
 * links into them stay in sync.
 */

/** People page with this customer opened in the detail pane. */
export function customerLink(id: number | string): string {
    return `/people?selected=${id}&selected_type=customer`;
}

/** People page with this agent opened in the detail pane. */
export function agentLink(id: number | string): string {
    return `/people?selected=${id}&selected_type=agent`;
}

/**
 * People page with the email panel open, composing to one person (pre-ticked in
 * the list). `key` is the same `type:id` form the list uses to track selection.
 */
export function composeLink(id: number | string, type: 'customer' | 'agent' = 'customer'): string {
    return `/people?compose=${type}:${id}`;
}

/** Reports page with this report (completed visit) opened in the detail pane. */
export function reportLink(id: number | string): string {
    return `/reports?selected=${id}`;
}

/** Pools page with this pool opened in the detail pane. */
export function poolLink(id: number | string): string {
    return `/pools?selected=${id}`;
}

/**
 * Google Maps, searching for a place: the full address when there is one (Maps
 * shows its own card for it), otherwise the map position. Null with neither.
 */
export function mapsLink(address: string | null, coords?: [number, number] | null): string | null {
    const query = address?.trim() || (coords ? coords.join(',') : '');
    return query ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}` : null;
}

/** A device tel: link from a free-form phone string (digits + leading + only). */
export function telLink(phone: string): string {
    return `tel:${phone.replace(/[^0-9+]/g, '')}`;
}

/** Portal: the requests page with a new request pre-filled for one pool. */
export function requestForPoolLink(poolId: number | string): string {
    return `/requests?pool=${poolId}`;
}

/** Back-office: the Requests inbox with this customer request opened. */
export function inboxRequestLink(id: number | string): string {
    return `/inbox?selected=${id}`;
}

/** Back-office: the Inventory page with this chemical opened. */
export function chemicalLink(id: number | string): string {
    return `/inventory?selected=${id}`;
}

/** Back-office: the Work orders page with this one opened. */
export function workOrderLink(id: number | string): string {
    return `/work-orders?selected=${id}`;
}

/** Back-office: Balances with the add-charge form filled in from this (finished) work order. */
export function billWorkOrderLink(id: number | string): string {
    return `/balances?work_order=${id}`;
}

/** Back-office: the Requests inbox on the website-leads tab with this lead opened. */
export function inboxLeadLink(id: number | string): string {
    return `/inbox?tab=leads&selected=${id}`;
}
