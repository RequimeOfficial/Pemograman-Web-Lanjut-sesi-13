// Helper khusus SiLaundry (login, logout, query DB untuk verifikasi integrasi).
const { execFileSync } = require('child_process');
const { credentials, env } = require('../lib/env');

const DASHBOARD = { admin: '/dashboard/admin', kasir: '/dashboard/kasir', pelanggan: '/dashboard/pelanggan' };

/** Login lewat form memakai akun uji dari .env (password tidak pernah ditulis di kode). */
async function login(page, role) {
  const { user, pass } = credentials(role);
  await loginWith(page, user, pass);
  await page.waitForURL(`**${DASHBOARD[role]}`);
}

async function loginWith(page, email, password) {
  await page.goto('/login');
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);
  await page.getByRole('button', { name: 'Masuk' }).click();
  await page.waitForLoadState('networkidle').catch(() => {});
}

/** Logout lewat menu akun di kaki sidebar. */
async function logout(page) {
  await page.locator('aside').getByRole('button').last().click();
  await page.getByRole('button', { name: 'Keluar' }).click();
  await page.waitForURL('**/login');
}

const dbAvailable = () => !!env('MYSQL_CLI');

/** Jalankan query SELECT (read-only) dan kembalikan nilai sel pertama. */
function dbScalar(sql) {
  const args = ['-h', env('DB_HOST', '127.0.0.1'), '-u', env('DB_USERNAME', 'root'), '-N', '-B', '-e', sql, env('DB_DATABASE', 'silaundry')];
  const pass = env('DB_PASSWORD');
  if (pass) args.unshift(`-p${pass}`);
  return execFileSync(env('MYSQL_CLI'), args, { encoding: 'utf8' }).trim().split('\n')[0];
}

/** Ambil angka pada kartu ringkasan berdasarkan labelnya. */
async function statValue(page, label) {
  const card = page.locator('p.uppercase', { hasText: new RegExp(`^${label}$`, 'i') }).first().locator('xpath=..');
  const text = await card.locator('p').nth(1).innerText();
  return parseInt(text.replace(/\./g, ''), 10);
}

const uniq = (prefix) => `${prefix}${Date.now().toString(36)}`;

module.exports = { login, loginWith, logout, dbAvailable, dbScalar, statValue, uniq, DASHBOARD };
