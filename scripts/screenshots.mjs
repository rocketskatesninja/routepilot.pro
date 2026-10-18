/**
 * RoutePilot marketing screenshot helper.
 *
 * Recaptures the product screenshots used on the public marketing surfaces by
 * driving a headless Chrome through the live demo tenant:
 *   - the login-page showcase carousel  → AuthLayout.vue   (desktop + phone, light/dark)
 *   - the landing hero demo-site preview → Welcome.vue      (light/dark)
 *
 * It writes straight into public/assets/images/screenshots, so a run replaces
 * the committed assets in place — review the diff, then commit.
 *
 * Usage:
 *   npm run screenshots             # everything (app carousel + landing)
 *   npm run screenshots app         # just the login-page carousel shots
 *   npm run screenshots landing     # just the demo-tenant landing hero
 *
 * Env overrides:
 *   SCREENSHOT_BASE   base URL              (default https://routepilot.pro)
 *   SCREENSHOT_DATE   schedule date to load (default today; needs the demo agent's stops on that day)
 *   CHROME_PATH       chrome executable     (default /usr/bin/google-chrome)
 *   DEMO_PASSWORD     demo login password   (default "password")
 *   SCREENSHOT_INSECURE=1   accept a self-signed or expired certificate
 *   SCREENSHOT_HOST_RULES   point a hostname at another box (Chrome's
 *                           --host-resolver-rules), e.g. "MAP test.routepilot.pro
 *                           192.168.0.80" to shoot staging under a name the
 *                           Google Maps key allows
 *
 * Requires a Chrome/Chromium binary on the machine (uses playwright-core, which
 * does NOT bundle browsers) and the demo tenant seeded on the target host.
 */
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';

const BASE = process.env.SCREENSHOT_BASE ?? 'https://routepilot.pro';
const DATE = process.env.SCREENSHOT_DATE ?? new Date().toISOString().slice(0, 10);
const CHROME = process.env.CHROME_PATH ?? '/usr/bin/google-chrome';
const PASSWORD = process.env.DEMO_PASSWORD ?? 'password';
const INSECURE = process.env.SCREENSHOT_INSECURE === '1';
const HOST_RULES = process.env.SCREENSHOT_HOST_RULES ? [`--host-resolver-rules=${process.env.SCREENSHOT_HOST_RULES}`] : [];

const OUT = resolve(dirname(fileURLToPath(import.meta.url)), '../public/assets/images/screenshots');

// Demo logins (seeded on serv). Each surface is shot from the role that owns it.
const TENANT = 'tenant@routepilot.pro';
const AGENT = 'agent@routepilot.pro';
const CUSTOMER = 'customer@routepilot.pro';

// The login-page carousel cycles these (see AuthLayout.vue `shots` / `phones`).
const DESKTOP_SHOTS = ['dashboard', 'route-map', 'reports', 'pools'];
const DESKTOP_VIEWPORT = { width: 1440, height: 900 };
const PHONE_VIEWPORT = { width: 390, height: 844 };
const PHONE_CLIP = { x: 0, y: 0, width: 390, height: 640 }; // trim trailing whitespace

const which = (process.argv[2] ?? 'all').toLowerCase();
const wantApp = which === 'all' || which === 'app';
const wantLanding = which === 'all' || which === 'landing';

/** Log in and land on the dashboard, returning the page. */
async function login(ctx, email) {
    const page = await ctx.newPage();
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    await page.fill('#email', email);
    await page.fill('#password', PASSWORD);
    await page.click('button[type=submit]');
    await page.waitForURL('**/dashboard', { timeout: 25000 });
    return page;
}

/** Navigate, optionally open the first table row (detail panes), settle, shoot. */
async function shoot(page, path, file, { openRow = false, settle = 2500, clip } = {}) {
    await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    if (openRow) {
        await page
            .locator('tbody tr')
            .first()
            .click({ timeout: 5000 })
            .catch(() => {});
    }
    await page.waitForTimeout(settle);
    await page.screenshot({ path: `${OUT}/${file}`, ...(clip ? { clip } : {}) });
    console.log('  ✓', file);
}

