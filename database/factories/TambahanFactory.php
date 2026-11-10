<?php

namespace Database\Factories;

use App\Models\Tambahan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tambahan> */
class TambahanFactory extends Factory
{
    protected $model = Tambahan::class;

    public function definition(): array
    {
        return [
            'nama' => fake()->unique()->randomElement(['Parfum', 'Pewangi', 'Pelembut', 'Hanger', 'Plastik', 'Noda Membandel'])
                .' '.fake()->randomElement(['Lavender', 'Sakura', 'Premium', 'Ekstra', 'Jumbo']),
            'harga' => fake()->numberBetween(2, 20) * 500,
            'satuan' => fake()->randomElement(['pcs', 'kg', 'transaksi']),
            'aktif' => fake()->boolean(90),
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(fn () => ['aktif' => false]);
    }
}
