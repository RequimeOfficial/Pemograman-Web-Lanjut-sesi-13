<?php

namespace Database\Seeders;

use App\Models\Pelanggan;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Pelanggan = akun users (role pelanggan) + data pelanggan (relasi 1:1).
 * 2 pelanggan statis untuk login uji + 20 pelanggan acak dari factory.
 */
class PelangganSeeder extends Seeder
{
    public const JUMLAH_ACAK = 20;

    public function run(): void
    {
        $statis = [
            ['email' => 'pelanggan1@silaundry.test', 'nama' => 'Budi Santoso', 'username' => 'budi', 'no_hp' => '081234567890', 'alamat' => 'Jl. Melati No. 10, Jombang'],
            ['email' => 'pelanggan2@silaundry.test', 'nama' => 'Dewi Lestari', 'username' => 'dewi', 'no_hp' => '082198765432', 'alamat' => 'Jl. Kenanga No. 5, Jombang'],
        ];

        foreach ($statis as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['nama'],
                    'username' => $data['username'],
                    'role' => User::ROLE_PELANGGAN,
                    'password' => 'password123',
                    'email_verified_at' => now(),
                ],
            );

            Pelanggan::updateOrCreate(
                ['user_id' => $user->id],
                ['nama' => $data['nama'], 'no_hp' => $data['no_hp'], 'alamat' => $data['alamat']],
            );
        }

        // Factory Pelanggan otomatis membuat akun users ber-role pelanggan untuk setiap baris
        Pelanggan::factory(self::JUMLAH_ACAK)->create();
    }
}