/** Desktop carousel shots for one colour scheme. `dir` is '' (light) or 'dark/'. */
async function desktop(browser, scheme, dir) {
    const ctx = await browser.newContext({ viewport: DESKTOP_VIEWPORT, deviceScaleFactor: 2, colorScheme: scheme, ignoreHTTPSErrors: INSECURE });
    const page = await login(ctx, TENANT);
    await page.waitForTimeout(2500);
    await page.screenshot({ path: `${OUT}/${dir}dashboard.png` });
    console.log('  ✓', `${dir}dashboard.png`);
    await shoot(page, `/schedule?date=${DATE}`, `${dir}route-map.png`, { settle: 6000 }); // let the map tiles draw
    await shoot(page, '/reports', `${dir}reports.png`, { openRow: true });
    await shoot(page, '/pools', `${dir}pools.png`, { openRow: true });
    await ctx.close();
    console.log(`desktop ${scheme} done`);
}

/** Phone carousel shots for one colour scheme: the tech's field app and the customer's portal. */
async function phone(browser, scheme, dir) {
    const opt = { viewport: PHONE_VIEWPORT, deviceScaleFactor: 3, isMobile: true, hasTouch: true, colorScheme: scheme, ignoreHTTPSErrors: INSECURE };

    let ctx = await browser.newContext(opt);
    let page = await login(ctx, AGENT);
    await shoot(page, '/field', `${dir}phone-day.png`, { settle: 2000, clip: PHONE_CLIP });
    // A visit's water test, each reading checked against the pool's targets as it's typed.
    await page.locator('main ol li').first().click();
    await page.waitForTimeout(800);
    const inputs = page.locator('section:has(h3:text-is("Water test")) input[type=number]');
    for (const [i, v] of ['0.3', '0.4', '7.9', '60'].entries()) await inputs.nth(i).fill(v);
    await page.locator('h3:text-is("Water test")').evaluate((el) => el.scrollIntoView({ block: 'start' }));
    await page.waitForTimeout(400);
    await page.screenshot({ path: `${OUT}/${dir}phone-water.png`, clip: PHONE_CLIP });
    console.log('  ✓', `${dir}phone-water.png`);
    await ctx.close();

    // The customer's simple Home (they choose it over the widget grid in the page header).
    ctx = await browser.newContext(opt);
    page = await login(ctx, CUSTOMER);
    const simple = page.locator('[aria-label="Dashboard style"] button:has-text("Simple")');
    await simple.click({ timeout: 3000 }).catch(() => {});
    await page.locator('[aria-label="Dashboard style"] button[aria-pressed="true"]:has-text("Simple")').waitFor({ timeout: 10000 });
    await shoot(page, '/dashboard', `${dir}phone-portal.png`, { settle: 2000, clip: PHONE_CLIP });
    await ctx.close();
    console.log(`phone ${scheme} done`);
}

/** Landing hero — the public demo-tenant site, light + dark, as a compact JPEG. */
async function landing(browser) {
    for (const [scheme, dir] of [
        ['light', ''],
        ['dark', 'dark/'],
    ]) {
        const ctx = await browser.newContext({ viewport: DESKTOP_VIEWPORT, deviceScaleFactor: 2, colorScheme: scheme, ignoreHTTPSErrors: INSECURE });
        const page = await ctx.newPage();
        await page.goto(`${BASE}/t/demo`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(3500);
        await page.screenshot({ path: `${OUT}/${dir}demo-landing.jpg`, type: 'jpeg', quality: 84 });
        console.log('  ✓', `${dir}demo-landing.jpg`);
        await ctx.close();
    }
    console.log('landing done');
}

const browser = await chromium.launch({ executablePath: CHROME, args: ['--no-sandbox', ...HOST_RULES] });
try {
    console.log(`Capturing from ${BASE} → ${OUT}`);
    if (wantApp) {
        await desktop(browser, 'light', '');
        await desktop(browser, 'dark', 'dark/');
        await phone(browser, 'light', '');
        await phone(browser, 'dark', 'dark/');
    }
    if (wantLanding) {
        await landing(browser);
    }
    console.log('ALL DONE');
} finally {
    await browser.close();
}
