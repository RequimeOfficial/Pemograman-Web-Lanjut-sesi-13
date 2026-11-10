<?php

namespace Database\Seeders;

use App\Models\Layanan;
use Illuminate\Database\Seeder;

/**
 * Data master statis: daftar layanan utama beserta tarif per kg.
 */
class LayananSeeder extends Seeder
{
    public function run(): void
    {
        $layanan = [
            ['nama_layanan' => 'Cuci Reguler', 'harga_per_kg' => 7000, 'estimasi_hari' => 3, 'deskripsi' => 'Cuci + kering + lipat.'],
            ['nama_layanan' => 'Cuci Setrika', 'harga_per_kg' => 8000, 'estimasi_hari' => 3, 'deskripsi' => 'Cuci + kering + setrika + lipat.'],
            ['nama_layanan' => 'Cuci Express', 'harga_per_kg' => 10000, 'estimasi_hari' => 1, 'deskripsi' => 'Selesai dalam 1 hari.'],
            ['nama_layanan' => 'Setrika Saja', 'harga_per_kg' => 5000, 'estimasi_hari' => 2, 'deskripsi' => 'Khusus pakaian yang sudah dicuci.'],
            ['nama_layanan' => 'Cuci Bedcover', 'harga_per_kg' => 15000, 'estimasi_hari' => 3, 'deskripsi' => 'Bedcover, selimut, dan sprei tebal.'],
        ];

        foreach ($layanan as $data) {
            Layanan::updateOrCreate(['nama_layanan' => $data['nama_layanan']], $data);
        }
    }
}
