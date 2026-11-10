<?php

namespace Database\Factories;

use App\Models\Layanan;
use App\Models\Pelanggan;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Relasi induk didefinisikan sebagai factory. Saat seeding, gunakan
 * ->recycle($pelanggan)->recycle($layanan)->recycle($kasir) agar memakai data
 * yang sudah ada, bukan membuat data induk baru.
 *
 * @extends Factory<Transaksi>
 */
class TransaksiFactory extends Factory
{
    protected $model = Transaksi::class;

    public function definition(): array
    {
        return [
            'pelanggan_id' => Pelanggan::factory(),
            'layanan_id' => Layanan::factory(),
            'kasir_id' => User::factory()->kasir(),

            // Berat cucian kiloan wajar: 1–10 kg
            'berat_kg' => fake()->randomFloat(2, 1, 10),

            // Total = berat x tarif layanan (tambahan dihitung di seeder)
            'total_harga' => fn (array $a) => round($a['berat_kg'] * (float) Layanan::find($a['layanan_id'])->harga_per_kg),

            // Sebaran 90 hari terakhir supaya laporan per bulan terisi
            'tanggal_masuk' => fake()->dateTimeBetween('-90 days', 'now')->format('Y-m-d'),

            'status' => fake()->randomElement(Transaksi::STATUS),

            // Hanya transaksi selesai/diambil yang punya tanggal selesai
            'tanggal_selesai' => fn (array $a) => in_array($a['status'], ['selesai', 'diambil'], true)
                ? Carbon::parse($a['tanggal_masuk'])->addDays(Layanan::find($a['layanan_id'])->estimasi_hari)->min(today())->toDateString()
                : null,

            'catatan' => fake()->optional(0.3)->randomElement([
                'Pisahkan pakaian putih.',
                'Jangan pakai pemutih.',
                'Lipat rapi, tanpa hanger.',
                'Ada noda kopi di kemeja.',
                'Diantar ke alamat pelanggan.',
            ]),
        ];
    }

    public function diterima(): static
    {
        return $this->state(fn () => ['status' => 'diterima']);
    }

    public function diproses(): static
    {
        return $this->state(fn () => ['status' => 'diproses']);
    }

    public function selesai(): static
    {
        return $this->state(fn () => ['status' => 'selesai']);
    }

    public function diambil(): static
    {
        return $this->state(fn () => ['status' => 'diambil']);
    }
}
