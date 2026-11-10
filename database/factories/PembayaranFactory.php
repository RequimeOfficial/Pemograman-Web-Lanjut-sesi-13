<?php

namespace Database\Factories;

use App\Models\Pembayaran;
use App\Models\Transaksi;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<Pembayaran> */
class PembayaranFactory extends Factory
{
    protected $model = Pembayaran::class;

    public function definition(): array
    {
        return [
            'transaksi_id' => Transaksi::factory(),

            // Penerima = kasir yang mencatat transaksi
            'diterima_oleh' => fn (array $a) => Transaksi::find($a['transaksi_id'])->kasir_id,

            // Default: langsung lunas
            'jumlah' => fn (array $a) => Transaksi::find($a['transaksi_id'])->total_harga,

            // Tunai paling sering dipakai di laundry kiloan
            'metode' => fake()->randomElement(['tunai', 'tunai', 'tunai', 'transfer', 'qris', 'qris']),

            'tanggal_bayar' => fn (array $a) => Carbon::parse(Transaksi::find($a['transaksi_id'])->tanggal_masuk)
                ->setTime(fake()->numberBetween(8, 20), fake()->numberBetween(0, 59)),

            'keterangan' => 'Pelunasan',
        ];
    }

    /**
     * Pembayaran uang muka (DP) 50% dari total.
     */
    public function dp(): static
    {
        return $this->state(fn () => [
            'jumlah' => fn (array $a) => round(Transaksi::find($a['transaksi_id'])->total_harga / 2),
            'keterangan' => 'Uang muka (DP)',
        ]);
    }
}
