/**
 * RoutePilot full product tour — comprehensive screenshots across all three
 * roles (tenant back-office, agent field app, customer portal), for
 * portfolio/demo purposes. Distinct from screenshots.mjs, which only
 * recaptures the small curated set used on in-app marketing surfaces
 * (login carousel + landing hero).
 *
 * Writes into public/assets/images/screenshots/tour/{tenant,agent,customer}
 * (tenant also gets a dark/ subfolder). Review the diff, then commit.
 *
 * Usage:
 *   npm run screenshots:tour
 *
 * Env overrides: SCREENSHOT_BASE, SCREENSHOT_DATE, CHROME_PATH, DEMO_PASSWORD
 * — same as screenshots.mjs. Requires the demo tenant seeded on the target
 * host, with at least one materialized stop for "today" (the field app and
 * several tenant screens key off real data for today's date).
 */
import { chromium } from 'playwright-core';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import { mkdirSync } from 'node:fs';

const BASE = process.env.SCREENSHOT_BASE ?? 'https://routepilot.pro';
const DATE = process.env.SCREENSHOT_DATE ?? new Date().toISOString().slice(0, 10);
const CHROME = process.env.CHROME_PATH ?? '/usr/bin/google-chrome';
const PASSWORD = process.env.DEMO_PASSWORD ?? 'password';

const OUT = resolve(dirname(fileURLToPath(import.meta.url)), '../public/assets/images/screenshots/tour');
for (const dir of ['tenant', 'tenant/dark', 'agent', 'customer']) mkdirSync(`${OUT}/${dir}`, { recursive: true });

const TENANT = 'tenant@routepilot.pro';
const AGENT = 'agent@routepilot.pro';
const CUSTOMER = 'customer@routepilot.pro';

const DESKTOP_VIEWPORT = { width: 1440, height: 900 };
const PHONE_VIEWPORT = { width: 390, height: 844 };
const PHONE_CLIP = { x: 0, y: 0, width: 390, height: 844 };

/** Every tenant back-office nav destination (AppSidebar.vue). */
const TENANT_PAGES = [
    { file: 'dashboard', path: '/dashboard' },
    { file: 'schedule', path: `/schedule?date=${DATE}`, settle: 6000 },
    { file: 'pools', path: '/pools', openRow: true },
    { file: 'people', path: '/people', openRow: true },
    { file: 'services', path: '/services', openRow: true },
    { file: 'inventory', path: '/inventory' },
    { file: 'reports', path: '/reports', openRow: true },
    { file: 'insights', path: '/insights' },
    { file: 'balances', path: '/balances', openRow: true },
    { file: 'assistant', path: '/assistant' },
    { file: 'company', path: '/company' },
    { file: 'billing', path: '/billing' },
    { file: 'landing-editor', path: '/company/landing' },
];

/** Out-of-range readings so the on-device chemistry engine surfaces real dosing recommendations. */
const BAD_READING = { free_chlorine: '0.3', total_chlorine: '0.4', ph: '7.9', alkalinity: '60', calcium_hardness: '180', cyanuric_acid: '20', salt: '', water_temperature: '88' };

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
        await page.locator('tbody tr').first().click({ timeout: 5000 }).catch(() => {});
    }
    await page.waitForTimeout(settle);
    await page.screenshot({ path: file });
    console.log('  ✓', file);
}

/** Tenant back-office: every nav page, one colour scheme. */
async function tenantTour(browser, scheme, dir) {
    const ctx = await browser.newContext({ viewport: DESKTOP_VIEWPORT, deviceScaleFactor: 2, colorScheme: scheme });
    const page = await login(ctx, TENANT);
    for (const p of TENANT_PAGES) {
        await shoot(page, p.path, `${OUT}/tenant/${dir}${p.file}.png`, { openRow: p.openRow, settle: p.settle ?? 2500 });
    }
    await ctx.close();
    console.log(`tenant ${scheme} done`);
}

