<?php

namespace Database\Factories;

use App\Models\Notifikasi;
use App\Models\Transaksi;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Notifikasi> */
class NotifikasiFactory extends Factory
{
    protected $model = Notifikasi::class;

    public const PESAN = [
        'diterima' => 'Cucian Anda telah kami terima dan akan segera diproses.',
        'diproses' => 'Cucian Anda sedang dalam proses pencucian.',
        'selesai' => 'Cucian Anda sudah selesai dan siap diambil.',
        'diambil' => 'Cucian telah diambil. Terima kasih telah menggunakan SiLaundry.',
    ];

    public function definition(): array
    {
        return [
            'transaksi_id' => Transaksi::factory(),
            'pesan' => fake()->randomElement(self::PESAN),
            'tanggal_kirim' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
