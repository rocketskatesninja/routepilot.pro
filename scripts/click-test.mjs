#!/usr/bin/env node
/**
 * RoutePilot click test: a real-browser pass over the whole app on staging.
 *
 * For each browser and screen size it logs in as every kind of user, opens every
 * main screen, checks it, and clicks what's on it. Then two end-to-end checks make
 * real changes: the feature switches, a tech finishing a visit, adding a customer,
 * billing and payment, and answering a customer's request. Everything runs
 * against a throwaway company (scripts/click-test-company.php), made for the run and
 * deleted after, so it can't touch the demo company or sign anyone out.
 *
 * While it clicks around, every request that would change data is blocked, so a
 * Delete or Send button can't do anything. It refuses to run against anything but
 * a .local host.
 *
 * Usage (from a machine that can ssh to staging):
 *   npm run test:click
 *
 * Env:
 *   CLICK_BASE      staging URL                  (default https://routepilot.local)
 *   CLICK_SSH       ssh host for staging         (default serv)
 *   CLICK_APP_DIR   app folder on staging        (default /var/www/routepilot.pro)
 *   CLICK_TARGETS   e.g. chrome-desktop,firefox-phone (default all six)
 *   CLICK_ROLES     e.g. office,customer         (default all)
 *   CLICK_NO_FLOWS=1    skip the two end-to-end checks
 *   CLICK_NO_PUBLISH=1  don't copy the report to staging
 *   CHROME_PATH, FIREFOX_PATH  browser executables
 *
 * The report goes to click-report/ and https://routepilot.local/click-report/index.html.
 * Exit code 1 if anything failed.
 */
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { homedir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium, firefox } from 'playwright-core';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const BASE = (process.env.CLICK_BASE ?? 'https://routepilot.local').replace(/\/$/, '');
const SSH = process.env.CLICK_SSH ?? 'serv';
const APP_DIR = process.env.CLICK_APP_DIR ?? '/var/www/routepilot.pro';
const OUT = join(ROOT, 'click-report');
const SHOTS = join(OUT, 'shots');
const MAX_CLICKS_PER_PAGE = 20;

if (!new URL(BASE).hostname.endsWith('.local')) {
    console.error(`Refusing to run against ${BASE}: the click test is for staging (.local) only.`);
    process.exit(2);
}

const firefoxPath = () => {
    if (process.env.FIREFOX_PATH) return process.env.FIREFOX_PATH;
    const cache = join(homedir(), '.cache/ms-playwright');
    const dir = readdirSync(cache)
        .filter((d) => d.startsWith('firefox-'))
        .sort()
        .pop();
    return dir ? join(cache, dir, 'firefox/firefox') : undefined;
};

const TARGETS = [
    { id: 'chrome-desktop', engine: 'chrome', viewport: { width: 1440, height: 900 }, crawl: true },
    { id: 'chrome-phone', engine: 'chrome', viewport: { width: 412, height: 860 }, touch: true },
    { id: 'firefox-desktop', engine: 'firefox', viewport: { width: 1440, height: 900 } },
    // A smaller laptop, where headers run out of room first.
    { id: 'chrome-laptop', engine: 'chrome', viewport: { width: 1280, height: 720 } },
    // Big view (everything 20% bigger), on a laptop: the most crowded the desktop gets.
    { id: 'chrome-big', engine: 'chrome', viewport: { width: 1366, height: 768 }, big: true },
    { id: 'firefox-phone', engine: 'firefox', viewport: { width: 412, height: 860 }, crawl: true },
].filter((t) => !process.env.CLICK_TARGETS || process.env.CLICK_TARGETS.split(',').includes(t.id));

const PAGES = {
    public: ['/', '/login', '/register', '/forgot-password', '/privacy', '/terms'],
    office: [
        '/dashboard',
        '/schedule',
        '/reports',
        '/work-orders',
        '/inventory',
        '/inventory?type=parts',
        '/inbox',
        '/inbox?tab=leads',
        '/people',
        '/people?type=agent',
        '/pools',
        '/services',
        '/balances',
        '/insights',
        '/company',
        '/company?tab=address',
        '/company?tab=email',
        '/company?tab=features',
        '/company/landing',
        '/billing',
        '/assistant',
        '/notifications',
        '/settings/profile',
        '/settings/password',
        '/settings/appearance',
        '/settings/two-factor',
        '/field',
    ],
    tech: ['/dashboard', '/schedule', '/reports', '/work-orders', '/notifications', '/assistant', '/settings/profile', '/field'],
    customer: [
        '/dashboard',
        '/my-pools',
        '/history',
        '/balance',
        '/requests',
        '/settings/emails',
        '/notifications',
        '/assistant',
        '/settings/profile',
    ],
    platform: ['/dashboard', '/people', '/platform/billing', '/platform/ai', '/assistant'],
};
const ROLES = Object.keys(PAGES).filter((r) => !process.env.CLICK_ROLES || process.env.CLICK_ROLES.split(',').includes(r));

