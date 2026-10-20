/**
 * DIQ-1205: records the DriveQ demo videos from the running app.
 *
 *   node marketing/video/record.cjs --lang en --format walkthrough
 *   node marketing/video/record.cjs --lang hi --format short
 *
 * Needs: the app running with demo data (`make demo`, API on :8000, web on
 * :5173), Playwright, and ffmpeg (FFMPEG=/path/to/ffmpeg) for MP4 output.
 * Env: WEB, API, OUT (output folder), CLAIM_URL (a claim link for the last scene).
 */
const path = require('path');
const fs = require('fs');
const { execFileSync, execSync } = require('child_process');

let playwright;
try {
  playwright = require('playwright');
} catch {
  playwright = require(execSync('npm root -g').toString().trim() + '/playwright');
}

const arg = (name, fallback) => {
  const i = process.argv.indexOf('--' + name);
  return i > -1 ? process.argv[i + 1] : fallback;
};
const LANG = arg('lang', 'en');
const FORMAT = arg('format', 'walkthrough'); // walkthrough | short
const WEB = process.env.WEB || 'http://localhost:5173';
const API = process.env.API || 'http://127.0.0.1:8000';
const OUT = process.env.OUT || path.join(__dirname, 'out');
const CAPTIONS = JSON.parse(fs.readFileSync(path.join(__dirname, 'captions.json'), 'utf8'))[LANG];
const SHORT = FORMAT === 'short';
const PACE = SHORT ? 0.5 : 1.4; // the short cut lingers less on each scene

// Walkthrough: 1920×1080. Short: phone layout at 540×960, recorded at 1080×1920.
const VIEWPORT = SHORT ? { width: 540, height: 960 } : { width: 1920, height: 1080 };
const VIDEO = SHORT ? { width: 1080, height: 1920 } : { width: 1920, height: 1080 };

const wait = (page, ms) => page.waitForTimeout(ms * PACE);

/** Caption bar, cursor dot and end card, injected into every page. */
function overlayScript() {
  const style = document.createElement('style');
  style.textContent = `
    #dq-caption{position:fixed;left:50%;bottom:4%;transform:translateX(-50%);z-index:2147483647;max-width:88%;
      background:rgba(17,24,39,.88);color:#fff;font:600 clamp(16px,2.1vw,34px)/1.35 'Noto Sans Devanagari',Inter,Arial,sans-serif;
      padding:.55em 1em;border-radius:.6em;text-align:center;box-shadow:0 8px 30px rgba(0,0,0,.25);transition:opacity .3s}
    #dq-cursor{position:fixed;width:22px;height:22px;margin:-11px 0 0 -11px;border-radius:50%;background:rgba(37,99,235,.35);
      border:2px solid #2563eb;z-index:2147483646;pointer-events:none;transition:transform .15s}
    #dq-cursor.down{transform:scale(.7)}
    #dq-end{position:fixed;inset:0;z-index:2147483647;display:flex;flex-direction:column;align-items:center;justify-content:center;
      background:linear-gradient(135deg,#1e3a8a,#2563eb);color:#fff;font-family:'Noto Sans Devanagari',Inter,Arial,sans-serif;text-align:center;padding:5%}
    #dq-end h1{font-size:clamp(48px,9vw,140px);margin:0;font-weight:800;letter-spacing:-.03em}
    #dq-end h1 span{color:#facc15}
    #dq-end p{font-size:clamp(18px,2.6vw,40px);margin:.6em 0 0;opacity:.95}`;
  document.addEventListener('DOMContentLoaded', () => {
    document.head.appendChild(style);
    const dot = document.createElement('div');
    dot.id = 'dq-cursor';
    document.body.appendChild(dot);
    document.addEventListener('mousemove', (e) => { dot.style.left = e.clientX + 'px'; dot.style.top = e.clientY + 'px'; });
    document.addEventListener('mousedown', () => dot.classList.add('down'));
    document.addEventListener('mouseup', () => dot.classList.remove('down'));
  });
  try {
    localStorage.setItem('driveiq_cookie_consent', 'essential');
  } catch {}
}

