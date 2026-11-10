<?php

namespace Database\Seeders;

use App\Models\Layanan;
use App\Models\Notifikasi;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\Tambahan;
use App\Models\Transaksi;
use App\Models\User;
use Database\Factories\NotifikasiFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Data transaksi beserta tabel turunannya:
 * tambahan_transaksi (pivot N:M), pembayaran (1:N), dan notifikasi (1:N).
 * Wajib dijalankan SETELAH UserSeeder, LayananSeeder, TambahanSeeder, PelangganSeeder.
 */
class TransaksiSeeder extends Seeder
{
    public const JUMLAH_ACAK = 58;

    public function run(): void
    {
        $kasir = User::where('role', User::ROLE_KASIR)->get();
        $pelanggan = Pelanggan::all();
        $layanan = Layanan::all();
        $tambahan = Tambahan::where('aktif', true)->get();

        if ($kasir->isEmpty() || $pelanggan->isEmpty() || $layanan->isEmpty()) {
            throw new RuntimeException('Jalankan UserSeeder, LayananSeeder, dan PelangganSeeder terlebih dahulu.');
        }

        // 1. Dua transaksi statis milik pelanggan uji (data contoh yang mudah dicek)
        $budi = Pelanggan::whereRelation('user', 'email', 'pelanggan1@silaundry.test')->firstOrFail();
        $dewi = Pelanggan::whereRelation('user', 'email', 'pelanggan2@silaundry.test')->firstOrFail();
        $kasirUtama = User::where('email', 'kasir@silaundry.test')->firstOrFail();

        $statis = [
            Transaksi::create([
                'kode_transaksi' => 'TRX-20260928-00001',
                'pelanggan_id' => $budi->id,
                'layanan_id' => $layanan->firstWhere('nama_layanan', 'Cuci Reguler')->id,
                'kasir_id' => $kasirUtama->id,
                'berat_kg' => 3.50,
                'total_harga' => 24500,
                'status' => 'diterima',
                'tanggal_masuk' => '2026-09-28',
            ]),
            Transaksi::create([
                'kode_transaksi' => 'TRX-20261005-00002',
                'pelanggan_id' => $dewi->id,
                'layanan_id' => $layanan->firstWhere('nama_layanan', 'Cuci Setrika')->id,
                'kasir_id' => $kasirUtama->id,
                'berat_kg' => 4.00,
                'total_harga' => 32000,
                'status' => 'selesai',
                'tanggal_masuk' => '2026-10-05',
                'tanggal_selesai' => '2026-10-08',
            ]),
        ];

        // 2. Transaksi acak dari factory; recycle() memakai ulang data induk yang sudah ada
        $acak = Transaksi::factory(self::JUMLAH_ACAK)
            ->recycle($pelanggan)
            ->recycle($layanan)
            ->recycle($kasir)
            ->create();

        foreach ([...$statis, ...$acak] as $transaksi) {
            $this->lampirkanTambahan($transaksi, $tambahan);
            $this->buatPembayaran($transaksi);
            $this->buatNotifikasi($transaksi);
        }
    }

    /**
     * Mengisi tabel pivot tambahan_transaksi (0–2 tambahan) lalu menghitung ulang total harga.
     */
    private function lampirkanTambahan(Transaksi $transaksi, $tambahan): void
    {
        $dipilih = $tambahan->random(min($tambahan->count(), fake()->randomElement([0, 0, 1, 1, 2])));

        $pivot = [];
        foreach ($dipilih as $item) {
            $jumlah = $item->satuan === 'pcs' ? fake()->numberBetween(1, 5) : 1;
            $pivot[$item->id] = [
                'jumlah' => $jumlah,
                'harga_satuan' => $item->harga,
                'subtotal' => $jumlah * (float) $item->harga,
            ];
        }

        $transaksi->tambahan()->attach($pivot);

        $totalTambahan = collect($pivot)->sum('subtotal');
        $transaksi->update([
            'total_harga' => round((float) $transaksi->berat_kg * (float) $transaksi->layanan->harga_per_kg) + $totalTambahan,
        ]);
    }

    /**
     * diambil = selalu lunas; selesai = lunas atau DP; diterima/diproses = DP atau belum bayar.
     */
    private function buatPembayaran(Transaksi $transaksi): void
    {
        $skema = match ($transaksi->status) {
            'diambil' => fake()->randomElement(['lunas', 'dp_lunas']),
            'selesai' => fake()->randomElement(['lunas', 'dp']),
            default => fake()->randomElement(['dp', 'belum']),
        };

        $factory = Pembayaran::factory()->for($transaksi, 'transaksi');

        if ($skema === 'lunas') {
            $factory->create();
        }

        if (in_array($skema, ['dp', 'dp_lunas'], true)) {
            $dp = $factory->dp()->create();

            if ($skema === 'dp_lunas') {
                $factory->create([
                    'jumlah' => (float) $transaksi->total_harga - (float) $dp->jumlah,
                    'tanggal_bayar' => Carbon::parse($transaksi->tanggal_selesai ?? $transaksi->tanggal_masuk)->setTime(16, 0),
                ]);
            }
        }
    }

    /**
     * Satu notifikasi untuk setiap tahap status yang sudah dilalui transaksi.
     */
    private function buatNotifikasi(Transaksi $transaksi): void
    {
        $tahap = array_slice(Transaksi::STATUS, 0, array_search($transaksi->status, Transaksi::STATUS, true) + 1);

        foreach ($tahap as $i => $status) {
            Notifikasi::factory()->for($transaksi, 'transaksi')->create([
                'pesan' => NotifikasiFactory::PESAN[$status],
                'tanggal_kirim' => Carbon::parse($transaksi->tanggal_masuk)->addDays($i)->setTime(9 + $i, 0),
            ]);
        }
    }
}