// Staging-only noise, counted in the report but not failures.
const KNOWN = [/google maps|maps\.googleapis|InvalidKey|RefererNotAllowed/i, /ResizeObserver loop/i, /Download the Vue Devtools/i];
// A request the crawl blocked on purpose makes the app complain; that's the block, not a bug.
const BLOCK_NOISE = /ERR_FAILED|NS_ERROR|NS_BINDING_ABORTED|Network ?Error|Failed to fetch|NetworkError|Load failed|AbortError|ERR_CONNECTION/i;
// Never followed: leaves the app, or starts something with an outside service.
const SKIP_HREF = /^(mailto:|tel:|sms:|javascript:)|\/billing\/portal|\/company\/connect/i;

const report = { issues: [], known: 0, pages: 0, clicks: 0, blocked: 0, shots: [], flows: [], started: new Date(), notes: [] };
const seenIssues = new Set();

// `once` reports something a single time per browser and role however many pages show it
// (a cut-off label in the sidebar would otherwise be listed for every page).
function addIssue(ctx, kind, detail, once = null) {
    const where = ctx.where ?? {};
    const key = once
        ? [ctx.target.id, ctx.role, kind, once].join('|')
        : [ctx.target.id, ctx.role, where.path, kind, String(detail).slice(0, 80)].join('|');
    if (seenIssues.has(key)) return;
    seenIssues.add(key);
    report.issues.push({
        target: ctx.target.id,
        role: ctx.role,
        path: where.path ?? '',
        action: where.action ?? 'load',
        kind,
        detail: String(detail).slice(0, 400),
        shot: where.shot ?? null,
    });
    console.log(`  ✗ [${ctx.target.id}/${ctx.role}] ${where.path} (${where.action}) ${kind}: ${String(detail).slice(0, 140)}`);
}

function noise(ctx, text) {
    if (KNOWN.some((re) => re.test(text))) {
        report.known++;
        return true;
    }
    // The test's own page loads cut off whatever the last page was still fetching. Firefox
    // reports that as "Request aborted" (Chrome stays quiet), so it's noted, not counted.
    if (Date.now() - ctx.leftAt < 2000 && /Request aborted/i.test(text)) {
        report.notes.push(`${ctx.target.id}/${ctx.role}: a request was cut off when the test left a page (before ${ctx.where.path}).`);
        return true;
    }
    return ctx.block && Date.now() - ctx.lastBlock < 3000 && BLOCK_NOISE.test(text);
}

function watch(ctx) {
    const { page } = ctx;
    page.on('pageerror', (e) => noise(ctx, e.message) || addIssue(ctx, 'javascript-error', e.message));
    page.on(
        'console',
        (m) => m.type() === 'error' && !ctx.allow404 && !noise(ctx, `${m.text()} ${m.location().url}`) && addIssue(ctx, 'console-error', m.text()),
    );
    page.on('response', (r) => {
        let url;
        try {
            url = new URL(r.url());
        } catch {
            return;
        }
        if (url.origin !== BASE) return;
        if (r.status() >= 500) addIssue(ctx, 'server-error', `HTTP ${r.status()} ${url.pathname}`);
        else if (r.status() === 404 && /\.(js|css|png|jpe?g|webp|svg|woff2?)$/.test(url.pathname)) addIssue(ctx, 'missing-file', url.pathname);
    });
    page.on('dialog', (d) => d.dismiss().catch(() => {}));
}

async function session(browser, target, role, creds, { block }) {
    const options = { ignoreHTTPSErrors: true, viewport: target.viewport, serviceWorkers: 'block' };
    if (target.touch) Object.assign(options, { hasTouch: true, isMobile: true, deviceScaleFactor: 2 });
    const context = await browser.newContext(options);
    if (target.big) await context.addInitScript(() => localStorage.setItem('big-view', '1'));
    const page = await context.newPage();
    const ctx = { target, role, context, page, block, lastBlock: 0, leftAt: 0, where: { path: '/login', action: 'login' } };
    context.on('page', (p) => p !== page && p.close().catch(() => {}));
    watch(ctx);
    if (role !== 'public') {
        for (let attempt = 1; ; attempt++) {
            try {
                await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded', timeout: 30000 });
                await page.fill('#email', creds.accounts[role]);
                await page.fill('#password', creds.password);
                await page.press('#password', 'Enter');
                await page.waitForURL(/dashboard|field/, { waitUntil: 'commit', timeout: 20000 });
                break;
            } catch (e) {
                if (attempt === 2) throw e;
            }
        }
        await settle(page);
    }
    if (block) {
        await context.route('**/*', (route) => {
            const request = route.request();
            if (['GET', 'HEAD', 'OPTIONS'].includes(request.method()) || !request.url().startsWith(BASE)) return route.continue();
            report.blocked++;
            ctx.lastBlock = Date.now();
            return route.abort();
        });
    }
    return ctx;
}

