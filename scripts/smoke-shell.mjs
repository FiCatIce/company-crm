/*
 * Browser smoke for the persistent shell (R-B): proves the CLIENT app is
 * actually alive, not just server-rendered.
 *
 * Why this exists: with dev SSR active, a page whose JS modules fail to
 * load (e.g. Vite "504 Outdated Optimize Dep" after a poisoned dep cache)
 * still LOOKS perfect — but nothing is clickable: no user menu, no
 * logout, no sidebar collapse. Pest can't catch that class of failure
 * (it never runs a browser), so this script drives a real one.
 *
 * Prerequisites: a running app server and assets (dev server or build):
 *   php artisan serve --port=8123   (or set SMOKE_BASE_URL)
 *   npm run dev                     (or a fresh `npm run build`)
 * Run:
 *   npm run smoke:shell
 * Env overrides: SMOKE_BASE_URL, SMOKE_EMAIL, SMOKE_PASSWORD.
 *
 * Asserts, in order:
 *   1. zero console errors / page errors while loading /dashboard
 *   2. the user menu dropdown OPENS on click and shows the Log out item
 *   3. sidebar collapse toggles (second, independent shell interaction)
 *   4. Log out actually logs out (redirects to /login as guest)
 */
import { chromium } from 'playwright';

const BASE = process.env.SMOKE_BASE_URL ?? 'http://localhost:8123';
const EMAIL = process.env.SMOKE_EMAIL ?? 'manager@crm.test';
const PASSWORD = process.env.SMOKE_PASSWORD ?? 'password';

const failures = [];
const check = (ok, label) => {
    console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}`);

    if (!ok) {
        failures.push(label);
    }
};

const browser = await chromium.launch({ channel: 'msedge', headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

const consoleErrors = [];
page.on('console', (m) => {
    if (m.type() === 'error') {
        consoleErrors.push(m.text().slice(0, 200));
    }
});
page.on('pageerror', (e) => consoleErrors.push(String(e).slice(0, 200)));

try {
    await page.goto(`${BASE}/login`, {
        waitUntil: 'networkidle',
        timeout: 20000,
    });
} catch {
    console.error(
        `Cannot reach ${BASE} — start the app server first (see header).`,
    );
    process.exit(2);
}

// Login via fetch + XSRF: Inertia v2 form hydration is flaky under automation.
await page.evaluate(
    async ({ email, password }) => {
        const xsrf = decodeURIComponent(
            document.cookie.match(/XSRF-TOKEN=([^;]+)/)[1],
        );
        await fetch('/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ email, password }),
            redirect: 'manual',
        });
    },
    { email: EMAIL, password: PASSWORD },
);

await page.goto(`${BASE}/dashboard`, { waitUntil: 'networkidle' });
await page.waitForTimeout(800); // allow hydration

check(
    consoleErrors.length === 0,
    `no console/page errors on load${consoleErrors.length ? ` — got: ${consoleErrors[0]}` : ''}`,
);

// 2. user menu opens and offers Log out
const trigger = page.locator('[data-test="user-menu-button"]');
check((await trigger.count()) === 1, 'user menu trigger rendered');
await trigger.click().catch(() => {});
await page.waitForTimeout(400);
check(
    (await trigger.getAttribute('data-state')) === 'open',
    'user menu dropdown opens on click',
);
const logoutItem = page.locator('[data-test="logout-button"]');
check(
    (await logoutItem.count()) === 1 && (await logoutItem.isVisible()),
    'Log out item visible inside the open menu',
);
await page.keyboard.press('Escape');
await page.waitForTimeout(200);

// 3. sidebar collapse still interactive (independent shell control)
const sidebar = page
    .locator('[data-slot="sidebar"], [data-state][data-collapsible]')
    .first();
const sidebarBtn = page.locator('[data-sidebar="trigger"]').first();
const sBefore = await sidebar.getAttribute('data-state');
await sidebarBtn.click().catch(() => {});
await page.waitForTimeout(300);
const sAfter = await sidebar.getAttribute('data-state');
check(sBefore !== sAfter, `sidebar collapse toggles (${sBefore} -> ${sAfter})`);

// 4. logout completes the story: dropdown -> Log out -> session gone
//    (logout redirects to the guest landing `/`; the real proof is that
//    /dashboard now bounces to /login)
await trigger.click().catch(() => {});
await page.waitForTimeout(300);
await logoutItem.click().catch(() => {});
await page
    .waitForURL((u) => !u.pathname.startsWith('/dashboard'), { timeout: 10000 })
    .catch(() => {});
await page.goto(`${BASE}/dashboard`, { waitUntil: 'networkidle' });
check(
    page.url().includes('/login'),
    `after Log out, /dashboard redirects to /login (at: ${page.url()})`,
);

await browser.close();

if (failures.length) {
    console.error(`\n${failures.length} smoke check(s) FAILED`);
    process.exit(1);
}

console.log('\nshell smoke: all green');
