/**
 * RoutePilot full product tour — comprehensive screenshots across all three
 * roles (tenant back-office, agent field app, customer portal), for
 * portfolio/demo purposes. Distinct from screenshots.mjs, which only
 * recaptures the small curated set used on in-app marketing surfaces
 * (login carousel + landing hero).
 *
 * Writes into public/assets/images/screenshots/tour/{tenant,agent,customer}
 * (tenant and agent also get a dark/ subfolder, customer a desktop/ one).
 * Review the diff, then commit.
 *
 * Usage:
 *   npm run screenshots:tour                    # everything
 *   npm run screenshots:tour tenant customer    # just these parts (tenant, agent, customer)
 *
 * Env overrides: SCREENSHOT_BASE, SCREENSHOT_DATE, CHROME_PATH, DEMO_PASSWORD
 * — same as screenshots.mjs — plus SCREENSHOT_INSECURE=1 to accept a
 * self-signed or expired certificate, and SCREENSHOT_HOST_RULES to point a
 * hostname at another box (Chrome's --host-resolver-rules, e.g.
 * "MAP test.routepilot.pro 192.168.0.80": a staging copy served under a name
 * the Google Maps key allows). Requires the demo tenant seeded on the target
 * host, with at least one materialized stop for "today" (the field app and
 * several tenant screens key off real data for today's date).
 */
import { mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';

const BASE = process.env.SCREENSHOT_BASE ?? 'https://routepilot.pro';
const DATE = process.env.SCREENSHOT_DATE ?? new Date().toISOString().slice(0, 10);
const CHROME = process.env.CHROME_PATH ?? '/usr/bin/google-chrome';
const PASSWORD = process.env.DEMO_PASSWORD ?? 'password';
const INSECURE = process.env.SCREENSHOT_INSECURE === '1';
const PARTS = process.argv.slice(2);
const want = (part) => PARTS.length === 0 || PARTS.includes(part);

const OUT = resolve(dirname(fileURLToPath(import.meta.url)), '../public/assets/images/screenshots/tour');
for (const dir of ['tenant', 'tenant/dark', 'agent', 'agent/dark', 'customer', 'customer/desktop']) mkdirSync(`${OUT}/${dir}`, { recursive: true });

const TENANT = 'tenant@routepilot.pro';
const AGENT = 'agent@routepilot.pro';
const CUSTOMER = 'customer@routepilot.pro';

const DESKTOP_VIEWPORT = { width: 1440, height: 900 };
const PHONE_VIEWPORT = { width: 390, height: 844 };
const PHONE_CLIP = { x: 0, y: 0, width: 390, height: 844 };

/** Every tenant back-office nav destination, in sidebar order (AppSidebar.vue). */
const TENANT_PAGES = [
    { file: 'dashboard', path: '/dashboard' },
    { file: 'schedule', path: `/schedule?date=${DATE}`, settle: 6000 },
    { file: 'reports', path: '/reports', openRow: true },
    { file: 'inventory', path: '/inventory', openRow: true },
    { file: 'requests', path: '/inbox', openRow: true },
    { file: 'people', path: '/people', openRow: true },
    { file: 'pools', path: '/pools', openRow: true },
    { file: 'services', path: '/services', openRow: true },
    { file: 'balances', path: '/balances', openRow: true },
    { file: 'insights', path: '/insights' },
    { file: 'company', path: '/company' },
    { file: 'billing', path: '/billing' },
    { file: 'landing-editor', path: '/company/landing' },
    { file: 'assistant', path: '/assistant' },
];

/** The customer portal's pages; the simple Home is the portal's own front page. */
const CUSTOMER_PAGES = [
    { file: 'home', path: '/dashboard' },
    { file: 'pools', path: '/my-pools' },
    { file: 'history', path: '/history' },
    { file: 'balance', path: '/balance' },
    { file: 'services', path: '/requests' },
];

/** Out-of-range readings so the on-device chemistry engine surfaces real dosing recommendations. */
const BAD_READING = {
    free_chlorine: '0.3',
    total_chlorine: '0.4',
    ph: '7.9',
    alkalinity: '60',
    calcium_hardness: '180',
    cyanuric_acid: '20',
    salt: '',
    water_temperature: '88',
};

async function login(ctx, email) {
    const page = await ctx.newPage();
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    await page.fill('#email', email);
    await page.fill('#password', PASSWORD);
    await page.click('button[type=submit]');
    await page.waitForURL('**/dashboard', { timeout: 25000 });
    return page;
}

async function shoot(page, path, file, { openRow = false, settle = 2500 } = {}) {
    await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1200);
    if (openRow) {
        await page
            .locator('tbody tr')
            .first()
            .click({ timeout: 5000 })
            .catch(() => {});
    }
    await page.waitForTimeout(settle);
    await page.screenshot({ path: file });
    console.log('  ✓', file);
}

/** Tenant back-office: every nav page, one colour scheme. */
async function tenantTour(browser, scheme, dir) {
    const ctx = await browser.newContext({ viewport: DESKTOP_VIEWPORT, deviceScaleFactor: 2, colorScheme: scheme, ignoreHTTPSErrors: INSECURE });
    const page = await login(ctx, TENANT);
    for (const p of TENANT_PAGES) {
        await shoot(page, p.path, `${OUT}/tenant/${dir}${p.file}.png`, { openRow: p.openRow, settle: p.settle ?? 2500 });
    }
    await ctx.close();
    console.log(`tenant ${scheme} done`);
}