const PAGE_CHECK = () => {
    const text = document.body?.innerText ?? '';
    const broken = [...document.images]
        .filter((i) => i.complete && i.naturalWidth === 0 && i.currentSrc.startsWith(location.origin))
        .map((i) => new URL(i.currentSrc).pathname);
    const bad = text.match(/\bundefined\b|\bNaN\b|\[object Object\]|\{\{|\}\}/)?.[0] ?? null;
    // Text cut off by its own box: a one-line ellipsis or clip, or a line clamp, that doesn't
    // fit. Long names and addresses may be cut in narrow lists (with the full text on hover); labels mustn't.
    const clipped = [];
    // Whether any words in a clipping box really run past its sides. Pictures and glows
    // overflow a box by design (a hero's slow zoom grows past it by the second); words
    // shut inside a scroller of their own are that scroller's business.
    const wordsPastEdge = (box) => {
        const edge = box.getBoundingClientRect();
        const walker = document.createTreeWalker(box, NodeFilter.SHOW_TEXT);
        const range = document.createRange();
        for (let node; (node = walker.nextNode());) {
            if (!node.textContent.trim()) continue;
            let inner = false;
            for (let up = node.parentElement; up && up !== box && !inner; up = up.parentElement) inner = getComputedStyle(up).overflowX !== 'visible';
            if (inner) continue;
            range.selectNodeContents(node);
            for (const r of range.getClientRects()) if (r.width && (r.right > edge.right + 1 || r.left < edge.left - 1)) return true;
        }
        return false;
    };
    for (const el of document.body?.querySelectorAll('*') ?? []) {
        if (clipped.length >= 8) break;
        // The folded sidebar hides its words on purpose, and a Google map's tiles run past its edge.
        if (el.closest('[data-collapsible="icon"], .gm-style')) continue;
        const s = getComputedStyle(el);
        const clamp = s.webkitLineClamp && s.webkitLineClamp !== 'none';
        const oneLine = s.whiteSpace === 'nowrap' && s.overflowX !== 'visible';
        // A box that hides what doesn't fit, or (on a desktop-sized screen) a row that
        // scrolls sideways, like tabs squeezed out of a header. Phones swipe those on purpose.
        const hides = ['hidden', 'clip'].includes(s.overflowX) || (window.innerWidth >= 1024 && ['auto', 'scroll'].includes(s.overflowX));
        if (!oneLine && !clamp && !hides) continue;
        if (el.checkVisibility && !el.checkVisibility({ opacityProperty: true, visibilityProperty: true })) continue;
        const wide = (oneLine || hides) && el.scrollWidth > el.clientWidth + 1;
        const tall = clamp && el.scrollHeight > el.clientHeight + 1;
        const words = (el.innerText ?? '').replace(/\s+/g, ' ').trim();
        // 1px boxes are screen-reader labels (sr-only): hidden from view on purpose.
        if ((!wide && !tall) || !words || el.clientWidth <= 2) continue;
        // Shortened on purpose: data (names, emails, messages) carries its full wording as hover
        // text. Labels don't, so a cut-off label still counts.
        const hover = (el.closest('[title]')?.getAttribute('title') ?? '').replace(/\s+/g, ' ').trim();
        if (hover && hover.toLowerCase() === words.toLowerCase()) continue;
        // A box that clips (not scrolls) counts only when it cuts words, not decoration. A box
        // that scrolls by even a few px gives the page a sideways wobble, so that always counts.
        if (wide && !tall && !oneLine && ['hidden', 'clip'].includes(s.overflowX) && !wordsPastEdge(el)) continue;
        clipped.push({
            text: words.slice(0, 60),
            detail: tall
                ? `${clamp} lines aren't enough`
                : `${oneLine ? 'needs' : 'hides content: needs'} ${el.scrollWidth}px, has ${el.clientWidth}px`,
        });
    }
    return {
        length: text.trim().length,
        bad,
        crashed: /SQLSTATE|Whoops!|Server Error|Page Expired|Internal Server/i.test(text),
        overflow: document.documentElement.scrollWidth > window.innerWidth + 1 ? document.documentElement.scrollWidth : 0,
        broken: broken.slice(0, 4),
        clipped,
    };
};

function examine(ctx, c) {
    if (c.crashed) addIssue(ctx, 'error-page', 'The page shows a server error');
    if (c.length < 40) addIssue(ctx, 'blank-page', `Only ${c.length} characters on the page`);
    if (c.bad) addIssue(ctx, 'broken-text', `Shows "${c.bad}" on the page`);
    if (c.overflow) addIssue(ctx, 'sideways-scroll', `Page is ${c.overflow}px wide on a ${ctx.target.viewport.width}px screen`);
    if (c.broken.length) addIssue(ctx, 'broken-image', c.broken.join(', '));
    for (const cut of c.clipped ?? []) addIssue(ctx, 'cut-off-text', `"${cut.text}" ${cut.detail}`, cut.text);
}

// Settled: the network has gone quiet and no spinner is showing (the field app fetches its day
// after the page itself has loaded). A spinner that never stops still fails the blank-page check.
const settle = (page) =>
    page
        .waitForLoadState('networkidle', { timeout: 5000 })
        .catch(() => {})
        .then(() =>
            page
                .waitForFunction(() => ![...document.querySelectorAll('.animate-spin')].some((s) => s.checkVisibility?.() ?? true), null, {
                    timeout: 5000,
                })
                .catch(() => {}),
        )
        .then(() => page.waitForTimeout(300));
