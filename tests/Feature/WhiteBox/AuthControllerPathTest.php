<?php

namespace Tests\Feature\WhiteBox;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * White-box (basis path testing) untuk AuthController::login.
 *
 * Titik keputusan: (1) validasi input, (2) RateLimiter::tooManyAttempts,
 * (3) Auth::attempt gagal, (4) dashboardRoute() === null.
 * Cyclomatic complexity V(G) = 4 + 1 = 5 jalur independen (WB-AUTH-P1 s.d. P5).
 */
class AuthControllerPathTest extends TestCase
{
    use RefreshDatabase;

    /** WB-AUTH-P1: validasi gagal → berhenti sebelum RateLimiter. */
    public function test_p1_validasi_gagal(): void
    {
        $this->post(route('login.process'), ['email' => 'bukan-email', 'password' => ''])
            ->assertSessionHasErrors(['email' => 'Format email tidak valid.', 'password' => 'Kata sandi wajib diisi.']);

        $this->assertSame(0, RateLimiter::attempts('bukan-email|127.0.0.1'));
        $this->assertGuest();
    }

    /** WB-AUTH-P2: kunci RateLimiter sudah penuh → ditolak walau kata sandi benar. */
    public function test_p2_terkunci_oleh_rate_limiter(): void
    {
        $user = User::factory()->admin()->create(['password' => 'password123']);
        $key = strtolower($user->email).'|127.0.0.1';
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($key);
        }

        $this->post(route('login.process'), ['email' => $user->email, 'password' => 'password123'])
            ->assertSessionHasErrors('email');

        $this->assertStringStartsWith('Terlalu banyak percobaan masuk.', session('errors')->first('email'));
        $this->assertGuest();
    }

    /** WB-AUTH-P3: kredensial salah → hit RateLimiter bertambah 1. */
    public function test_p3_kredensial_salah_menambah_hitungan(): void
    {
        $user = User::factory()->kasir()->create(['password' => 'password123']);

        $this->post(route('login.process'), ['email' => $user->email, 'password' => 'salah'])
            ->assertSessionHasErrors(['email' => 'Email atau kata sandi tidak sesuai.']);

        $this->assertSame(1, RateLimiter::attempts(strtolower($user->email).'|127.0.0.1'));
        $this->assertGuest();
    }

    /** WB-AUTH-P4: login berhasil tetapi role tidak dikenal → sesi diakhiri. */
    public function test_p4_role_tidak_dikenal_sesi_diakhiri(): void
    {
        $user = User::factory()->create(['role' => 'owner', 'password' => 'password123']);

        $this->post(route('login.process'), ['email' => $user->email, 'password' => 'password123'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Peran pengguna tidak dikenali.']);

        $this->assertGuest();
    }

    /** WB-AUTH-P5: login berhasil → hitungan dibersihkan dan diarahkan sesuai role. */
    public function test_p5_login_berhasil_membersihkan_hitungan(): void
    {
        $user = User::factory()->pelanggan()->create(['password' => 'password123']);
        $key = strtolower($user->email).'|127.0.0.1';
        RateLimiter::hit($key);

        $this->post(route('login.process'), ['email' => $user->email, 'password' => 'password123'])
            ->assertRedirect(route('dashboard.pelanggan'));

        $this->assertSame(0, RateLimiter::attempts($key));
        $this->assertAuthenticatedAs($user);
    }
}
