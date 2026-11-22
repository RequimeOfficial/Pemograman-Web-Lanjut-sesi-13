# QA Handover — SiLaundry (Tahap 1: Application Discovery)

| Item | Keterangan |
|---|---|
| Aplikasi | SiLaundry — Sistem Informasi Manajemen Laundry |
| Jenis | Aplikasi web internal berbasis role (back-office), server-rendered |
| Teknologi | Laravel 13, PHP 8.4, MySQL 8.0, Blade + Tailwind v4 + Alpine.js |
| URL uji | `http://127.0.0.1:8000` (lokal, `php artisan serve`) |
| Sumber dokumen | `README.md` proyek, kode sumber (`routes/web.php`, controller, form request), hasil crawler |
| Metode | Crawler Playwright read-only per role (`npm run discover`) + pembacaan kode |
| Tanggal | 22 November 2026 |

Label kepercayaan: **Verified** = dibuktikan lewat eksekusi test; **Observed** = terlihat di browser/crawler;
**Documented** = tertulis di README; **Inferred** = disimpulkan dari kode.

## 1. Halaman yang ditemukan

| Page ID | URL | Role | Bukti | Label |
|---|---|---|---|---|
| P001 | `/login` (dari `/`) | Publik | `discovery/screenshots/P001_PUBLIC_login.png` | Observed |
| P002 | `/dashboard/admin` | Admin | `P001_ADMIN_dashboard-admin.png` | Observed |
| P003 | `/users` (+ `?q=`, `?role=`, `?page=`) | Admin | `P002_ADMIN_users.png`, `P004`–`P006` | Observed |
| P004 | `/users/create` | Admin | `P003_ADMIN_users-create.png` | Observed |
| P005 | `/users/{id}/edit` | Admin | `P007`–`P010_ADMIN_users-*-edit.png` | Observed |
| P006 | `/dashboard/kasir` | Kasir | `P001_KASIR_dashboard-kasir.png` | Observed |
| P007 | `/dashboard/pelanggan` | Pelanggan | `P001_PELANGGAN_dashboard-pelanggan.png` | Observed |
| P008 | Halaman galat 403 / 404 | Semua | ditemukan saat uji akses (TC011, TC039) | Verified |

Aksi `POST /logout` dan `DELETE /users/{id}` berupa form, tidak dikunjungi crawler (dilewati sesuai aturan read-only).

## 2. Fitur & modul

| Modul | Feature ID | Fitur | Role | Label |
|---|---|---|---|---|
| M01 Autentikasi | F01 | Login email + kata sandi, redirect sesuai role | Semua | Documented |
| | F02 | Kunci login 5× gagal → 60 detik | Publik | Documented |
| | F03 | Logout | Semua | Observed |
| M02 Hak Akses | F04 | Pembatasan route per role (403) & menu sesuai role | Semua | Documented |
| M03 Dashboard | F05 | Dashboard admin: total pengguna/pelanggan/layanan/transaksi, per peran, transaksi terbaru | Admin | Observed |
| | F06 | Dashboard kasir: ringkasan + transaksi terbaru | Kasir | Observed |
| | F07 | Dashboard pelanggan: transaksi & notifikasi milik sendiri | Pelanggan | Observed |
| M04 Manajemen Pengguna | F08 | Daftar + cari + filter peran + paginasi 10 | Admin | Observed |
| | F09 | Tambah pengguna (+ data pelanggan otomatis) | Admin | Observed |
| | F10 | Ubah pengguna (tidak bisa ubah peran sendiri) | Admin | Documented |
| | F11 | Hapus pengguna (konfirmasi; tolak akun sendiri, kasir/pelanggan bertransaksi) | Admin | Documented |

## 3. Matriks role

| Fitur | Publik | Admin | Kasir | Pelanggan |
|---|:-:|:-:|:-:|:-:|
| Login | ✓ | – | – | – |
| Dashboard admin | – | ✓ | 403 | 403 |
| Dashboard kasir | – | 403 | ✓ | 403 |
| Dashboard pelanggan | – | 403 | 403 | ✓ |
| Manajemen pengguna | – | ✓ | 403 | 403 |

## 4. QA requirement (baseline pengujian)

| REQ | Requirement | Sumber |
|---|---|---|
| REQ-01 | Login dengan email & kata sandi, diarahkan ke dashboard sesuai role | README |
| REQ-02 | Login menolak isian kosong, format email salah, dan kredensial salah dengan pesan Indonesia | Kode `AuthController` |
| REQ-03 | 5 kali gagal → dikunci sementara (60 detik) | README |
| REQ-04 | Logout mengakhiri sesi; halaman terproteksi tidak dapat diakses tamu | README / kode |
| REQ-05 | Akses route dibatasi per role (halaman 403), menu sesuai role | README |
| REQ-06 | Dashboard menampilkan ringkasan data yang benar; pelanggan hanya melihat data miliknya | README |
| REQ-07 | Daftar pengguna dengan pencarian, filter peran, paginasi | README |
| REQ-08 | Tambah pengguna tervalidasi: wajib isi, username 3–50 (huruf/angka/._-) unik, email unik, kata sandi ≥ 8 + konfirmasi | Kode `StoreUserRequest` |
| REQ-09 | Peran Pelanggan wajib No HP & alamat dan otomatis memiliki data pelanggan | README |
| REQ-10 | Ubah pengguna; admin tidak dapat mengubah peran akunnya sendiri | README |
| REQ-11 | Hapus pengguna dengan konfirmasi; tolak akun sendiri, kasir/pelanggan yang memiliki transaksi | README |
| REQ-12 | Antarmuka konsisten berbahasa Indonesia dan dapat dipakai di layar ponsel | README (desain) / Inferred |

## 5. Alur bisnis utama

- Alur login multi role: `discovery/diagrams/alur-login.mmd`
- Alur pengelolaan akun pelanggan: `discovery/diagrams/alur-kelola-pengguna.mmd`

## 6. Keterbatasan & hal yang perlu konfirmasi

1. Modul layanan, transaksi, pembayaran, dan notifikasi baru tersedia di database (ditampilkan di dashboard); belum ada halaman CRUD sehingga **di luar scope** pengujian fungsional.
2. Role Kasir dan Pelanggan hanya memiliki satu halaman (dashboard). README mencentang "Melihat data" untuk kedua role → dicatat sebagai *documentation discrepancy* DOC-001.
3. Kontrol akses diverifikasi di **server** (HTTP 403 dan manipulasi request), bukan hanya visibilitas menu.
4. Pengujian memakai data seeder di lingkungan lokal; tidak ada data production.
