/** Read a cookie value by name (e.g. the XSRF-TOKEN Laravel sets). */
export function getCookie(name: string): string {
    const match = document.cookie.match(new RegExp('(^|; )' + name.replace(/([.*+?^${}()|[\]\\])/g, '\\$1') + '=([^;]*)'));
    return match ? decodeURIComponent(match[2]) : '';
}

/**
 * POST JSON to a same-origin Laravel route with the CSRF header + standard
 * headers. Returns the raw Response (callers check .ok / parse as needed).
 */
export async function postJson(url: string, body?: unknown): Promise<Response> {
    return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': getCookie('XSRF-TOKEN'),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });
}