const fileFor = (ctx, path, suffix = '') =>
    `${ctx.target.id}__${ctx.role}__${(path.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'home') + suffix}.jpg`;

async function shot(ctx, path, suffix = '') {
    const file = fileFor(ctx, path, suffix);
    await ctx.page.screenshot({ path: join(SHOTS, file), type: 'jpeg', quality: 55, scale: 'css', timeout: 15000 }).catch(() => {});
    return file;
}

async function open(ctx, path, { keep = true } = {}) {
    ctx.where = { path, action: 'load' };
    let response;
    try {
        ctx.leftAt = Date.now();
        response = await ctx.page.goto(BASE + path, { waitUntil: 'domcontentloaded', timeout: 30000 });
    } catch (e) {
        addIssue(ctx, 'would-not-load', e.message);
        return false;
    }
    await settle(ctx.page);
    const file = await shot(ctx, path);
    ctx.where.shot = file;
    if (response && response.status() >= 400) addIssue(ctx, 'bad-status', `HTTP ${response.status()}`);
    if (path !== '/login' && new URL(ctx.page.url()).pathname === '/login' && ctx.role !== 'public')
        addIssue(ctx, 'signed-out', 'Sent back to the login page');
    examine(ctx, await ctx.page.evaluate(PAGE_CHECK));
    report.pages++;
    if (keep) report.shots.push({ role: ctx.role, path, target: ctx.target.id, file });
    return true;
}

const CANDIDATES = () => {
    const visible = (el) => {
        const r = el.getBoundingClientRect();
        return r.width > 0 && r.height > 0 && getComputedStyle(el).visibility !== 'hidden' && !el.disabled;
    };
    const out = [];
    let firstRow = true;
    document.querySelectorAll('[data-ct]').forEach((el) => el.removeAttribute('data-ct'));
    for (const el of document.querySelectorAll('a[href], button, [role="tab"], tbody tr')) {
        if (!visible(el) || el.closest('[data-sidebar="rail"]')) continue;
        if (el.tagName === 'TR') {
            if (!firstRow) continue;
            firstRow = false;
        }
        const href = el.getAttribute('href');
        if (el.target === '_blank' || el.hasAttribute('download')) continue;
        if (href && new URL(href, location.href).origin !== location.origin) continue;
        const name =
            el.tagName === 'TR'
                ? 'first row'
                : (el.getAttribute('aria-label') || el.title || el.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 50) || '(no label)';
        if (/^log ?out$|^sign ?out$/i.test(name)) continue;
        el.setAttribute('data-ct', String(out.length));
        out.push({ id: out.length, name, href: href ?? '' });
    }
    return out;
};

