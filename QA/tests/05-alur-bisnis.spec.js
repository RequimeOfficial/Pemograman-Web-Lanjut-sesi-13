// M05 Alur Bisnis (end-to-end lintas role): admin mendaftarkan pelanggan → pelanggan login → admin menghapus.
const { test, expect } = require('../lib/fixtures');
const { login, loginWith, logout, dbAvailable, dbScalar, uniq } = require('./helpers');

test('TC041 Alur pendaftaran akun pelanggan baru sampai akun dihapus', async ({ page, tc, evidence }) => {
  tc({
    module: 'M05', feature: 'F09', featureName: 'ALUR_PELANGGAN', requirement: 'REQ-08, REQ-09, REQ-01, REQ-11',
    role: 'ADMIN', type: 'Workflow', priority: 'Critical',
    expected: 'Pelanggan baru tersimpan beserta data pelanggan, dapat login ke /dashboard/pelanggan dengan 0 transaksi, lalu setelah dihapus tidak dapat login',
  });
  const p = {
    name: `QA Pelanggan ${uniq('')}`, username: uniq('qa.pel.'), email: `${uniq('qa.pel.')}@silaundry.test`,
    password: `Qa-${uniq('')}`, no_hp: '081234567890', alamat: 'Jl. Uji Coba No. 13, Jombang',
  };

  await test.step('Admin menambahkan akun pelanggan', async () => {
    await login(page, 'admin');
    await page.goto('/users/create');
    await page.locator('#name').fill(p.name);
    await page.locator('#username').fill(p.username);
    await page.locator('#email').fill(p.email);
    await page.locator('#role').selectOption('pelanggan');
    await page.locator('#password').fill(p.password);
    await page.locator('#password_confirmation').fill(p.password);
    await page.locator('#no_hp').fill(p.no_hp);
    await page.locator('#alamat').fill(p.alamat);
    await evidence.snap('1. Admin mengisi data pelanggan baru');
    await page.getByRole('button', { name: 'Simpan' }).click();
    await expect(page.getByText(`Pengguna ${p.name} berhasil ditambahkan.`)).toBeVisible();
    await evidence.snap('2. Pelanggan baru tersimpan');
    if (dbAvailable()) {
      const n = dbScalar(`SELECT COUNT(*) FROM pelanggan pl JOIN users u ON u.id = pl.user_id WHERE u.email='${p.email}' AND pl.no_hp='${p.no_hp}'`);
      expect(Number(n), 'Data pelanggan (No HP & alamat) otomatis dibuat').toBe(1);
    }
    await logout(page);
  });

  await test.step('Pelanggan baru login dan melihat dashboard kosong', async () => {
    await loginWith(page, p.email, p.password);
    await expect(page).toHaveURL(/\/dashboard\/pelanggan$/);
    await expect(page.getByText('Belum ada transaksi.')).toBeVisible();
    await expect(page.getByText('Akun belum terhubung dengan data pelanggan.')).toHaveCount(0);
    await evidence.snap('3. Dashboard pelanggan baru');
    await logout(page);
  });

  await test.step('Admin menghapus akun pelanggan tanpa transaksi', async () => {
    await login(page, 'admin');
    await page.goto(`/users?q=${encodeURIComponent(p.email)}`);
    await page.locator(`[data-delete-user="${p.email}"]`).click();
    await page.locator('#confirmDeleteSubmit').click();
    await expect(page.getByText(`Pengguna ${p.name} berhasil dihapus.`)).toBeVisible();
    await evidence.snap('4. Akun pelanggan dihapus');
    await logout(page);
  });

  await test.step('Akun yang sudah dihapus tidak dapat login', async () => {
    await loginWith(page, p.email, p.password);
    await expect(page.getByText('Email atau kata sandi tidak sesuai.')).toBeVisible();
    await evidence.snap('5. Login akun terhapus ditolak');
  });
});