/** Agent field app: the full day → visit → water test → what to add → sync flow, phone viewport. */
async function agentTour(browser, scheme, dir) {
    const ctx = await browser.newContext({
        viewport: PHONE_VIEWPORT,
        deviceScaleFactor: 3,
        isMobile: true,
        hasTouch: true,
        colorScheme: scheme,
        ignoreHTTPSErrors: INSECURE,
    });
    const page = await login(ctx, AGENT);
    const snap = async (name) => {
        await page.screenshot({ path: `${OUT}/agent/${dir}${name}.png`, clip: PHONE_CLIP });
        console.log('  ✓', `agent/${dir}${name}.png`);
    };
    const scrollTo = async (heading) => {
        await page
            .locator(`h3:text-is("${heading}")`)
            .first()
            .evaluate((el) => el.scrollIntoView({ block: 'start' }));
        await page.waitForTimeout(300);
    };

    await page.goto(`${BASE}/field`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    await snap('day');

    // Open the first stop: gate code + checklist.
    await page.locator('main ol li').first().click();
    await page.waitForTimeout(800);
    const checkboxes = page.locator('section:has(h3:text-is("Checklist")) input[type=checkbox]');
    await checkboxes
        .nth(0)
        .check()
        .catch(() => {});
    await checkboxes
        .nth(1)
        .check()
        .catch(() => {});
    await page.waitForTimeout(300);
    await snap('visit-checklist');

    // Water test: each reading is checked against the pool's targets as it's typed.
    const inputs = page.locator('section:has(h3:text-is("Water test")) input[type=number]');
    const keys = ['free_chlorine', 'total_chlorine', 'ph', 'alkalinity', 'calcium_hardness', 'cyanuric_acid', 'salt', 'water_temperature'];
    for (let i = 0; i < keys.length; i++) {
        const v = BAD_READING[keys[i]];
        if (v) await inputs.nth(i).fill(v);
    }
    await scrollTo('Water test');
    await snap('visit-water');

    // The on-device chemistry engine works out real doses.
    await page.locator('button:has-text("Work out what to add")').click();
    await page.waitForTimeout(500);
    await scrollTo('What to add');
    await snap('visit-analysis');

    // Add a recommendation → it lands in "Chemicals added".
    await page
        .locator('section:has(h3:text-is("What to add")) button:has-text("Add")')
        .first()
        .click()
        .catch(() => {});
    await page.waitForTimeout(300);
    await scrollTo('Chemicals added');
    await snap('visit-treatments');

    // Close without finishing, so this stop stays pending.
    await page.locator('button[aria-label="Back to the route"]').click();
    await page.waitForTimeout(500);

    // Go offline: the route keeps working from the phone's copy.
    await ctx.setOffline(true);
    await page.locator('button[aria-label="Sync and refresh"]').click(); // network fails → falls back to the IndexedDB cache
    await page.waitForTimeout(1000);
    await snap('day-offline');

    // Finish a second stop while offline: it queues and waits to sync.
    await page.locator('main ol li').nth(1).click();
    await page.waitForTimeout(600);
    const finish = page.locator('button:has-text("Finish visit")');
    await finish.click();
    await finish.waitFor({ state: 'detached', timeout: 10000 });
    await page.waitForTimeout(500);
    await snap('day-queued');

    await ctx.close(); // note: don't setOffline(false) first — that can race a still-in-flight queued request onto the real network right before teardown
    console.log(`agent ${scheme} done`);
}

/** Customer portal: every page on a phone, and the main ones on a desktop. */
async function customerTour(browser) {
    const shots = [
        { viewport: PHONE_VIEWPORT, scale: 3, mobile: true, dir: '', pages: CUSTOMER_PAGES },
        { viewport: DESKTOP_VIEWPORT, scale: 2, mobile: false, dir: 'desktop/', pages: CUSTOMER_PAGES.filter((p) => p.file !== 'balance') },
    ];
    for (const { viewport, scale, mobile, dir, pages } of shots) {
        const ctx = await browser.newContext({
            viewport,
            deviceScaleFactor: scale,
            isMobile: mobile,
            hasTouch: mobile,
            colorScheme: 'light',
            ignoreHTTPSErrors: INSECURE,
        });
        const page = await login(ctx, CUSTOMER);
        // Customers choose a widget grid or the simple Home; the tour shows the simple one.
        await page
            .locator('[aria-label="Dashboard style"] button:has-text("Simple")')
            .click({ timeout: 3000 })
            .catch(() => {});
        await page.waitForTimeout(1000);
        for (const p of pages) {
            await page.goto(`${BASE}${p.path}`, { waitUntil: 'networkidle' });
            await page.waitForTimeout(1500);
            await page.screenshot({ path: `${OUT}/customer/${dir}${p.file}.png`, ...(mobile ? { clip: PHONE_CLIP } : {}) });
            console.log('  ✓', `customer/${dir}${p.file}.png`);
        }
        await ctx.close();
    }
    console.log('customer done');
}

const HOST_RULES = process.env.SCREENSHOT_HOST_RULES ? [`--host-resolver-rules=${process.env.SCREENSHOT_HOST_RULES}`] : [];
const browser = await chromium.launch({ executablePath: CHROME, args: ['--no-sandbox', ...HOST_RULES] });
try {
    console.log(`Capturing full tour from ${BASE} → ${OUT}`);
    if (want('tenant')) {
        await tenantTour(browser, 'light', '');
        await tenantTour(browser, 'dark', 'dark/');
    }
    if (want('agent')) {
        await agentTour(browser, 'light', '');
        await agentTour(browser, 'dark', 'dark/');
    }
    if (want('customer')) await customerTour(browser);
    console.log('ALL DONE');
} finally {
    await browser.close();
}