async function caption(page, key) {
  const text = CAPTIONS[key];
  await page.evaluate((t) => {
    let el = document.getElementById('dq-caption');
    if (!el) {
      el = document.createElement('div');
      el.id = 'dq-caption';
      document.body.appendChild(el);
    }
    el.textContent = t;
  }, text);
}

async function go(page, url) {
  await page.goto(WEB + url, { waitUntil: 'networkidle' });
  await passGate(page);
  // Let data-driven panels finish loading before the camera lingers.
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(700);
}

/** The one-time "how we use your data" panel shown on first portal visit. */
async function passGate(page) {
  await page.waitForTimeout(500);
  const gate = page.locator('div.fixed.inset-0').filter({ has: page.locator('button[role="checkbox"]') });
  if (await gate.count()) {
    await gate.locator('button[role="checkbox"]').first().click();
    await gate.getByRole('button', { name: /continue to portal/i }).click();
    await page.waitForTimeout(600);
  }
}

async function click(page, locator) {
  const el = typeof locator === 'string' ? page.locator(locator).first() : locator.first();
  await el.scrollIntoViewIfNeeded();
  const box = await el.boundingBox();
  if (box) {
    await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2, { steps: 18 });
    await page.waitForTimeout(250);
  }
  await el.click();
}

async function type(page, selector, text) {
  await click(page, selector);
  await page.locator(selector).first().pressSequentially(text, { delay: SHORT ? 35 : 60 });
}

async function scroll(page, px, ms = 1200) {
  const steps = 20;
  for (let i = 0; i < steps; i++) {
    await page.mouse.wheel(0, px / steps);
    await page.waitForTimeout(ms / steps);
  }
}

