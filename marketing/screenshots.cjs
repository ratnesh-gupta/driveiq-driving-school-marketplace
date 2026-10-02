/**
 * DIQ-1206: desktop and phone screenshots of the main DriveQ screens, from
 * the demo data, for decks, emails and the landing pages.
 *
 *   node marketing/screenshots.cjs
 *
 * Needs the app running with demo data (`make demo`). Env: WEB, OUT.
 */
const path = require('path');
const fs = require('fs');
const { execSync } = require('child_process');

let playwright;
try {
  playwright = require('playwright');
} catch {
  playwright = require(execSync('npm root -g').toString().trim() + '/playwright');
}

const WEB = process.env.WEB || 'http://localhost:5173';
const OUT = process.env.OUT || path.join(__dirname, 'screenshots');

const PUBLIC = [
  ['home', '/'],
  ['search', '/search'],
  ['trainers', '/search?type=trainer'],
  ['school', '/school/skyline-driving-academy'],
  ['compare', '/compare?schools=1,4,2'],
  ['for-schools', '/for-schools'],
];
const OWNER = [
  ['dashboard', '/dashboard'],
  ['leads', '/dashboard/leads'],
  ['learners', '/dashboard/learners'],
  ['schedules', '/dashboard/schedules'],
  ['billing', '/dashboard/billing'],
];
const ADMIN = [
  ['admin-prospects', '/admin/prospects'],
  ['admin-outreach', '/admin/outreach'],
];

async function passGate(page) {
  await page.waitForTimeout(500);
  const gate = page.locator('div.fixed.inset-0').filter({ has: page.locator('button[role="checkbox"]') });
  if (await gate.count()) {
    await gate.locator('button[role="checkbox"]').first().click();
    await gate.getByRole('button', { name: /continue to portal/i }).click();
    await page.waitForTimeout(600);
  }
}

async function login(page, email) {
  await page.goto(WEB + '/auth/login', { waitUntil: 'networkidle' });
  await page.fill('[data-testid="input-login-email"]', email);
  await page.fill('[data-testid="input-login-password"]', 'password123');
  await page.click('[data-testid="button-login-submit"]');
  await page.waitForTimeout(1500);
}

async function shoot(browser, device, pages, email) {
  const phone = device === 'phone';
  const context = await browser.newContext(phone
    ? { viewport: { width: 390, height: 844 }, deviceScaleFactor: 3, isMobile: true, hasTouch: true }
    : { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 });
  await context.addInitScript(() => { try { localStorage.setItem('driveiq_cookie_consent', 'essential'); } catch {} });
  const page = await context.newPage();
  if (email) await login(page, email);
  for (const [name, url] of pages) {
    await page.goto(WEB + url, { waitUntil: 'networkidle' });
    await passGate(page);
    await page.waitForTimeout(900);
    await page.screenshot({ path: path.join(OUT, `${name}-${device}.png`) });
  }
  await context.close();
}

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await playwright.chromium.launch();
  for (const device of ['desktop', 'phone']) {
    await shoot(browser, device, PUBLIC);
    await shoot(browser, device, OWNER, 'info@skylinedrive.in');
  }
  await shoot(browser, 'desktop', ADMIN, 'admin@driveiq.in');
  await browser.close();
  console.log('Screenshots in', OUT);
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