/** Agent field app: the full day → visit → chemistry → sync flow, mobile viewport. */
async function agentTour(browser) {
    const ctx = await browser.newContext({ viewport: PHONE_VIEWPORT, deviceScaleFactor: 3, isMobile: true, hasTouch: true, colorScheme: 'light' });
    const page = await login(ctx, AGENT);
    await page.goto(`${BASE}/field`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    await page.screenshot({ path: `${OUT}/agent/day.png`, clip: PHONE_CLIP });
    console.log('  ✓ agent/day.png');

    // Open the first stop: checklist + access info.
    await page.locator('main li').first().click();
    await page.waitForTimeout(800);
    const checkboxes = page.locator('section:has(h2:has-text("checklist")) input[type=checkbox]');
    await checkboxes.nth(0).check().catch(() => {});
    await checkboxes.nth(1).check().catch(() => {});
    await page.waitForTimeout(300);
    await page.screenshot({ path: `${OUT}/agent/visit-checklist.png`, clip: PHONE_CLIP });
    console.log('  ✓ agent/visit-checklist.png');

    // Water test → on-device chemistry analysis with real dosing recommendations.
    const inputs = page.locator('section:has(h2:has-text("Water test")) input[type=number]');
    const keys = ['free_chlorine', 'total_chlorine', 'ph', 'alkalinity', 'calcium_hardness', 'cyanuric_acid', 'salt', 'water_temperature'];
    for (let i = 0; i < keys.length; i++) {
        const v = BAD_READING[keys[i]];
        if (v) await inputs.nth(i).fill(v);
    }
    await page.locator('button:has-text("Analyze")').click();
    await page.waitForTimeout(500);
    const analysisHeading = page.locator('h2:has-text("Analysis")').first();
    await analysisHeading.evaluate((el) => el.scrollIntoView({ block: 'start' }));
    await page.waitForTimeout(300);
    await page.screenshot({ path: `${OUT}/agent/visit-analysis.png`, clip: PHONE_CLIP });
    console.log('  ✓ agent/visit-analysis.png');

    // Apply a recommendation → Treatments applied section populates.
    await page
        .locator('section:has(h2:has-text("Analysis")) button')
        .first()
        .click()
        .catch(() => {});
    await page.waitForTimeout(300);
    const treatmentsHeading = page.locator('h2:has-text("Treatments applied")').first();
    await treatmentsHeading.evaluate((el) => el.scrollIntoView({ block: 'start' }));
    await page.waitForTimeout(300);
    await page.screenshot({ path: `${OUT}/agent/visit-treatments.png`, clip: PHONE_CLIP });
    console.log('  ✓ agent/visit-treatments.png');

    // Close without completing, so this stop stays pending.
    await page.locator('div.fixed header button').first().click();
    await page.waitForTimeout(500);

    // Go offline: cached-route banner.
    await ctx.setOffline(true);
    await page.locator('header button').last().click(); // refresh → network fails → falls back to the IndexedDB cache
    await page.waitForTimeout(1000);
    await page.screenshot({ path: `${OUT}/agent/day-offline.png`, clip: PHONE_CLIP });
    console.log('  ✓ agent/day-offline.png');

    // Complete a second stop while offline: it queues (rather than failing) and shows
    // the "waiting to sync" banner. (Previously blocked by a real bug — a Vue-reactive
    // Proxy passed straight into IndexedDB — fixed in lib/field/store.ts + Index.vue.)
    await page.locator('main li').nth(1).click();
    await page.waitForTimeout(600);
    const completeBtn = page.locator('button:has-text("Complete")');
    await completeBtn.click();
    await completeBtn.waitFor({ state: 'detached', timeout: 10000 });
    await page.waitForTimeout(500);
    await page.screenshot({ path: `${OUT}/agent/day-queued.png`, clip: PHONE_CLIP });
    console.log('  ✓ agent/day-queued.png');

    await ctx.close(); // note: don't setOffline(false) first — that can race a still-in-flight queued request onto the real network right before teardown
    console.log('agent done');
}

/** Customer portal: all four pages, mobile viewport. */
async function customerTour(browser) {
    const ctx = await browser.newContext({ viewport: PHONE_VIEWPORT, deviceScaleFactor: 3, isMobile: true, hasTouch: true, colorScheme: 'light' });
    const page = await login(ctx, CUSTOMER);
    const pages = [
        { file: 'history', path: '/history' },
        { file: 'pools', path: '/my-pools' },
        { file: 'balance', path: '/balance' },
        { file: 'requests', path: '/requests' },
    ];
    for (const p of pages) {
        await page.goto(`${BASE}${p.path}`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(1500);
        await page.screenshot({ path: `${OUT}/customer/${p.file}.png`, clip: PHONE_CLIP });
        console.log('  ✓', `customer/${p.file}.png`);
    }
    await ctx.close();
    console.log('customer done');
}

const browser = await chromium.launch({ executablePath: CHROME, args: ['--no-sandbox'] });
try {
    console.log(`Capturing full tour from ${BASE} → ${OUT}`);
    await tenantTour(browser, 'light', '');
    await tenantTour(browser, 'dark', 'dark/');
    await agentTour(browser);
    await customerTour(browser);
    console.log('ALL DONE');
} finally {
    await browser.close();
}