async function crawl(ctx, path, seen) {
    const { page } = ctx;
    const home = async () => {
        ctx.leftAt = Date.now();
        await page.goto(BASE + path, { waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => {});
        await settle(page);
        return page.evaluate(CANDIDATES).catch(() => []);
    };
    const keyOf = (c) => `${c.name}|${c.href.replace(/\d+/g, '#')}`;
    let current = await page.evaluate(CANDIDATES).catch(() => []);
    const todo = current.filter((c) => !SKIP_HREF.test(c.href) && !seen.has(keyOf(c))).slice(0, MAX_CLICKS_PER_PAGE);
    let sinceHome = 0;
    for (const item of todo) {
        const key = keyOf(item);
        seen.add(key);
        if (sinceHome >= 5) {
            current = await home();
            sinceHome = 0;
        }
        let found = current.find((c) => keyOf(c) === key);
        if (!found) {
            current = await home();
            sinceHome = 0;
            found = current.find((c) => keyOf(c) === key);
            if (!found) continue;
        }
        ctx.where = { path, action: `click "${item.name}"` };
        const before = page.url();
        try {
            await page.locator(`[data-ct="${found.id}"]`).click({ timeout: 2500 });
        } catch {
            current = await home();
            sinceHome = 0;
            continue; // covered by something else, or gone: not a failure on its own
        }
        report.clicks++;
        sinceHome++;
        await page.waitForTimeout(200);
        await settle(page);
        const moved = page.url() !== before;
        const url = new URL(page.url());
        if (url.origin === BASE) {
            const checked = await page.evaluate(PAGE_CHECK).catch(() => null);
            if (checked) {
                ctx.where.shot =
                    checked.crashed || checked.length < 40 || checked.bad || checked.clipped.length
                        ? await shot(ctx, path, `-click-${report.clicks}`)
                        : null;
                examine(ctx, checked);
            }
        }
        if (moved) {
            current = await home();
            sinceHome = 0;
        } else {
            await page.keyboard.press('Escape').catch(() => {});
            await page.waitForTimeout(80);
            current = await page.evaluate(CANDIDATES).catch(() => []);
        }
    }
}

async function launch(engine) {
    if (engine === 'chrome')
        return chromium.launch({ executablePath: process.env.CHROME_PATH ?? '/usr/bin/google-chrome', headless: true, args: ['--no-sandbox'] });
    return firefox.launch({ executablePath: firefoxPath(), headless: true });
}

async function runTarget(target, creds) {
    console.log(`\n▶ ${target.id}`);
    const browser = await launch(target.engine);
    for (const role of ROLES) {
        let ctx;
        try {
            ctx = await session(browser, target, role, creds, { block: true });
        } catch (e) {
            addIssue({ target, role, where: { path: '/login', action: 'login' } }, 'could-not-log-in', e.message);
            continue;
        }
        const seen = new Set();
        const pages = role === 'public' ? [...PAGES.public, `/t/${creds.slug}`] : PAGES[role];
        for (const path of pages) {
            const ok = await open(ctx, path);
            if (ok && target.crawl) await crawl(ctx, path, seen).catch((e) => addIssue(ctx, 'crawl-stopped', e.message));
        }
        console.log(`  ${role}: ${pages.length} screens`);
        await ctx.context.close();
    }
    await browser.close();
}

// ---- the two checks that make real changes, on the throwaway company

async function step(name, fn) {
    try {
        const detail = await fn();
        report.flows.push({ name, ok: true, detail: detail ?? '' });
        console.log(`  ✓ ${name}`);
    } catch (e) {
        report.flows.push({ name, ok: false, detail: e.message.slice(0, 300) });
        console.log(`  ✗ ${name}: ${e.message.slice(0, 200)}`);
    }
}
const must = (ok, why) => {
    if (!ok) throw new Error(why);
};

async function runFlows(creds) {
    console.log('\n▶ end-to-end checks');
    const desktop = TARGETS.find((t) => t.id === 'chrome-desktop') ?? {
        id: 'chrome-desktop',
        engine: 'chrome',
        viewport: { width: 1440, height: 900 },
    };
    const phone = { id: 'chrome-phone', engine: 'chrome', viewport: { width: 412, height: 860 }, touch: true };
    const browser = await launch('chrome');

    const office = await session(browser, desktop, 'office', creds, { block: false });
    const p = office.page;
    const nav = async () => (await p.locator('[data-sidebar="sidebar"]').first().innerText()).replace(/\s+/g, ' ');
    const status = async (path) => (await p.goto(BASE + path, { waitUntil: 'domcontentloaded' })).status();
    const flip = async (id, on) => {
        await p.goto(`${BASE}/company?tab=features`, { waitUntil: 'domcontentloaded' });
        await settle(p);
        const box = p.locator(`#${id}`);
        if (((await box.getAttribute('data-state')) === 'checked') !== on) {
            await box.click();
            await p.waitForFunction(
                ([i, w]) => document.querySelector(`#${i}`)?.getAttribute('data-state') === (w ? 'checked' : 'unchecked'),
                [id, on],
            );
            await p.waitForTimeout(300);
        }
    };
    const SWITCHES = [
        ['customer_billing', 'Customer billing', '/balances', 'Balances'],
        ['landing_page', 'Website', '/company/landing', 'Landing page'],
        ['ai_assistant', 'AI assistant', '/assistant', 'Assistant'],
    ];
    // A checker that can't fail proves nothing: plant problems, and insist they're caught.
    await step('The checker catches a planted error and a dead page', async () => {
        const before = report.issues.length;
        office.where = { path: '/canary', action: 'planted' };
        await p.evaluate(() =>
            setTimeout(() => {
                throw new Error('click-test canary');
            }),
        );
        await p.waitForTimeout(400);
        must(
            report.issues.some((i) => i.kind === 'javascript-error' && i.detail.includes('canary')),
            'Missed a planted JavaScript error',
        );
        await open(office, '/this-page-does-not-exist', { keep: false });
        must(
            report.issues.some((i) => i.kind === 'bad-status' && i.path === '/this-page-does-not-exist'),
            'Missed a dead page',
        );
        report.issues.length = before;
    });

    // A step that fails halfway must not leave a switch off for the steps after it.
    const allOn = async () => {
        for (const [id] of [...SWITCHES, ['inventory_tracking']]) await flip(id, true).catch(() => {});
    };
    const restoring = async (fn) => {
        try {
            return await fn();
        } finally {
            await allOn();
        }
    };

    office.allow404 = true;
    for (const [id, name, path, link] of SWITCHES) {
        await step(`${name} switch: off removes it, on brings it back`, () =>
            restoring(async () => {
                await flip(id, false);
                must((await status(path)) === 404, `${path} still opens with it off`);
                await p.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded' });
                await settle(p);
                must(!(await nav()).includes(link), `"${link}" still in the sidebar`);
                await flip(id, true);
                must((await status(path)) === 200, `${path} didn't come back`);
            }),
        );
    }
    await step('Inventory switch: off makes it a Chemicals list, on restores it', () =>
        restoring(async () => {
            await flip('inventory_tracking', false);
            await p.goto(`${BASE}/inventory`, { waitUntil: 'domcontentloaded' });
            await settle(p);
            const off = await p.locator('body').innerText();
            must((await nav()).includes('Chemicals'), 'Sidebar doesn’t say Chemicals');
            must(!off.includes('In stock'), 'Still shows stock');
            await flip('inventory_tracking', true);
            await p.goto(`${BASE}/inventory`, { waitUntil: 'domcontentloaded' });
            await settle(p);
            must((await p.locator('body').innerText()).includes('In stock'), 'Stock didn’t come back');
        }),
    );
    await step('Website off: the public address shows a customer login', () =>
        restoring(async () => {
            await flip('landing_page', false);
            const visitor = await session(browser, desktop, 'public', creds, { block: true });
            await visitor.page.goto(`${BASE}/t/${creds.slug}`, { waitUntil: 'domcontentloaded' });
            await settle(visitor.page);
            must((await visitor.page.locator('body').innerText()).includes('Customer log in'), 'No login button');
            await visitor.context.close();
        }),
    );

    await step('Everything still works with all four switched off', () =>
        restoring(async () => {
            for (const [id] of [...SWITCHES, ['inventory_tracking']]) await flip(id, false);
            const gone = ['/balances', '/balance', '/company/landing', '/assistant'];
            const before = report.issues.length;
            for (const role of ['tech', 'customer']) {
                const ctx = await session(browser, { ...desktop }, role, creds, { block: true });
                for (const path of PAGES[role].filter((x) => !gone.includes(x))) await open(ctx, path, { keep: false });
                await ctx.context.close();
            }
            office.block = false;
            office.allow404 = false;
            for (const path of PAGES.office.filter((x) => !gone.includes(x))) await open(office, path, { keep: false });
            office.allow404 = true;
            must(report.issues.length === before, `${report.issues.length - before} problem(s), listed above`);
            return `${PAGES.office.length + PAGES.tech.length + PAGES.customer.length - 5} screens`;
        }),
    );

    await step('A tech finishes a visit on a phone, and it reaches the office', async () => {
        const tech = await session(browser, phone, 'tech', creds, { block: false });
        const t = tech.page;
        await t.goto(`${BASE}/field`, { waitUntil: 'domcontentloaded' });
        await settle(t);
        await t.waitForTimeout(800);
        await t.locator(`li:has-text("${creds.nextPool}")`).first().tap();
        await t.waitForTimeout(700);
        const pick = async (label, typed) => {
            await t.locator(`[aria-label="${label}"]`).last().tap();
            await t.waitForTimeout(400);
            await t.keyboard.type(typed, { delay: 20 });
            await t.waitForTimeout(200);
            await t.locator('input[role="combobox"] ~ ul li').first().tap();
            await t.waitForTimeout(300);
        };
        await t.getByRole('button', { name: 'Add one' }).tap();
        await pick('Chemical', 'muri');
        await t.locator('[aria-label="Amount"]').last().fill('2');
        await t.getByRole('button', { name: 'Add a part' }).tap();
        await pick('Part', 'or-112');
        await t.screenshot({ path: join(SHOTS, 'flow-field-visit.jpg'), type: 'jpeg', quality: 55, scale: 'css' });
        await t
            .getByRole('button', { name: /Finish visit/ })
            .first()
            .tap();
        await t.waitForTimeout(7000);
        must(/4 of 6 done/.test(await t.locator('body').innerText()), 'The route doesn’t show the visit as done');
        await tech.context.close();
        await p.goto(`${BASE}/reports`, { waitUntil: 'domcontentloaded' });
        await settle(p);
        must((await p.locator('body').innerText()).includes(creds.nextPool), 'The report isn’t in the office');
        await p.goto(`${BASE}/inventory`, { waitUntil: 'domcontentloaded' });
        await settle(p);
        must(/Muriatic Acid[\s\S]{0,60}15 gal/.test(await p.locator('body').innerText()), 'Stock didn’t go down by 2');
    });

    office.allow404 = false;
    office.where = { path: '/people', action: 'end-to-end' };
    const body = async (page) => (await page.locator('body').innerText()).replace(/\s+/g, ' ');
    const goto = async (page, path) => {
        await page.goto(BASE + path, { waitUntil: 'domcontentloaded' });
        await settle(page);
    };
    const saved = async (page) => {
        await page.waitForTimeout(500);
        await settle(page);
    };

    await step('The office adds a customer and their first pool', async () => {
        await goto(p, '/people');
        await p.locator('button[title="New customer"]').click();
        await p.fill('#first_name', 'Hana');
        await p.fill('#last_name', 'Flowtest');
        await p.fill('#email', 'hana@example.test');
        await p.fill('#phone', '912-555-0142');
        await p.fill('#pool_name', 'Flowtest Pool');
        await p.fill('#pool_volume', '15000');
        await p.getByRole('button', { name: 'Add customer' }).click();
        await saved(p);
        await goto(p, '/people?search=Flowtest');
        must((await p.locator('tbody').innerText()).includes('Flowtest'), 'The new customer isn’t in the People list');
        await goto(p, '/pools');
        must((await body(p)).includes('Flowtest Pool'), 'The new pool isn’t in Pools');
    });

    const customer = await session(browser, desktop, 'customer', creds, { block: false });
    const c = customer.page;

    await step('A charge and a partial payment show up on the customer’s own bill', async () => {
        await goto(p, '/balances');
        await p.locator('button[title="Add charge"]').click();
        await p.locator('#ch_cust').click();
        await p.keyboard.type('Casey', { delay: 20 });
        await p.waitForTimeout(200);
        await p.locator('input[role="combobox"] ~ ul li').first().click();
        await p.fill('#ch_desc', 'Filter replacement');
        await p.fill('#ch_amt', '40');
        await p.locator('form:has(#ch_amt) button[type="submit"]').click();
        await saved(p);
        await goto(p, '/balances');
        await p.locator('tbody tr:has-text("Casey")').first().click();
        await saved(p);
        must((await body(p)).includes('$90.00'), 'The $50 visit plus the $40 charge isn’t $90 owed');
        await p.locator('[aria-label="Amount paid"]').first().fill('15');
        await p.locator('form:has([aria-label="Amount paid"]) button[type="submit"]').first().click();
        await saved(p);
        await goto(c, '/balance');
        const bill = await body(c);
        must(bill.includes('$75.00'), 'The customer’s bill doesn’t show $75 left to pay');
        must(bill.includes('Filter replacement'), 'The charge isn’t itemized on the customer’s bill');
    });

    const askForFilterClean = async (message) => {
        await goto(c, '/requests');
        await c.locator('li:has-text("Filter Clean") button:has-text("Request")').first().click();
        await c.waitForTimeout(400);
        await c.fill('#message', message);
        await c.locator('[role="dialog"] button[type="submit"]').click();
        await saved(c);
        await goto(c, '/requests');
        must((await body(c)).includes(message), 'The request isn’t on the customer’s list');
    };
    const openRequest = async () => {
        await goto(p, '/inbox');
        await p.locator('tbody tr:has-text("Filter Clean")').first().click();
        await saved(p);
    };

    await step('A customer’s request is declined, and they see why', async () => {
        await askForFilterClean('Click test: please decline this one.');
        await openRequest();
        await p.getByRole('button', { name: 'Decline' }).click();
        await p.fill('#dc_reply', 'Sorry, we are fully booked this week.');
        await p.locator('form:has(#dc_reply) button[type="submit"]').click();
        await saved(p);
        await goto(c, '/requests');
        must((await body(c)).includes('fully booked this week'), 'The customer can’t see the answer');
    });

    await step('A customer’s request is approved, and the service is set up', async () => {
        await askForFilterClean('Click test: please approve this one.');
        await openRequest();
        await p.getByRole('button', { name: 'Approve & schedule' }).click();
        await p.waitForTimeout(300);
        await p.fill('#ap_reply', 'All set, see you soon.');
        await p.locator('form:has(#ap_reply) button[type="submit"]').click();
        await saved(p);
        await goto(c, '/requests');
        const seen = await body(c);
        must(seen.includes('All set, see you soon'), 'The customer can’t see the answer');
        must(/Set up: \S/.test(seen), 'The customer isn’t told the service was set up');
    });

    await customer.context.close();
    await office.context.close();
    await browser.close();
}

// ---- staging's throwaway company

function company(action) {
    execFileSync('scp', ['-q', join(ROOT, 'scripts/click-test-company.php'), `${SSH}:/tmp/click-test-company.php`], { timeout: 60000 });
    const out = execFileSync(
        'ssh',
        [SSH, `cd ${APP_DIR} && CLICK_ACTION=${action} php artisan tinker --execute='include "/tmp/click-test-company.php";'`],
        { encoding: 'utf8', timeout: 180000, maxBuffer: 1e7 },
    );
    const json = out.match(/CLICK_JSON=(\{.*\})/);
    if (!json) throw new Error(`Company ${action} failed:\n${out.slice(-600)}`);
    return JSON.parse(json[1]);
}

// ---- the report

const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);

