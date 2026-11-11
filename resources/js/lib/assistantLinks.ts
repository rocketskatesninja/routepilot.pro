import { navPaths, navTitle } from '@/lib/nav';

/**
 * Turns an assistant reply into safe parts: [[type:id:Name]] becomes a link to
 * that record, [[page:/path:Name]] a link to a menu page, **bold** bold, and
 * everything else plain text (no HTML, so it stays XSS-safe).
 *
 * Each kind of account has its own allow-list: a link type it can't open, or
 * a page outside its menu, shows as plain text. Nothing ever links outside the
 * app, so an instruction planted in someone's note can't smuggle data out in
 * a link.
 */
export type Part = { kind: 'text' | 'bold' | 'link'; text: string; url?: string };

const office: Record<string, (id: string) => string> = {
    customer: (id) => `/people?selected=${id}&selected_type=customer`,
    agent: (id) => `/people?selected=${id}&selected_type=agent`,
    pool: (id) => `/pools?selected=${id}`,
    visit: (id) => `/reports?selected=${id}`,
    day: (id) => `/schedule?date=${id.slice(0, 4)}-${id.slice(4, 6)}-${id.slice(6, 8)}`,
    request: (id) => `/inbox?selected=${id}`,
    lead: (id) => `/inbox?tab=leads&selected=${id}`,
    workorder: (id) => `/work-orders?selected=${id}`,
    inventory: (id) => `/inventory?selected=${id}`,
    balance: (id) => `/balances?selected=${id}`,
};

const recordLinks: Record<string, Record<string, (id: string) => string>> = { tenant_admin: office };

/** Where a link goes for this kind of account, or null when it isn't allowed. */
export function linkUrl(role: string, type: string, id: string): string | null {
    if (type === 'page') {
        return navPaths(role).includes(id) ? id : null;
    }
    if (!/^\d+$/.test(id) || (type === 'day' && id.length !== 8)) {
        return null;
    }
    const to = recordLinks[role]?.[type];
    return to ? to(id) : null;
}

function pushText(parts: Part[], text: string) {
    const bold = /\*\*([^*]+)\*\*/g;
    let last = 0;
    let m: RegExpExecArray | null;
    while ((m = bold.exec(text)) !== null) {
        if (m.index > last) {
            parts.push({ kind: 'text', text: text.slice(last, m.index) });
        }
        parts.push({ kind: 'bold', text: m[1] });
        last = bold.lastIndex;
    }
    if (last < text.length) {
        parts.push({ kind: 'text', text: text.slice(last) });
    }
}

/**
 * One [[…]] token as a part. The proper shape is type:id:Name. The model
 * sometimes drops a piece ([[schedule:/schedule]], [[/schedule]], [[Main Pool]]):
 * a menu path still links (named from the menu), anything else shows as its
 * words. Raw brackets never reach the screen.
 */
function token(inner: string, role: string): Part {
    const full = /^([a-z]+):(\d+|\/[a-z0-9/-]*):(.+)$/.exec(inner);
    if (full) {
        const url = linkUrl(role, full[1], full[2]);
        return url ? { kind: 'link', text: full[3], url } : { kind: 'text', text: full[3] };
    }
    const path = /^(?:[a-z]+:)?(\/[a-z0-9/-]*)$/.exec(inner);
    if (path) {
        const title = navTitle(role, path[1]);
        return title ? { kind: 'link', text: title, url: path[1] } : { kind: 'text', text: path[1] };
    }
    return { kind: 'text', text: inner.split(':').pop() ?? inner };
}

export function renderParts(content: string, role: string): Part[] {
    const parts: Part[] = [];
    // Markdown links and images ([here](https://…), ![x](…)) keep only their words: replies never link out.
    content = content.replace(/!?\[([^[\]]*)\]\([^)]*\)/g, '$1');
    const link = /\[\[([^[\]]+)\]\]/g;
    let last = 0;
    let m: RegExpExecArray | null;
    while ((m = link.exec(content)) !== null) {
        if (m.index > last) {
            pushText(parts, content.slice(last, m.index));
        }
        const part = token(m[1].trim(), role);
        if (part.kind === 'link') {
            parts.push(part);
        } else {
            pushText(parts, part.text);
        }
        last = link.lastIndex;
    }
    if (last < content.length) {
        pushText(parts, content.slice(last));
    }
    return parts;
}
