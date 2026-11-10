<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data master statis: akun administrator dan kasir.
 * (Akun pelanggan dibuat oleh PelangganSeeder karena selalu berpasangan dengan data pelanggan.)
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $akun = [
            ['email' => 'admin@silaundry.test', 'name' => 'Administrator SiLaundry', 'username' => 'admin', 'role' => User::ROLE_ADMIN],
            ['email' => 'kasir@silaundry.test', 'name' => 'Kasir SiLaundry', 'username' => 'kasir', 'role' => User::ROLE_KASIR],
            ['email' => 'siti.kasir@silaundry.test', 'name' => 'Siti Rahmawati', 'username' => 'siti', 'role' => User::ROLE_KASIR],
        ];

        foreach ($akun as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                $data + [
                    'password' => 'password123', // di-hash otomatis oleh cast 'hashed'
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
