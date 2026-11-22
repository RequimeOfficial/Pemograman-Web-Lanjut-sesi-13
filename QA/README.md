# QA SiLaundry — Black-Box & White-Box Testing

Hasil Quality Assurance aplikasi **SiLaundry** oleh Kelompok 2 (Ahmad Mofidur Robbiyal Aqla — 2495114006, Fadli Hasan — 2495114035, M Zakki Asshidiqi — 2495114054) untuk Tugas Pemrograman Web Lanjut Sesi 13.
Alur kerja mengikuti materi Sesi 13
(tahap 1 *Application Discovery* → tahap 2 *QA Automation*) dengan Playwright, ditambah white-box testing PHPUnit.

📄 **Laporan lengkap: [`reports/Laporan_QA_SiLaundry.pdf`](reports/Laporan_QA_SiLaundry.pdf)**
(versi DOCX dan katalog test case `Test_Case_Catalogue.xlsx` ada di folder yang sama).

## Hasil

| Pengujian | Alat | Run 1 (awal) | Run 2 (retest & regresi) |
|---|---|---|---|
| Black-box, 44 test case (TC000–TC043) | Playwright + Chromium | 39 PASS / 5 FAIL (88,6 %) | 44 PASS / 0 FAIL (100 %) |
| White-box, basis path + test fitur proyek | PHPUnit 12 | 64 / 66 lulus | 67 / 67 lulus |

Bug terkonfirmasi (detail di [`bugs/bugs.json`](bugs/bugs.json)), semuanya sudah diperbaiki dan diverifikasi ulang:

| Bug | Temuan | Severity | Ditemukan oleh |
|---|---|---|---|
| BUG-001 | Pesan validasi panjang min/maks tampil bahasa Inggris | Medium | TC028, TC029, white-box WB-VAL-1 |
| BUG-002 | Setelah logout, tombol Back menampilkan lagi halaman daftar pengguna | Medium | TC009 |
| BUG-003 | "Transaksi Terbaru" tidak urut dari tanggal masuk terbaru | Medium | TC022, white-box WB-DASH-D1 |
| BUG-004 | Teks paginasi bahasa Inggris ("Showing 1 to 10 of 25 results") | Low | TC040 |

## Modul yang diuji

| Modul | Spec | Isi |
|---|---|---|
| M01 Autentikasi | `tests/01-autentikasi.spec.js` | login 3 role, validasi, kunci 5× gagal, logout, tombol Back, proteksi tamu |
| M02 Hak Akses | `tests/02-hak-akses.spec.js` | 403 per role, menu sesuai role, redirect halaman login |
| M03 Dashboard | `tests/03-dashboard.spec.js` | angka ringkasan dibandingkan dengan database, data milik pelanggan, urutan transaksi |
| M04 Manajemen Pengguna | `tests/04-manajemen-pengguna.spec.js` | daftar, cari, filter, tambah (negatif & boundary), ubah, hapus, aturan bisnis, 404 |
| M05 Alur Bisnis | `tests/05-alur-bisnis.spec.js` | admin membuat pelanggan → pelanggan login → admin menghapus → login ditolak |
| M06 Tampilan | `tests/06-tampilan.spec.js` | layar ponsel 375 px |
| White-box | `../tests/Feature/WhiteBox/*.php` | jalur AuthController, DashboardController, UserController, middleware, pesan validasi |

## Struktur folder

```
QA/
├── discovery/        Tahap 1: data crawler per role, screenshot halaman, QA_Handover.md, diagram alur (.mmd)
├── tests/            Spec Playwright per modul + helpers.js
├── evidence/run1/    Bukti eksekusi awal: test-results.json, evidence.jsonl, logs.jsonl, screenshot, hasil PHPUnit
├── evidence/run2/    Bukti retest: hasil JSON & PHPUnit (screenshot Run 2 ada di screenshots/)
├── screenshots/      Bukti eksekusi terakhir, nama TC_ROLE_FITUR_STEPxx_STATUS.png
├── bugs/bugs.json    Bug, documentation discrepancy, enhancement suggestion
├── reports/          Laporan QA (PDF/DOCX) + katalog test case (XLSX)
└── scripts/          discover.js, login-auth.js, build_report.py, docx_to_pdf.ps1
```

## Menjalankan ulang

```bash
# terminal 1 — root proyek
php artisan serve --host=127.0.0.1 --port=8000

# terminal 2 — folder QA
npm run setup                  # Playwright + Chromium
cp .env.example .env           # isi akun uji dari seeder (lihat README proyek)
npm run test:env               # TC000 validasi environment
npm test                       # seluruh test black-box
npm run auth:login             # (opsional) simpan sesi per role untuk discovery
npm run discover -- --role admin --start /dashboard/admin
```

White-box (dari root proyek; PHP 8.4 Laragon perlu pdo_sqlite):

```bash
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --testdox
```

Laporan dibuat ulang dari data hasil eksekusi: `python scripts/build_report.py` lalu
`powershell -ExecutionPolicy Bypass -File scripts/docx_to_pdf.ps1`.

`.env`, `auth/`, `results/`, dan `archive/` tidak di-commit karena berisi kredensial, sesi login, atau hasil sementara.
