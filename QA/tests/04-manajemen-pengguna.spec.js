// M04 Manajemen Pengguna (khusus Administrator): daftar, cari, filter, tambah, ubah, hapus + aturan bisnis.
const { test, expect } = require('../lib/fixtures');
const { login, dbAvailable, dbScalar, uniq } = require('./helpers');

const msg = (page, text) => expect(page.getByText(text).first()).toBeVisible();

async function isiForm(page, data) {
  for (const [field, value] of Object.entries(data)) {
    if (field === 'role') await page.locator('#role').selectOption(value);
    else await page.locator(`#${field}`).fill(value);
  }
}

test.describe('M04 Manajemen Pengguna', () => {
  test.beforeEach(async ({ page }) => { await login(page, 'admin'); });

  // Data uji dibuat oleh TC033 lalu dipakai TC034–TC035 (dibersihkan di TC035).
  const baru = { name: `QA Kasir ${uniq('')}`, username: `q${Math.random().toString(36).slice(2, 4)}`, email: `${uniq('qa.kasir.')}@silaundry.test` };

  test('TC023 Daftar pengguna menampilkan 10 baris per halaman dengan paginasi', async ({ page, tc, evidence }) => {
    tc({
      module: 'M04', feature: 'F08', featureName: 'DAFTAR_PENGGUNA', requirement: 'REQ-07',
      role: 'ADMIN', type: 'Positive', priority: 'High',
      expected: 'Tabel kolom Nama, Username, Email, Peran, Aksi; maks. 10 baris; navigasi halaman tersedia bila > 10 data',
    });
    await page.goto('/users');
    await expect(page.locator('table thead')).toContainText(/Nama.*Username.*Email.*Peran.*Aksi/is);
    await expect(page.locator('table tbody tr')).toHaveCount(10);
    await expect(page.locator('nav[role="navigation"]').first()).toBeVisible();
    await evidence.snap('Halaman 1 daftar pengguna');
    await page.goto('/users?page=2');
    await expect(page.locator('table tbody tr').first()).toBeVisible();
    await evidence.snap('Halaman 2 daftar pengguna');
  });

  test('TC024 Pencarian berdasarkan kata kunci menampilkan pengguna yang cocok', async ({ page, tc, evidence }) => {
    tc({
      module: 'M04', feature: 'F08', featureName: 'DAFTAR_PENGGUNA', requirement: 'REQ-07',
      role: 'ADMIN', type: 'Positive', priority: 'Medium',
      expected: 'Cari "kasir" → setiap baris memuat "kasir" pada nama/username/email; tombol Reset muncul',
    });
    await page.goto('/users');
    await page.getByPlaceholder(/Cari nama/).fill('kasir');
    await page.getByRole('button', { name: 'Cari' }).click();
    await expect(page).toHaveURL(/q=kasir/);
    const rows = await page.locator('table tbody tr').allInnerTexts();
    expect(rows.length).toBeGreaterThan(0);
    for (const r of rows) expect(r.toLowerCase()).toContain('kasir');
    await expect(page.getByRole('link', { name: 'Reset' })).toBeVisible();
    await evidence.snap('Hasil pencarian "kasir"');
  });

  test('TC025 Filter peran Kasir hanya menampilkan pengguna ber-peran Kasir', async ({ page, tc, evidence }) => {
    tc({
      module: 'M04', feature: 'F08', featureName: 'DAFTAR_PENGGUNA', requirement: 'REQ-07',
      role: 'ADMIN', type: 'Positive', priority: 'Medium',
      expected: 'Pilih "Kasir" pada filter → semua badge peran = Kasir; jumlah baris = jumlah kasir di database',
    });
    await page.goto('/users');
    await page.locator('select[name="role"]').selectOption('kasir');
    await expect(page).toHaveURL(/role=kasir/);
    const badges = await page.locator('table tbody tr td:nth-child(4)').allInnerTexts();
    for (const b of badges) expect(b.trim()).toBe('Kasir');
    if (dbAvailable()) expect(badges.length).toBe(Number(dbScalar("SELECT COUNT(*) FROM users WHERE role='kasir'")));
    await evidence.snap('Filter peran Kasir');
  });

  test('TC026 Pencarian tanpa hasil menampilkan pesan "Pengguna tidak ditemukan."', async ({ page, tc, evidence }) => {
    tc({
      module: 'M04', feature: 'F08', featureName: 'DAFTAR_PENGGUNA', requirement: 'REQ-07',
      role: 'ADMIN', type: 'Negative', priority: 'Low',
      expected: 'Pesan "Pengguna tidak ditemukan." pada tabel kosong',
    });
    await page.goto('/users?q=zzz-tidak-ada-zzz');
    await expect(page.getByText('Pengguna tidak ditemukan.')).toBeVisible();
    await evidence.snap('Pencarian tanpa hasil');
  });

  test('TC027 Simpan form tambah pengguna kosong menampilkan pesan wajib diisi', async ({ page, tc, evidence }) => {
    tc({
      module: 'M04', feature: 'F09', featureName: 'TAMBAH_PENGGUNA', requirement: 'REQ-08',
      role: 'ADMIN', type: 'Negative', priority: 'High',
      expected: 'Pesan wajib diisi untuk Nama, Nama Pengguna, Email, Kata Sandi, Peran; data tidak tersimpan',
    });
    await page.goto('/users/create');
    await evidence.snap('Form tambah pengguna');
    await page.getByRole('button', { name: 'Simpan' }).click();
    await expect(page).toHaveURL(/\/users\/create$/);
    for (const t of ['Nama wajib diisi.', 'Nama Pengguna wajib diisi.', 'Email wajib diisi.', 'Kata Sandi wajib diisi.', 'Peran wajib diisi.']) await msg(page, t);
    await evidence.snap('Pesan validasi field wajib');
  });

  test('TC028 Nama pengguna 2 karakter dan mengandung spasi ditolak', async ({ page, tc, evidence }) => {
    tc({
      module: 'M04', feature: 'F09', featureName: 'TAMBAH_PENGGUNA', requirement: 'REQ-08',
      role: 'ADMIN', type: 'Boundary', priority: 'Medium',
      expected: '"ab" → "Nama Pengguna minimal 3 karakter."; "qa user" → pesan karakter tidak diizinkan',
    });
    const base = { name: 'QA Boundary', email: `${uniq('qa.b.')}@silaundry.test`, role: 'kasir', password: 'rahasia123', password_confirmation: 'rahasia123' };
    await page.goto('/users/create');
    await isiForm(page, { ...base, username: 'ab' });
    await page.getByRole('button', { name: 'Simpan' }).click();
    await msg(page, 'Nama Pengguna minimal 3 karakter.');
    await evidence.snap('Username 2 karakter (min - 1)');
    await isiForm(page, { password: 'rahasia123', password_confirmation: 'rahasia123', username: 'qa user' });
    await page.getByRole('button', { name: 'Simpan' }).click();
    await msg(page, 'Nama pengguna hanya boleh berisi huruf, angka, titik, tanda hubung, dan garis bawah.');
    await evidence.snap('Username mengandung spasi');
  });

  test('TC029 Nama pengguna 51 karakter ditolak (batas maksimal 50)', async ({ page, tc, evidence }) => {
    tc({
      module: 'M04', feature: 'F09', featureName: 'TAMBAH_PENGGUNA', requirement: 'REQ-08',
      role: 'ADMIN', type: 'Boundary', priority: 'Low',
      expected: 'Username 51 karakter → "Nama Pengguna maksimal 50 karakter."',
    });
    await page.goto('/users/create');
    await isiForm(page, { name: 'QA Boundary', username: 'u'.repeat(51), email: `${uniq('qa.b.')}@silaundry.test`, role: 'kasir', password: 'rahasia123', password_confirmation: 'rahasia123' });
    await page.getByRole('button', { name: 'Simpan' }).click();
    await msg(page, 'Nama Pengguna maksimal 50 karakter.');
    await evidence.snap('Username 51 karakter (max + 1)');
  });

  test('TC030 Kata sandi 7 karakter dan konfirmasi tidak cocok ditolak', async ({ page, tc, evidence }) => {
    tc({
      module: 'M04', feature: 'F09', featureName: 'TAMBAH_PENGGUNA', requirement: 'REQ-08',
      role: 'ADMIN', type: 'Boundary', priority: 'High',
      expected: '7 karakter → "Kata sandi minimal 8 karakter."; konfirmasi beda → "Konfirmasi Kata Sandi tidak cocok."',
    });
    const base = { name: 'QA Boundary', username: uniq('qa.'), email: `${uniq('qa.b.')}@silaundry.test`, role: 'kasir' };
    await page.goto('/users/create');
    await isiForm(page, { ...base, password: 'abc1234', password_confirmation: 'abc1234' });
    await page.getByRole('button', { name: 'Simpan' }).click();
    await msg(page, 'Kata sandi minimal 8 karakter.');
    await evidence.snap('Kata sandi 7 karakter (min - 1)');
    await isiForm(page, { password: 'rahasia123', password_confirmation: 'rahasia124' });
    await page.getByRole('button', { name: 'Simpan' }).click();
    await msg(page, 'Konfirmasi Kata Sandi tidak cocok.');
    await evidence.snap('Konfirmasi kata sandi berbeda');
  });

  test('TC031 Email yang sudah terdaftar ditolak', async ({ page, tc, evidence }) => {
    tc({
      module: 'M04', feature: 'F09', featureName: 'TAMBAH_PENGGUNA', requirement: 'REQ-08',
      role: 'ADMIN', type: 'Negative', priority: 'High',
      expected: 'Email kasir@silaundry.test → "Email sudah digunakan oleh pengguna lain."',
    });
    await page.goto('/users/create');
    await isiForm(page, { name: 'QA Duplikat', username: uniq('qa.'), email: 'kasir@silaundry.test', role: 'kasir', password: 'rahasia123', password_confirmation: 'rahasia123' });
    await page.getByRole('button', { name: 'Simpan' }).click();
    await msg(page, 'Email sudah digunakan oleh pengguna lain.');
    await evidence.snap('Email duplikat ditolak');
  });

  test('TC032 Peran Pelanggan memunculkan & mewajibkan Nomor HP dan Alamat', async ({ page, tc, evidence }) => {
    tc({
      module: 'M04', feature: 'F09', featureName: 'TAMBAH_PENGGUNA', requirement: 'REQ-09',
      role: 'ADMIN', type: 'Negative', priority: 'High',
      expected: 'Field Nomor HP & Alamat tersembunyi untuk Kasir, tampil untuk Pelanggan; kosong → "... wajib diisi untuk peran pelanggan."',
    });
    await page.goto('/users/create');
    await page.locator('#role').selectOption('kasir');
    await expect(page.locator('#no_hp')).toBeHidden();
    await page.locator('#role').selectOption('pelanggan');
    await expect(page.locator('#no_hp')).toBeVisible();
    await expect(page.locator('#alamat')).toBeVisible();
    await evidence.snap('Field kontak muncul untuk peran Pelanggan');
    await isiForm(page, { name: 'QA Pelanggan', username: uniq('qa.'), email: `${uniq('qa.p.')}@silaundry.test`, password: 'rahasia123', password_confirmation: 'rahasia123' });
    await page.getByRole('button', { name: 'Simpan' }).click();
    await msg(page, 'Nomor HP wajib diisi untuk peran pelanggan.');
    await msg(page, 'Alamat wajib diisi untuk peran pelanggan.');
    await evidence.snap('Nomor HP & Alamat wajib untuk pelanggan');
  });

  test('TC033 Tambah kasir valid (username tepat 3 karakter, kata sandi tepat 8 karakter)', async ({ page, tc, evidence }) => {
    tc({
      module: 'M04', feature: 'F09', featureName: 'TAMBAH_PENGGUNA', requirement: 'REQ-08',
      role: 'ADMIN', type: 'Boundary', priority: 'Critical',
      expected: 'Redirect ke /users dengan pesan "Pengguna ... berhasil ditambahkan." dan data muncul di daftar',
    });
    await page.goto('/users/create');
    await isiForm(page, { ...baru, role: 'kasir', password: 'abcd1234', password_confirmation: 'abcd1234' });
    await evidence.snap('Form tambah kasir terisi');
    await page.getByRole('button', { name: 'Simpan' }).click();
    await expect(page).toHaveURL(/\/users$/);
    await msg(page, `Pengguna ${baru.name} berhasil ditambahkan.`);
    await expect(page.locator('table tbody')).toContainText(baru.email);
    await evidence.snap('Kasir baru tampil di daftar');
  });

  test('TC034 Ubah nama pengguna tersimpan dan tampil di daftar', async ({ page, tc, evidence }) => {
    tc({
      module: 'M04', feature: 'F10', featureName: 'UBAH_PENGGUNA', requirement: 'REQ-10',
      role: 'ADMIN', type: 'Positive', priority: 'High',
      expected: 'Pesan "Data pengguna ... berhasil diperbarui."; nama baru tampil; kata sandi kosong tidak wajib saat edit',
    });
    await page.goto(`/users?q=${encodeURIComponent(baru.email)}`);
    await page.getByRole('link', { name: 'Ubah' }).first().click();
    await expect(page).toHaveURL(/\/users\/\d+\/edit$/);
    baru.name = `${baru.name} Edit`;
    await page.locator('#name').fill(baru.name);
    await evidence.snap('Form ubah pengguna');
    await page.getByRole('button', { name: 'Simpan' }).click();
    await msg(page, `Data pengguna ${baru.name} berhasil diperbarui.`);
    await expect(page.locator('table tbody')).toContainText(baru.name);
    await evidence.snap('Nama pengguna diperbarui');
  });

  test('TC035 Hapus pengguna lewat dialog konfirmasi (Batal lalu Hapus)', async ({ page, tc, evidence }) => {
    tc({
      module: 'M04', feature: 'F11', featureName: 'HAPUS_PENGGUNA', requirement: 'REQ-11',
      role: 'ADMIN', type: 'Workflow', priority: 'High',
      expected: 'Dialog "Hapus pengguna?" muncul; Batal tidak menghapus; Hapus → pesan berhasil dihapus dan data hilang',
    });
    await page.goto(`/users?q=${encodeURIComponent(baru.email)}`);
    await page.locator(`[data-delete-user="${baru.email}"]`).click();
    await expect(page.getByText('Hapus pengguna?')).toBeVisible();
    await evidence.snap('Dialog konfirmasi hapus');
    await page.getByRole('button', { name: 'Batal' }).click();
    await expect(page.getByText('Hapus pengguna?')).toBeHidden();
    await expect(page.locator('table tbody')).toContainText(baru.email);
    await page.locator(`[data-delete-user="${baru.email}"]`).click();
    await page.locator('#confirmDeleteSubmit').click();
    await msg(page, `Pengguna ${baru.name} berhasil dihapus.`);
    await page.goto(`/users?q=${encodeURIComponent(baru.email)}`);
    await expect(page.getByText('Pengguna tidak ditemukan.')).toBeVisible();
    await evidence.snap('Pengguna sudah terhapus');
  });

  test('TC036 Admin tidak dapat mengubah peran akunnya sendiri (UI dan server)', async ({ page, tc, evidence }) => {
    tc({
      module: 'M04', feature: 'F10', featureName: 'UBAH_PENGGUNA', requirement: 'REQ-10',
      role: 'ADMIN', type: 'Security', priority: 'High',
      expected: 'Select Peran nonaktif; manipulasi nilai peran tersembunyi → "Kamu tidak dapat mengubah peran akun yang sedang digunakan."',
    });
    await page.goto('/users?role=admin');
    await page.locator('tr', { hasText: 'Anda' }).getByRole('link', { name: 'Ubah' }).click();
    await expect(page.locator('#role')).toBeDisabled();
    await evidence.snap('Select peran akun sendiri nonaktif');
    // Simulasi request yang dimanipulasi (bypass UI) untuk memastikan validasi ada di server.
    await page.locator('input[type="hidden"][name="role"]').evaluate((el) => { el.value = 'kasir'; });
    await page.getByRole('button', { name: 'Simpan' }).click();
    await msg(page, 'Kamu tidak dapat mengubah peran akun yang sedang digunakan.');
    await evidence.snap('Server menolak perubahan peran sendiri');
  });

  test('TC037 Admin tidak dapat menghapus akunnya sendiri (UI dan server)', async ({ page, tc, evidence }) => {
    tc({
      module: 'M04', feature: 'F11', featureName: 'HAPUS_PENGGUNA', requirement: 'REQ-11',
      role: 'ADMIN', type: 'Security', priority: 'High',
      expected: 'Tidak ada tombol Hapus di baris "Anda"; request DELETE paksa → "Kamu tidak dapat menghapus akun yang sedang digunakan."',
    });
    await page.goto('/users?role=admin');
    const self = page.locator('tr', { hasText: 'Anda' });
    await expect(self.getByRole('button', { name: 'Hapus' })).toHaveCount(0);
    await evidence.snap('Baris akun sendiri tanpa tombol Hapus');
    const editUrl = await self.getByRole('link', { name: 'Ubah' }).getAttribute('href');
    const target = editUrl.replace(/\/edit$/, '');
    await page.evaluate((action) => {
      const token = document.querySelector('input[name="_token"]').value;
      const f = Object.assign(document.createElement('form'), { method: 'POST', action });
      f.innerHTML = `<input name="_token" value="${token}"><input name="_method" value="DELETE">`;
      document.body.appendChild(f);
      f.submit();
    }, target);
    await page.waitForURL('**/users');
    await msg(page, 'Kamu tidak dapat menghapus akun yang sedang digunakan.');
    await evidence.snap('Server menolak penghapusan akun sendiri');
  });

  test('TC038 Kasir yang sudah menangani transaksi tidak dapat dihapus', async ({ page, tc, evidence }) => {
    tc({
      module: 'M04', feature: 'F11', featureName: 'HAPUS_PENGGUNA', requirement: 'REQ-11',
      role: 'ADMIN', type: 'Negative', priority: 'High',
      expected: 'Hapus kasir@silaundry.test → pesan "... masih tercatat sebagai kasir pada transaksi ..."; data tetap ada',
    });
    await page.goto('/users?q=kasir%40silaundry.test');
    await page.locator('[data-delete-user="kasir@silaundry.test"]').click();
    await page.locator('#confirmDeleteSubmit').click();
    await msg(page, /masih tercatat sebagai kasir pada transaksi, sehingga tidak dapat dihapus\./);
    await evidence.snap('Penghapusan kasir bertransaksi ditolak');
    await page.goto('/users?q=kasir%40silaundry.test');
    await expect(page.locator('table tbody')).toContainText('kasir@silaundry.test');
  });

  test('TC039 Membuka form ubah untuk pengguna yang tidak ada menampilkan 404', async ({ page, tc, evidence }) => {
    tc({
      module: 'M04', feature: 'F10', featureName: 'UBAH_PENGGUNA', requirement: 'REQ-10',
      role: 'ADMIN', type: 'Negative', priority: 'Low',
      expected: '/users/999999/edit → HTTP 404 dengan halaman galat aplikasi',
    });
    const resp = await page.goto('/users/999999/edit');
    expect(resp.status()).toBe(404);
    await expect(page.getByText('404')).toBeVisible();
    await evidence.snap('Halaman 404');
  });

  test('TC040 Teks navigasi paginasi menggunakan bahasa Indonesia seperti seluruh antarmuka', async ({ page, tc, evidence }) => {
    tc({
      module: 'M04', feature: 'F08', featureName: 'DAFTAR_PENGGUNA', requirement: 'REQ-12',
      role: 'ADMIN', type: 'UI', priority: 'Low',
      expected: 'Ringkasan paginasi berbahasa Indonesia (mis. "Menampilkan 1 sampai 10 dari 25 data"), tidak ada teks "Showing ... results"',
    });
    await page.goto('/users');
    const nav = page.locator('nav[role="navigation"]').first();
    await nav.scrollIntoViewIfNeeded();
    await evidence.snap('Area paginasi daftar pengguna');
    await expect(nav).not.toContainText(/Showing|results|Previous|Next/);
  });
});
