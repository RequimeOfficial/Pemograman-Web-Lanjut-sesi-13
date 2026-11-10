<?php

namespace Database\Factories;

use App\Models\Layanan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Layanan> */
class LayananFactory extends Factory
{
    protected $model = Layanan::class;

    public function definition(): array
    {
        return [
            // Kombinasi jenis + kecepatan agar nama unik tetapi tetap realistis
            'nama_layanan' => fake()->unique()->randomElement(['Cuci Kering', 'Cuci Setrika', 'Setrika Saja', 'Cuci Sepatu', 'Cuci Bedcover'])
                .' '.fake()->randomElement(['Reguler', 'Express', 'Kilat', 'Premium']),
            // Kelipatan Rp500 seperti tarif laundry sebenarnya
            'harga_per_kg' => fake()->numberBetween(10, 30) * 500,
            'estimasi_hari' => fake()->numberBetween(1, 3),
            'deskripsi' => fake()->optional(0.7)->sentence(8),
        ];
    }
}
