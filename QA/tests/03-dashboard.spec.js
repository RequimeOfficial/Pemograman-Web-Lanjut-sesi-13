// M03 Dashboard: ringkasan per role dibandingkan dengan isi database (integration).
const { test, expect } = require('../lib/fixtures');
const { credentials } = require('../lib/env');
const { login, dbAvailable, dbScalar, statValue } = require('./helpers');

const BULAN = { jan: 0, feb: 1, mar: 2, apr: 3, mei: 4, jun: 5, jul: 6, agu: 7, agt: 7, sep: 8, okt: 9, nov: 10, des: 11, may: 4, aug: 7, oct: 9, dec: 11 };
const parseTanggal = (s) => {
  const [d, m, y] = s.trim().split(/\s+/);
  return new Date(Number(y), BULAN[m.toLowerCase().slice(0, 3)], Number(d)).getTime();
};

async function tanggalMasukKolom(page) {
  const headers = await page.locator('table thead th').allInnerTexts();
  const idx = headers.findIndex((h) => /tanggal masuk/i.test(h));
  return page.locator(`table tbody tr td:nth-child(${idx + 1})`).allInnerTexts();
}

test.describe('M03 Dashboard', () => {
  test.beforeEach(() => {
    test.skip(!dbAvailable(), 'BLOCKED: MYSQL_CLI belum diisi di .env, angka UI tidak dapat dibandingkan dengan database');
  });

  test('TC019 Ringkasan dashboard admin sesuai jumlah data di database', async ({ page, tc, evidence }) => {
    tc({
      module: 'M03', feature: 'F05', featureName: 'DASHBOARD_ADMIN', requirement: 'REQ-06',
      role: 'ADMIN', type: 'Integration', priority: 'High',
      expected: 'Kartu Total Pengguna, Pelanggan, Layanan, Transaksi, dan jumlah per peran = COUNT(*) di database',
    });
    await login(page, 'admin');
    await evidence.snap('Dashboard admin');
    const db = (sql) => Number(dbScalar(sql));
    expect(await statValue(page, 'Total Pengguna')).toBe(db('SELECT COUNT(*) FROM users'));
    expect(await statValue(page, 'Layanan')).toBe(db('SELECT COUNT(*) FROM layanan'));
    expect(await statValue(page, 'Transaksi')).toBe(db('SELECT COUNT(*) FROM transaksi'));
    expect(await statValue(page, 'Administrator')).toBe(db("SELECT COUNT(*) FROM users WHERE role='admin'"));
    expect(await statValue(page, 'Kasir')).toBe(db("SELECT COUNT(*) FROM users WHERE role='kasir'"));
    const pelangganCards = page.locator('p.uppercase', { hasText: /^Pelanggan$/i });
    await expect(pelangganCards).toHaveCount(2);
    expect(await statValue(page, 'Pelanggan')).toBe(db('SELECT COUNT(*) FROM pelanggan'));
  });

  test('TC020 Ringkasan dashboard kasir sesuai jumlah data di database', async ({ page, tc, evidence }) => {
    tc({
      module: 'M03', feature: 'F06', featureName: 'DASHBOARD_KASIR', requirement: 'REQ-06',
      role: 'KASIR', type: 'Integration', priority: 'High',
      expected: 'Kartu Pelanggan, Layanan, Transaksi, Notifikasi = COUNT(*) di database',
    });
    await login(page, 'kasir');
    await evidence.snap('Dashboard kasir');
    const db = (sql) => Number(dbScalar(sql));
    expect(await statValue(page, 'Pelanggan')).toBe(db('SELECT COUNT(*) FROM pelanggan'));
    expect(await statValue(page, 'Layanan')).toBe(db('SELECT COUNT(*) FROM layanan'));
    expect(await statValue(page, 'Transaksi')).toBe(db('SELECT COUNT(*) FROM transaksi'));
    expect(await statValue(page, 'Notifikasi')).toBe(db('SELECT COUNT(*) FROM notifikasi'));
  });

  test('TC021 Dashboard pelanggan hanya menampilkan transaksi milik pelanggan tersebut', async ({ page, tc, evidence }) => {
    tc({
      module: 'M03', feature: 'F07', featureName: 'DASHBOARD_PELANGGAN', requirement: 'REQ-06',
      role: 'PELANGGAN', type: 'Integration', priority: 'Critical',
      expected: '"Transaksi Saya" = jumlah transaksi pelanggan login; setiap ID di Riwayat Laundry milik pelanggan tersebut',
    });
    const { user } = credentials('pelanggan');
    const own = `FROM transaksi t JOIN pelanggan p ON p.id = t.pelanggan_id JOIN users u ON u.id = p.user_id WHERE u.email = '${user.replace(/'/g, "''")}'`;
    await login(page, 'pelanggan');
    await evidence.snap('Dashboard pelanggan');
    expect(await statValue(page, 'Transaksi Saya')).toBe(Number(dbScalar(`SELECT COUNT(*) ${own}`)));
    const ids = (await page.locator('table tbody tr td:first-child').allInnerTexts()).map((t) => t.replace('#', '').trim()).filter((t) => /^\d+$/.test(t));
    expect(ids.length).toBeGreaterThan(0);
    const milik = dbScalar(`SELECT GROUP_CONCAT(t.id) ${own}`).split(',');
    for (const id of ids) expect(milik, `Transaksi #${id} harus milik pelanggan login`).toContain(id);
  });

  test('TC022 Tabel "Transaksi Terbaru" menampilkan transaksi dengan tanggal masuk terbaru', async ({ page, tc, evidence }) => {
    tc({
      module: 'M03', feature: 'F06', featureName: 'DASHBOARD_KASIR', requirement: 'REQ-06',
      role: 'KASIR', type: 'Functional', priority: 'Medium',
      expected: 'Baris pertama = transaksi dengan tanggal masuk paling baru di database; urutan tanggal menurun',
    });
    await login(page, 'kasir');
    await evidence.snap('Tabel Transaksi Terbaru di dashboard kasir');
    const tanggal = (await tanggalMasukKolom(page)).map(parseTanggal);
    expect(tanggal.length).toBe(5);
    const terbaruDb = new Date(dbScalar('SELECT MAX(tanggal_masuk) FROM transaksi') + 'T00:00:00').getTime();
    expect(tanggal[0], 'Baris pertama harus tanggal masuk terbaru').toBe(terbaruDb);
    for (let i = 1; i < tanggal.length; i++) {
      expect(tanggal[i], `Baris ${i + 1} tidak boleh lebih baru dari baris ${i}`).toBeLessThanOrEqual(tanggal[i - 1]);
    }
  });
});
