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

/** A device tel: link from a free-form phone string (digits + leading + only). */
export function telLink(phone: string): string {
    return `tel:${phone.replace(/[^0-9+]/g, '')}`;
}

/** Portal: the requests page with a new request pre-filled for one pool. */
export function requestForPoolLink(poolId: number | string): string {
    return `/requests?pool=${poolId}`;
}
