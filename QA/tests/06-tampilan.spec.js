// M06 Tampilan & Responsif: layar ponsel (375 px) tanpa scroll horizontal, menu sidebar dapat dibuka.
const { test, expect } = require('../lib/fixtures');
const { login } = require('./helpers');

const noHorizontalScroll = (page) =>
  page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1);

test.describe('M06 Tampilan', () => {
  test.use({ viewport: { width: 375, height: 812 } });

  test('TC042 Halaman login tampil utuh di layar ponsel', async ({ page, tc, evidence }) => {
    tc({
      module: 'M06', feature: 'F01', featureName: 'RESPONSIF', requirement: 'REQ-12',
      role: 'PUBLIC', type: 'UI', priority: 'Medium',
      expected: 'Lebar 375 px: tidak ada scroll horizontal; field Email, Kata Sandi, tombol Masuk terlihat',
    });
    await page.goto('/login');
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Masuk' })).toBeVisible();
    expect(await noHorizontalScroll(page)).toBeTruthy();
    await evidence.snap('Login pada lebar 375 px');
  });

  test('TC043 Daftar pengguna di layar ponsel: menu dapat dibuka dan halaman tidak melebar', async ({ page, tc, evidence }) => {
    tc({
      module: 'M06', feature: 'F08', featureName: 'RESPONSIF', requirement: 'REQ-12',
      role: 'ADMIN', type: 'UI', priority: 'Medium',
      expected: 'Sidebar tersembunyi, tombol "Buka menu" menampilkan sidebar; tabel bergulir di dalam kartu tanpa scroll horizontal halaman',
    });
    await login(page, 'admin');
    await page.goto('/users');
    expect(await noHorizontalScroll(page)).toBeTruthy();
    await evidence.snap('Daftar pengguna pada lebar 375 px');
    await page.getByRole('button', { name: 'Buka menu' }).click();
    await expect(page.locator('aside nav').getByRole('link', { name: 'Pengguna' })).toBeInViewport();
    await evidence.snap('Sidebar terbuka di ponsel', { fullPage: false });
  });
});
