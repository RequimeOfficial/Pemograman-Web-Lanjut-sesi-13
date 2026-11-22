// M01 Autentikasi: login multi role, validasi, pembatasan percobaan login, logout.
const { test, expect } = require('../lib/fixtures');
const { credentials } = require('../lib/env');
const { login, loginWith, logout, uniq } = require('./helpers');

test.describe('M01 Autentikasi', () => {
  for (const [id, role, label] of [
    ['TC001', 'admin', 'Administrator'],
    ['TC002', 'kasir', 'Kasir'],
    ['TC003', 'pelanggan', 'Pelanggan'],
  ]) {
    test(`${id} Login ${label} dengan kredensial valid diarahkan ke dashboard ${role}`, async ({ page, tc, evidence }) => {
      tc({
        module: 'M01', feature: 'F01', featureName: 'LOGIN', requirement: 'REQ-01',
        role: role.toUpperCase(), type: 'Positive', priority: 'Critical',
        expected: `Masuk ke /dashboard/${role}; sidebar menampilkan peran "${label}"`,
      });
      await page.goto('/login');
      await evidence.snap('Halaman login');
      await login(page, role);
      await expect(page).toHaveURL(new RegExp(`/dashboard/${role}$`));
      await expect(page.locator('aside')).toContainText(label);
      await evidence.snap(`Dashboard ${label} setelah login`);
    });
  }

  test('TC004 Login dengan kata sandi salah ditolak', async ({ page, tc, evidence }) => {
    tc({
      module: 'M01', feature: 'F01', featureName: 'LOGIN', requirement: 'REQ-02',
      role: 'PUBLIC', type: 'Negative', priority: 'High',
      expected: 'Tetap di /login dengan pesan "Email atau kata sandi tidak sesuai."',
    });
    const { user } = credentials('kasir');
    await loginWith(page, user, 'salah-sandi-123');
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByText('Email atau kata sandi tidak sesuai.')).toBeVisible();
    await expect(page.locator('#email')).toHaveValue(user);
    await evidence.snap('Pesan kredensial salah, email tetap terisi');
  });

  test('TC005 Login dengan field kosong menampilkan pesan wajib diisi', async ({ page, tc, evidence }) => {
    tc({
      module: 'M01', feature: 'F01', featureName: 'LOGIN', requirement: 'REQ-02',
      role: 'PUBLIC', type: 'Negative', priority: 'Medium',
      expected: 'Pesan "Email wajib diisi." dan "Kata sandi wajib diisi."',
    });
    await page.goto('/login');
    await page.getByRole('button', { name: 'Masuk' }).click();
    await expect(page.getByText('Email wajib diisi.')).toBeVisible();
    await expect(page.getByText('Kata sandi wajib diisi.')).toBeVisible();
    await evidence.snap('Validasi field kosong');
  });

  test('TC006 Login dengan format email tidak valid tidak diproses', async ({ page, tc, evidence }) => {
    tc({
      module: 'M01', feature: 'F01', featureName: 'LOGIN', requirement: 'REQ-02',
      role: 'PUBLIC', type: 'Negative', priority: 'Low',
      expected: 'Form tidak terkirim (validasi email browser) atau tampil "Format email tidak valid."; tetap di /login',
    });
    await page.goto('/login');
    await page.locator('#email').fill('admin-silaundry');
    await page.locator('#password').fill('apa-saja-123');
    await page.getByRole('button', { name: 'Masuk' }).click();
    await expect(page).toHaveURL(/\/login$/);
    const nativeInvalid = await page.locator('#email').evaluate((el) => !el.checkValidity());
    const serverMsg = await page.getByText('Format email tidak valid.').isVisible();
    expect(nativeInvalid || serverMsg, 'Email tanpa "@" harus ditolak').toBeTruthy();
    await evidence.snap('Email tanpa @ ditolak');
  });

  test('TC007 Login dikunci setelah 5 kali gagal (boundary percobaan ke-5 dan ke-6)', async ({ page, tc, evidence }) => {
    tc({
      module: 'M01', feature: 'F02', featureName: 'LOCKOUT', requirement: 'REQ-03',
      role: 'PUBLIC', type: 'Boundary', priority: 'High',
      expected: 'Percobaan 1–5: "Email atau kata sandi tidak sesuai."; percobaan ke-6: "Terlalu banyak percobaan masuk..."',
    });
    // Email uji unik agar akun seed tidak ikut terkunci.
    const email = `${uniq('qa.lock.')}@silaundry.test`;
    for (let i = 1; i <= 5; i++) {
      await loginWith(page, email, `salah-${i}-xyz`);
      await expect(page.getByText('Email atau kata sandi tidak sesuai.')).toBeVisible();
    }
    await evidence.snap('Percobaan ke-5 masih pesan kredensial salah');
    await loginWith(page, email, 'salah-6-xyz');
    await expect(page.getByText(/Terlalu banyak percobaan masuk\. Coba lagi dalam \d+ detik\./)).toBeVisible();
    await evidence.snap('Percobaan ke-6 dikunci sementara');
  });

  test('TC008 Logout mengakhiri sesi dan halaman dashboard tidak dapat diakses lagi', async ({ page, tc, evidence }) => {
    tc({
      module: 'M01', feature: 'F03', featureName: 'LOGOUT', requirement: 'REQ-04',
      role: 'ADMIN', type: 'Positive', priority: 'High',
      expected: 'Setelah Keluar diarahkan ke /login; membuka /dashboard/admin kembali diarahkan ke /login',
    });
    await login(page, 'admin');
    await page.locator('aside').getByRole('button').last().click();
    await evidence.snap('Menu akun berisi tombol Keluar');
    await page.getByRole('button', { name: 'Keluar' }).click();
    await expect(page).toHaveURL(/\/login$/);
    await page.goto('/dashboard/admin');
    await expect(page).toHaveURL(/\/login$/);
    await evidence.snap('Akses dashboard setelah logout dialihkan ke login');
  });

  test('TC009 Tombol Back browser setelah logout tidak menampilkan data halaman terproteksi', async ({ page, tc, evidence }) => {
    tc({
      module: 'M01', feature: 'F03', featureName: 'LOGOUT', requirement: 'REQ-04',
      role: 'ADMIN', type: 'Security', priority: 'Medium',
      expected: 'Setelah logout lalu menekan Back, tabel daftar pengguna tidak tampil (diarahkan ke login)',
    });
    await login(page, 'admin');
    await page.goto('/users');
    await expect(page.locator('table')).toBeVisible();
    await logout(page);
    await page.goBack();
    await page.waitForLoadState('networkidle').catch(() => {});
    await evidence.snap('Kondisi setelah menekan Back');
    await expect(page.getByRole('heading', { name: 'Pengguna' })).toHaveCount(0);
  });

  test('TC010 Tamu yang membuka halaman terproteksi diarahkan ke login', async ({ page, tc, evidence }) => {
    tc({
      module: 'M01', feature: 'F04', featureName: 'GUARD', requirement: 'REQ-04',
      role: 'PUBLIC', type: 'Access', priority: 'Critical',
      expected: '/, /users, /users/create, /dashboard/* tanpa login → /login',
    });
    for (const url of ['/', '/users', '/users/create', '/dashboard/admin', '/dashboard/kasir', '/dashboard/pelanggan']) {
      await page.goto(url);
      await expect(page, `URL ${url}`).toHaveURL(/\/login$/);
    }
    await evidence.snap('Semua URL terproteksi dialihkan ke login');
  });
});
