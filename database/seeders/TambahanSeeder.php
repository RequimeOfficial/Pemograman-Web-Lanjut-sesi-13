<?php

namespace Database\Seeders;

use App\Models\Tambahan;
use Illuminate\Database\Seeder;

/**
 * Data master statis: layanan tambahan (add-on).
 */
class TambahanSeeder extends Seeder
{
    public function run(): void
    {
        $tambahan = [
            ['nama' => 'Parfum Premium', 'harga' => 3000, 'satuan' => 'transaksi'],
            ['nama' => 'Pelembut Pakaian', 'harga' => 2000, 'satuan' => 'transaksi'],
            ['nama' => 'Hapus Noda Membandel', 'harga' => 5000, 'satuan' => 'pcs'],
            ['nama' => 'Hanger', 'harga' => 1000, 'satuan' => 'pcs'],
            ['nama' => 'Plastik Packing Jumbo', 'harga' => 2500, 'satuan' => 'pcs'],
            ['nama' => 'Antar Jemput', 'harga' => 10000, 'satuan' => 'transaksi', 'aktif' => false],
        ];

        foreach ($tambahan as $data) {
            Tambahan::updateOrCreate(['nama' => $data['nama']], $data + ['aktif' => true]);
        }
    }
}
