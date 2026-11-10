<?php

namespace Tests\Feature;

use App\Models\Layanan;
use App\Models\Notifikasi;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\Tambahan;
use App\Models\Transaksi;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LayananSeeder;
use Database\Seeders\PelangganSeeder;
use Database\Seeders\TambahanSeeder;
use Database\Seeders\TransaksiSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian LKP Pertemuan 4: struktur migrasi, relasi Eloquent dua arah,
 * factory, dan seeder.
 */
class DatabaseRelasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_relasi_satu_ke_satu_user_dan_pelanggan(): void
    {
        $pelanggan = Pelanggan::factory()->create();

        $this->assertSame(User::ROLE_PELANGGAN, $pelanggan->user->role);
        $this->assertTrue($pelanggan->user->pelanggan->is($pelanggan));
        $this->assertSame($pelanggan->user->name, $pelanggan->nama);
    }

    public function test_user_id_pelanggan_unik_sehingga_benar_benar_satu_ke_satu(): void
    {
        $pelanggan = Pelanggan::factory()->create();

        $this->expectException(QueryException::class);
        Pelanggan::factory()->create(['user_id' => $pelanggan->user_id]);
    }

    public function test_relasi_satu_ke_banyak_dan_kebalikannya(): void
    {
        $kasir = User::factory()->kasir()->create();
        $pelanggan = Pelanggan::factory()->create();
        $layanan = Layanan::factory()->create();

        Transaksi::factory(3)->recycle([$kasir, $pelanggan, $layanan])->create();

        $this->assertCount(3, $pelanggan->transaksi);
        $this->assertCount(3, $layanan->transaksi);
        $this->assertCount(3, $kasir->transaksiSebagaiKasir);

        $transaksi = $pelanggan->transaksi->first();
        $this->assertTrue($transaksi->pelanggan->is($pelanggan));
        $this->assertTrue($transaksi->layanan->is($layanan));
        $this->assertTrue($transaksi->kasir->is($kasir));
    }

    public function test_relasi_banyak_ke_banyak_lewat_tabel_pivot(): void
    {
        $transaksi = Transaksi::factory()->create();
        [$parfum, $hanger] = Tambahan::factory(2)->create();

        $transaksi->tambahan()->attach([
            $parfum->id => ['jumlah' => 1, 'harga_satuan' => 3000, 'subtotal' => 3000],
            $hanger->id => ['jumlah' => 4, 'harga_satuan' => 1000, 'subtotal' => 4000],
        ]);

        $this->assertCount(2, $transaksi->tambahan);
        $this->assertEquals(4, $transaksi->tambahan->firstWhere('id', $hanger->id)->pivot->jumlah);
        $this->assertTrue($parfum->transaksi->first()->is($transaksi));
        $this->assertDatabaseCount('tambahan_transaksi', 2);
    }

    public function test_pembayaran_dan_notifikasi_milik_transaksi(): void
    {
        $transaksi = Transaksi::factory()->create(['total_harga' => 50000]);

        Pembayaran::factory()->for($transaksi)->dp()->create();
        Notifikasi::factory(2)->for($transaksi)->create();

        $transaksi->refresh();
        $this->assertCount(1, $transaksi->pembayaran);
        $this->assertCount(2, $transaksi->notifikasi);
        $this->assertEquals(25000, $transaksi->total_dibayar);
        $this->assertEquals(25000, $transaksi->sisa_tagihan);
        $this->assertFalse($transaksi->isLunas());
        $this->assertTrue($transaksi->pembayaran->first()->penerima->is($transaksi->kasir));
        $this->assertTrue($transaksi->pelanggan->pembayaran->first()->transaksi->is($transaksi));
    }

    public function test_kode_transaksi_dibuat_otomatis(): void
    {
        $transaksi = Transaksi::factory()->create(['tanggal_masuk' => '2026-11-10']);

        $this->assertMatchesRegularExpression('/^TRX-20261110-[A-Z0-9]{5}$/', $transaksi->kode_transaksi);
    }

    public function test_hapus_transaksi_ikut_menghapus_data_anak_cascade(): void
    {
        $transaksi = Transaksi::factory()->create();
        $transaksi->tambahan()->attach(Tambahan::factory()->create(), ['jumlah' => 1, 'harga_satuan' => 1000, 'subtotal' => 1000]);
        Pembayaran::factory()->for($transaksi)->create();
        Notifikasi::factory()->for($transaksi)->create();

        $transaksi->delete();

        $this->assertDatabaseCount('tambahan_transaksi', 0);
        $this->assertDatabaseCount('pembayaran', 0);
        $this->assertDatabaseCount('notifikasi', 0);
        $this->assertDatabaseCount('tambahan', 1);
    }

    public function test_layanan_yang_dipakai_transaksi_tidak_bisa_dihapus_restrict(): void
    {
        $transaksi = Transaksi::factory()->create();

        $this->expectException(QueryException::class);
        $transaksi->layanan->delete();
    }

    public function test_kasir_dihapus_pembayaran_tetap_ada_null_on_delete(): void
    {
        $penerima = User::factory()->kasir()->create();
        $pembayaran = Pembayaran::factory()->create(['diterima_oleh' => $penerima->id]);

        $penerima->delete();

        $this->assertNull($pembayaran->fresh()->diterima_oleh);
    }

    public function test_database_seeder_mengisi_semua_tabel_secara_konsisten(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('layanan', 5);
        $this->assertDatabaseCount('tambahan', 6);
        $this->assertDatabaseCount('pelanggan', 2 + PelangganSeeder::JUMLAH_ACAK);
        $this->assertDatabaseCount('users', 3 + 2 + PelangganSeeder::JUMLAH_ACAK);
        $this->assertDatabaseCount('transaksi', 2 + TransaksiSeeder::JUMLAH_ACAK);
        $this->assertGreaterThan(0, Pembayaran::count());
        $this->assertGreaterThanOrEqual(Transaksi::count(), Notifikasi::count());

        // Setiap pelanggan punya akun ber-role pelanggan
        $this->assertSame(0, Pelanggan::whereRelation('user', 'role', '!=', User::ROLE_PELANGGAN)->count());

        // Total harga = berat x tarif + subtotal tambahan
        Transaksi::with(['layanan', 'tambahan'])->get()->each(function (Transaksi $t) {
            $harapan = round((float) $t->berat_kg * (float) $t->layanan->harga_per_kg) + $t->tambahan->sum('pivot.subtotal');
            $this->assertEquals($harapan, (float) $t->total_harga, $t->kode_transaksi);
        });

        // Transaksi berstatus diambil selalu lunas, dan tak ada pembayaran melebihi tagihan
        Transaksi::with('pembayaran')->get()->each(function (Transaksi $t) {
            $this->assertLessThanOrEqual((float) $t->total_harga + 0.01, $t->total_dibayar, $t->kode_transaksi);
            if ($t->status === 'diambil') {
                $this->assertTrue($t->isLunas(), $t->kode_transaksi);
            }
        });

        // Akun uji bisa login
        $this->post('/login', ['email' => 'kasir@silaundry.test', 'password' => 'password123']);
        $this->assertAuthenticated();
    }

    public function test_seeder_bisa_dijalankan_ulang_tanpa_duplikasi_data_master(): void
    {
        $this->seed([UserSeeder::class, LayananSeeder::class, TambahanSeeder::class]);
        $this->seed([UserSeeder::class, LayananSeeder::class, TambahanSeeder::class]);

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('layanan', 5);
        $this->assertDatabaseCount('tambahan', 6);
    }
}
