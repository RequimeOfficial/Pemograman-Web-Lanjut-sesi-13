<?php

namespace Tests\Feature\WhiteBox;

use App\Models\Layanan;
use App\Models\Pelanggan;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * White-box untuk DashboardController.
 *
 * index():     keputusan (1) belum login, (2) role tanpa dashboard → V(G) = 3.
 * pelanggan(): keputusan (1) profil pelanggan ada → V(G) = 2.
 * admin()/kasir(): satu jalur; diuji aliran datanya (isi & urutan $transaksiTerbaru).
 */
class DashboardControllerPathTest extends TestCase
{
    use RefreshDatabase;

    /** WB-DASH-P1: tamu → login. */
    public function test_index_p1_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    /** WB-DASH-P2: role tidak dikenal → logout paksa + pesan. */
    public function test_index_p2_role_tidak_dikenal_dilogout(): void
    {
        $user = User::factory()->create(['role' => 'owner']);

        $this->actingAs($user)->get(route('home'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /** WB-DASH-P3: role dikenal → dashboard masing-masing. */
    public function test_index_p3_diarahkan_sesuai_role(): void
    {
        $kasir = User::factory()->kasir()->create();

        $this->actingAs($kasir)->get(route('home'))->assertRedirect(route('dashboard.kasir'));
    }

    /** WB-DASH-P4: akun pelanggan tanpa profil → peringatan, angka 0. */
    public function test_pelanggan_p1_tanpa_profil(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_PELANGGAN]);
        $user->pelanggan()->delete();

        $this->actingAs($user)->get(route('dashboard.pelanggan'))
            ->assertOk()
            ->assertSee('Akun belum terhubung dengan data pelanggan.')
            ->assertViewHas('totalTransaksi', 0);
    }

    /** WB-DASH-P5: profil ada → hanya transaksi milik sendiri yang dihitung. */
    public function test_pelanggan_p2_hanya_transaksi_sendiri(): void
    {
        $layanan = Layanan::factory()->create();
        $kasir = User::factory()->kasir()->create();
        [$saya, $lain] = Pelanggan::factory()->count(2)->create();
        Transaksi::factory()->count(2)->create(['pelanggan_id' => $saya->id, 'layanan_id' => $layanan->id, 'kasir_id' => $kasir->id]);
        Transaksi::factory()->count(3)->create(['pelanggan_id' => $lain->id, 'layanan_id' => $layanan->id, 'kasir_id' => $kasir->id]);

        $this->actingAs($saya->user)->get(route('dashboard.pelanggan'))
            ->assertOk()
            ->assertViewHas('totalTransaksi', 2)
            ->assertViewHas('transaksiTerbaru', fn ($list) => $list->every(fn ($t) => $t->pelanggan_id === $saya->id));
    }

    /**
     * WB-DASH-D1 (data flow): "Transaksi Terbaru" harus diurutkan dari tanggal masuk terbaru,
     * termasuk saat beberapa transaksi tercatat pada detik yang sama (created_at identik).
     */
    public function test_transaksi_terbaru_diurutkan_dari_tanggal_masuk_terbaru(): void
    {
        $this->freezeTime();
        $layanan = Layanan::factory()->create();
        $kasir = User::factory()->kasir()->create();
        $pelanggan = Pelanggan::factory()->create();
        foreach (['2026-09-01', '2026-11-10', '2026-08-15', '2026-10-05', '2026-11-01', '2026-07-20'] as $tanggal) {
            Transaksi::factory()->create([
                'pelanggan_id' => $pelanggan->id, 'layanan_id' => $layanan->id, 'kasir_id' => $kasir->id,
                'tanggal_masuk' => $tanggal,
            ]);
        }

        $this->actingAs($kasir)->get(route('dashboard.kasir'))
            ->assertOk()
            ->assertViewHas('transaksiTerbaru', fn ($list) => $list->map(fn ($t) => $t->tanggal_masuk->toDateString())->all()
                === ['2026-11-10', '2026-11-01', '2026-10-05', '2026-09-01', '2026-08-15']);
    }
}
