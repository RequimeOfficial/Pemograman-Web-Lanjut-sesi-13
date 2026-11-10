<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Urutan pemanggilan menghormati relasi induk -> anak:
     * users (admin/kasir) -> layanan -> tambahan -> pelanggan -> transaksi
     * (transaksi sekaligus mengisi tambahan_transaksi, pembayaran, notifikasi).
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            LayananSeeder::class,
            TambahanSeeder::class,
            PelangganSeeder::class,
            TransaksiSeeder::class,
        ]);
    }
}
