#!/usr/bin/env node
// Simpan sesi login per role ke auth/<role>.json secara otomatis memakai akun UJI dari .env.
// Alternatif non-interaktif dari `npm run auth:save` (login manual). Kredensial tidak dicetak.
//   npm run auth:login              # semua role: admin, kasir, pelanggan
//   npm run auth:login -- admin     # satu role
const fs = require('fs');
const path = require('path');
const { chromium } = require('@playwright/test');
const { BASE_URL, VIEWPORT, AUTH_DIR, credentials } = require('../lib/env');

const roles = process.argv.slice(2).length ? process.argv.slice(2) : ['admin', 'kasir', 'pelanggan'];

(async () => {
  const browser = await chromium.launch();
  fs.mkdirSync(AUTH_DIR, { recursive: true });
  for (const role of roles) {
    const { user, pass } = credentials(role);
    const context = await browser.newContext({ viewport: VIEWPORT });
    const page = await context.newPage();
    await page.goto(`${BASE_URL}/login`);
    await page.locator('#email').fill(user);
    await page.locator('#password').fill(pass);
    await page.getByRole('button', { name: 'Masuk' }).click();
    await page.waitForURL(`**/dashboard/${role}`);
    await context.storageState({ path: path.join(AUTH_DIR, `${role}.json`) });
    console.log(`Sesi ${role} tersimpan → auth/${role}.json`);
    await context.close();
  }
  await browser.close();
})().catch((e) => { console.error(e.message); process.exit(1); });