async function endCard(page) {
  await page.evaluate(([title, sub]) => {
    document.getElementById('dq-caption')?.remove();
    const el = document.createElement('div');
    el.id = 'dq-end';
    el.innerHTML = '<h1>Drive<span>Q</span></h1><p></p>';
    el.querySelector('p').textContent = sub;
    document.body.appendChild(el);
  }, [CAPTIONS.end_title, CAPTIONS.end_sub]);
  await page.waitForTimeout(3500);
}

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await playwright.chromium.launch();
  const context = await browser.newContext({
    viewport: VIEWPORT,
    deviceScaleFactor: 1,
    isMobile: SHORT,
    hasTouch: SHORT,
    geolocation: { latitude: 18.5590, longitude: 73.7868 }, // Baner
    permissions: ['geolocation'],
    recordVideo: { dir: OUT, size: VIDEO },
  });
  await context.addInitScript(overlayScript);
  const page = await context.newPage();
  page.on('dialog', (d) => d.accept());

  // 1. Learner: home.
  await go(page, '/');
  await caption(page, 'intro');
  await wait(page, 3500);
  if (!SHORT) {
    await scroll(page, 500, 1600);
    await wait(page, 1200);
  }

  // 2. Near me.
  await go(page, '/search');
  await caption(page, 'near');
  if (SHORT) {
    await click(page, page.getByRole('button', { name: /near me/i }));
  } else {
    await click(page, '[data-testid="button-use-my-location"]');
  }
  await page.waitForTimeout(600);
  await click(page, page.getByRole('button', { name: /^continue$/i }));
  await page.waitForTimeout(1500);
  await wait(page, 2500);

  // 3. Filters (desktop only: the phone cut keeps it short).
  if (!SHORT) {
    await caption(page, 'filter');
    await click(page, '[data-testid="checkbox-women"]');
    await page.waitForTimeout(1200);
    await scroll(page, 400, 1200);
    await wait(page, 2000);
    await click(page, '[data-testid="checkbox-women"]');
    await page.waitForTimeout(800);
  }

  // 4. School page.
  await go(page, '/school/skyline-driving-academy');
  await caption(page, 'compare');
  await wait(page, 1800);
  await scroll(page, SHORT ? 1400 : 900, SHORT ? 1500 : 2500);
  await wait(page, 2000);

  // 5. Enquiry.
  await caption(page, 'enquire');
  await click(page, '[data-testid="button-send-inquiry"]');
  await page.waitForTimeout(600);
  await type(page, '[data-testid="input-inquiry-name"]', 'Rohit Kulkarni');
  await type(page, '[data-testid="input-inquiry-phone"]', '98220 11223');
  await click(page, '[data-testid="checkbox-inquiry-whatsapp"]');
  await page.waitForTimeout(3200); // the form's anti-bot minimum fill time
  await click(page, '[data-testid="button-submit-inquiry"]');
  await wait(page, 2500);

  // 6. School owner sees it. The short cut skips the login screen.
  if (SHORT) {
    const res = await page.request.post(API + '/api/auth/login', {
      headers: { Accept: 'application/json' },
      data: { email: 'info@skylinedrive.in', password: 'password123' },
    });
    const { token } = await res.json();
    await page.evaluate((t) => localStorage.setItem('driveiq_auth_token', t), token);
  } else {
    await go(page, '/auth/login');
    await type(page, '[data-testid="input-login-email"]', 'info@skylinedrive.in');
    await type(page, '[data-testid="input-login-password"]', 'password123');
    await click(page, '[data-testid="button-login-submit"]');
    await page.waitForURL('**/dashboard', { timeout: 15000 });
  }
  await go(page, '/dashboard/leads');
  await caption(page, 'alert');
  await wait(page, 3500);

  // 7. Note + follow-up.
  const row = page.locator('tr', { hasText: 'Rohit Kulkarni' });
  await click(page, row.locator('[data-testid^="button-lead-notes-"]'));
  await page.waitForTimeout(800);
  await caption(page, 'note');
  await type(page, '#lead-note', 'Called. Wants morning batch, starts Monday.');
  await click(page, page.getByRole('button', { name: /add note/i }));
  await wait(page, 2500);
  await page.keyboard.press('Escape');

  if (!SHORT) {
    // 8. Operations.
    await go(page, '/dashboard');
    await page.locator('[data-testid="pending-tasks"]').waitFor({ timeout: 10000 }).catch(() => {});
    await caption(page, 'operate');
    await wait(page, 2500);
    await scroll(page, 500, 1500);
    await wait(page, 1500);
    await go(page, '/dashboard/schedules');
    await caption(page, 'operate');
    await wait(page, 3000);
    await go(page, '/dashboard/learners');
    await caption(page, 'operate');
    await wait(page, 1500);
    const learner = page.locator('tr', { hasText: 'Ananya Iyer' }).first();
    if (await learner.count()) {
      await click(page, learner);
      await wait(page, 3000);
      await page.keyboard.press('Escape');
    }

    // 9. Independent trainers.
    await go(page, '/search?type=trainer');
    await caption(page, 'trainers');
    await wait(page, 3000);

    // 10. Claiming a prepared listing.
    if (process.env.CLAIM_URL) {
      await page.goto(process.env.CLAIM_URL, { waitUntil: 'networkidle' });
      await caption(page, 'claim');
      await wait(page, 2500);
      await click(page, '[data-testid="button-code-email"]');
      await wait(page, 2500);
    }
  } else {
    await go(page, '/for-schools');
    await caption(page, 'claim');
    await wait(page, 2500);
  }

  await endCard(page);

  const webm = await page.video().path();
  await context.close();
  await browser.close();

  const name = `driveq-${FORMAT}-${LANG}`;
  const target = path.join(OUT, name + '.webm');
  fs.renameSync(webm, target);
  const ffmpeg = process.env.FFMPEG || 'ffmpeg';
  try {
    // The short plays 15% faster so page loads don't drag it past 45 s.
    const speed = SHORT ? ['-vf', 'setpts=PTS/1.15'] : [];
    execFileSync(ffmpeg, ['-y', '-loglevel', 'error', '-i', target, ...speed, '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-preset', 'medium', '-crf', '22', '-movflags', '+faststart', path.join(OUT, name + '.mp4')]);
    fs.unlinkSync(target);
    console.log('Wrote', path.join(OUT, name + '.mp4'));
  } catch (e) {
    console.log('Wrote', target, '(no ffmpeg for MP4:', e.message.split('\n')[0] + ')');
  }
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
