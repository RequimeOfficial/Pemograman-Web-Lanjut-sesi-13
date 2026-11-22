// M02 Hak Akses: pembatasan route per role (RoleMiddleware) dan menu sesuai role.
const { test, expect } = require('../lib/fixtures');
const { login, DASHBOARD } = require('./helpers');

async function expectForbidden(page, url) {
  const resp = await page.goto(url);
  expect(resp.status(), `HTTP status ${url}`).toBe(403);
  await expect(page.getByText('Akses Ditolak')).toBeVisible();
}

test.describe('M02 Hak Akses', () => {
  test('TC011 Kasir tidak dapat membuka Manajemen Pengguna (403)', async ({ page, tc, evidence }) => {
    tc({
      module: 'M02', feature: 'F04', featureName: 'AKSES_ROLE', requirement: 'REQ-05',
      role: 'KASIR', type: 'Access', priority: 'Critical',
      expected: '/users, /users/create, /users/1/edit → HTTP 403 "Akses Ditolak"',
    });
    await login(page, 'kasir');
    for (const url of ['/users/create', '/users/1/edit', '/users']) await expectForbidden(page, url);
    await evidence.snap('Kasir membuka /users');
  });

  test('TC012 Pelanggan tidak dapat membuka dashboard admin & kasir (403)', async ({ page, tc, evidence }) => {
    tc({
      module: 'M02', feature: 'F04', featureName: 'AKSES_ROLE', requirement: 'REQ-05',
      role: 'PELANGGAN', type: 'Access', priority: 'Critical',
      expected: '/dashboard/admin, /dashboard/kasir, /users → HTTP 403',
    });
    await login(page, 'pelanggan');
    await expectForbidden(page, '/dashboard/kasir');
    await expectForbidden(page, '/users');
    await expectForbidden(page, '/dashboard/admin');
    await evidence.snap('Pelanggan membuka /dashboard/admin');
  });

  test('TC013 Admin tidak dapat membuka dashboard role lain (403)', async ({ page, tc, evidence }) => {
    tc({
      module: 'M02', feature: 'F04', featureName: 'AKSES_ROLE', requirement: 'REQ-05',
      role: 'ADMIN', type: 'Access', priority: 'Medium',
      expected: '/dashboard/kasir dan /dashboard/pelanggan → HTTP 403',
    });
    await login(page, 'admin');
    await expectForbidden(page, '/dashboard/pelanggan');
    await expectForbidden(page, '/dashboard/kasir');
    await evidence.snap('Admin membuka /dashboard/kasir');
  });

  test('TC014 Halaman 403 menyediakan tombol kembali ke dashboard sesuai role', async ({ page, tc, evidence }) => {
    tc({
      module: 'M02', feature: 'F04', featureName: 'AKSES_ROLE', requirement: 'REQ-05',
      role: 'KASIR', type: 'Workflow', priority: 'Low',
      expected: 'Klik "Kembali ke Dashboard" pada halaman 403 → /dashboard/kasir',
    });
    await login(page, 'kasir');
    await page.goto('/users');
    await page.getByRole('link', { name: 'Kembali ke Dashboard' }).click();
    await expect(page).toHaveURL(/\/dashboard\/kasir$/);
    await evidence.snap('Kembali ke dashboard kasir');
  });

  for (const [id, role, seesUsers] of [['TC015', 'admin', true], ['TC016', 'kasir', false], ['TC017', 'pelanggan', false]]) {
    test(`${id} Menu sidebar ${role} ${seesUsers ? 'memuat' : 'tidak memuat'} menu Pengguna`, async ({ page, tc, evidence }) => {
      tc({
        module: 'M02', feature: 'F04', featureName: 'MENU_ROLE', requirement: 'REQ-05',
        role: role.toUpperCase(), type: 'Access', priority: 'Medium',
        expected: seesUsers ? 'Sidebar: Dashboard + Pengaturan › Pengguna' : 'Sidebar hanya Dashboard (tanpa Pengguna)',
      });
      await login(page, role);
      const nav = page.locator('aside nav');
      await expect(nav.getByRole('link', { name: 'Dashboard' })).toBeVisible();
      if (seesUsers) {
        // Menu Pengguna berada di dalam grup "Pengaturan" yang tertutup di halaman dashboard.
        await nav.getByRole('button', { name: 'Pengaturan' }).click();
        await expect(nav.getByRole('link', { name: 'Pengguna' })).toBeVisible();
      }
      else await expect(nav.getByRole('link', { name: 'Pengguna' })).toHaveCount(0);
      await evidence.snap(`Sidebar ${role}`);
    });
  }

  test('TC018 Pengguna yang sudah login dan membuka /login diarahkan ke dashboard', async ({ page, tc, evidence }) => {
    tc({
      module: 'M02', feature: 'F01', featureName: 'LOGIN', requirement: 'REQ-01',
      role: 'PELANGGAN', type: 'Workflow', priority: 'Low',
      expected: 'Membuka /login atau / saat sudah login → /dashboard/pelanggan',
    });
    await login(page, 'pelanggan');
    await page.goto('/login');
    await expect(page).toHaveURL(new RegExp(`${DASHBOARD.pelanggan}$`));
    await page.goto('/');
    await expect(page).toHaveURL(new RegExp(`${DASHBOARD.pelanggan}$`));
    await evidence.snap('Tetap di dashboard pelanggan');
  });
});
