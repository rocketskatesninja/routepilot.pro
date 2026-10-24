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
    return sendJson('POST', url, body);
}

/** DELETE a same-origin Laravel route, with the same headers as postJson. */
export async function deleteJson(url: string): Promise<Response> {
    return sendJson('DELETE', url);
}

async function sendJson(method: 'POST' | 'DELETE', url: string, body?: unknown): Promise<Response> {
    return fetch(url, {
        method,
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

/**
 * The reason a failed request gives in its JSON `error` field, written to be
 * shown to the person (null when it gave none — fall back to a generic line).
 */
export async function failureReason(res: Response): Promise<string | null> {
    const data: unknown = await res.json().catch(() => null);
    const error = data !== null && typeof data === 'object' ? (data as { error?: unknown }).error : null;
    return typeof error === 'string' ? error : null;
}

/**
 * POST multipart/form-data to a same-origin Laravel route (for file uploads).
 * No Content-Type header — the browser sets the multipart boundary itself.
 */
export async function postForm(url: string, form: FormData): Promise<Response> {
    return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': getCookie('XSRF-TOKEN'),
        },
        body: form,
    });
}