function writeReport(commit, minutes) {
    const failed = report.issues.length + report.flows.filter((f) => !f.ok).length;
    const issueRows = report.issues
        .map(
            (i) =>
                `<tr><td>${esc(i.target)}</td><td>${esc(i.role)}</td><td><code>${esc(i.path)}</code><br><small>${esc(i.action)}</small></td><td><b>${esc(i.kind)}</b><br>${esc(i.detail)}</td><td>${i.shot ? `<a href="shots/${esc(i.shot)}">screenshot</a>` : ''}</td></tr>`,
        )
        .join('');
    const flowRows = report.flows
        .map((f) => `<tr><td class="${f.ok ? 'ok' : 'bad'}">${f.ok ? 'PASS' : 'FAIL'}</td><td>${esc(f.name)}</td><td>${esc(f.detail)}</td></tr>`)
        .join('');
    const groups = {};
    for (const s of report.shots) ((groups[s.role] ??= {})[s.path] ??= []).push(s);
    const gallery = Object.entries(groups)
        .map(
            ([role, paths]) =>
                `<h3>${esc(role)}</h3>` +
                Object.entries(paths)
                    .map(
                        ([path, shots]) =>
                            `<details><summary><code>${esc(path)}</code></summary><div class="grid">${shots.map((s) => `<a href="shots/${esc(s.file)}"><img loading="lazy" src="shots/${esc(s.file)}" alt=""><span>${esc(s.target)}</span></a>`).join('')}</div></details>`,
                    )
                    .join(''),
        )
        .join('');
    writeFileSync(join(OUT, 'report.json'), JSON.stringify({ ...report, commit, minutes, failed }, null, 1));
    writeFileSync(
        join(OUT, 'index.html'),
        `<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>RoutePilot click test</title>
<style>:root{color-scheme:dark}body{margin:0 auto;max-width:1100px;padding:24px 16px;background:#0b1020;color:#d7e2f0;font:15px/1.5 ui-monospace,Menlo,Consolas,monospace}h1{font-size:22px;margin:0 0 4px}h2{margin-top:32px;font-size:17px;border-bottom:1px solid #27324a;padding-bottom:6px}h3{color:#7dd3fc;text-transform:uppercase;font-size:13px;letter-spacing:.08em}.big{font-size:34px;font-weight:700;margin:12px 0}.ok{color:#4ade80}.bad{color:#f87171}.stats{display:flex;flex-wrap:wrap;gap:12px}.stats div{border:1px solid #27324a;border-radius:8px;padding:10px 14px}.stats b{display:block;font-size:22px}table{width:100%;border-collapse:collapse;font-size:13px}td{border-top:1px solid #27324a;padding:8px;vertical-align:top;overflow-wrap:anywhere}code{color:#fbbf24}a{color:#7dd3fc}summary{cursor:pointer;padding:3px 0}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px;padding:10px 0}.grid img{width:100%;height:150px;object-fit:cover;object-position:top;border:1px solid #27324a;border-radius:6px}.grid a{text-decoration:none;font-size:12px}.grid span{display:block}</style>
<h1>RoutePilot click test</h1><div>${esc(report.started.toLocaleString('en-US', { timeZone: 'America/New_York' }))} ET · commit <code>${esc(commit)}</code> · ${minutes} min</div>
<div class="big ${failed ? 'bad' : 'ok'}">${failed ? `${failed} problem${failed === 1 ? '' : 's'} found` : 'All clear'}</div>
<div class="stats"><div><b>${report.pages}</b>screens checked</div><div><b>${report.clicks}</b>things clicked</div><div><b>${report.flows.filter((f) => f.ok).length}/${report.flows.length}</b>end-to-end checks</div><div><b>${TARGETS.length}</b>browser + screen combos</div><div><b>${report.blocked}</b>changes blocked while clicking</div><div><b>${report.known}</b>known staging messages ignored</div></div>
${report.notes.map((n) => `<p>${esc(n)}</p>`).join('')}
<h2>Problems</h2>${issueRows ? `<table>${issueRows}</table>` : '<p class="ok">None.</p>'}
<h2>End-to-end checks</h2>${flowRows ? `<table>${flowRows}</table>` : '<p>Not run.</p>'}
<h2>Screenshots</h2>${gallery}`,
    );
    return failed;
}

