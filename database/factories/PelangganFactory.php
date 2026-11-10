<?php

namespace Database\Factories;

use App\Models\Pelanggan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Pelanggan> */
class PelangganFactory extends Factory
{
    protected $model = Pelanggan::class;

    public function definition(): array
    {
        return [
            // Otomatis membuat akun users ber-role pelanggan (relasi 1:1)
            'user_id' => User::factory()->pelanggan(),

            // Nama pelanggan disamakan dengan nama akun agar data konsisten
            'nama' => fn (array $attributes) => User::find($attributes['user_id'])?->name ?? fake()->name(),

            // Prefix operator seluler Indonesia + 8 digit
            'no_hp' => fake()->randomElement(['0812', '0813', '0821', '0852', '0857', '0878', '0896'])
                .fake()->numerify('########'),

            // Locale id_ID menghasilkan alamat bergaya Indonesia (Jl./Gg., kota)
            'alamat' => fake()->streetAddress().', '.fake()->city(),
        ];
    }
}