// ---- run

const started = Date.now();
rmSync(OUT, { recursive: true, force: true });
mkdirSync(SHOTS, { recursive: true });
let creds;
try {
    console.log(`Setting up the throwaway company on ${SSH}…`);
    creds = company('setup');
    for (const target of TARGETS) {
        if (target.engine === 'firefox' && !existsSync(firefoxPath() ?? '')) {
            report.notes.push(`Skipped ${target.id}: Firefox isn't installed here.`);
            continue;
        }
        await runTarget(target, creds);
    }
    if (!process.env.CLICK_NO_FLOWS) await runFlows(creds);
} catch (e) {
    console.error(e);
    report.flows.push({ name: 'The run itself', ok: false, detail: e.message.slice(0, 300) });
} finally {
    try {
        company('teardown');
        console.log('\nThrowaway company removed.');
    } catch (e) {
        console.error('TEARDOWN FAILED:', e.message);
        report.flows.push({ name: 'Removing the throwaway company', ok: false, detail: e.message.slice(0, 300) });
    }
}
const commit = execFileSync('git', ['rev-parse', '--short', 'HEAD'], { cwd: ROOT, encoding: 'utf8' }).trim();
const failed = writeReport(commit, Math.round((Date.now() - started) / 60000));
if (!process.env.CLICK_NO_PUBLISH) {
    try {
        execFileSync('rsync', ['-az', '--delete', `${OUT}/`, `${SSH}:${APP_DIR}/public/click-report/`], { timeout: 180000 });
        console.log(`Report: ${BASE}/click-report/index.html`);
    } catch (e) {
        console.error('Could not copy the report to staging:', e.message);
    }
}
console.log(
    `\n${failed ? `✗ ${failed} problem(s)` : '✓ All clear'} · ${report.pages} screens · ${report.clicks} clicks · ${report.flows.filter((f) => f.ok).length}/${report.flows.length} end-to-end · local report: ${join(OUT, 'index.html')}`,
);
process.exit(failed ? 1 : 0);
