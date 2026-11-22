<?php

namespace Tests\Feature\WhiteBox;

use App\Models\Layanan;
use App\Models\Pelanggan;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * White-box untuk UserController, UpdateUserRequest::after, RoleMiddleware, dan pesan validasi.
 *
 * destroy():        keputusan (1) akun sendiri, (2) kasir bertransaksi, (3) pelanggan bertransaksi → V(G) = 4.
 * index():          keputusan (1) kata kunci terisi, (2) role valid → V(G) = 3.
 * after():          keputusan (1) akun sendiri && role berubah → V(G) = 2.
 * RoleMiddleware:   keputusan (1) belum login, (2) role tidak cocok → V(G) = 3.
 */
class UserControllerPathTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function transaksiUntuk(User $kasir, Pelanggan $pelanggan): void
    {
        Transaksi::factory()->create([
            'kasir_id' => $kasir->id, 'pelanggan_id' => $pelanggan->id, 'layanan_id' => Layanan::factory()->create()->id,
        ]);
    }

    /** WB-DEL-P1: menghapus akun sendiri ditolak. */
    public function test_destroy_p1_akun_sendiri(): void
    {
        $this->actingAs($this->admin)->delete(route('users.destroy', $this->admin))
            ->assertSessionHas('error', 'Kamu tidak dapat menghapus akun yang sedang digunakan.');
        $this->assertModelExists($this->admin);
    }

    /** WB-DEL-P2: kasir yang tercatat di transaksi ditolak. */
    public function test_destroy_p2_kasir_bertransaksi(): void
    {
        $kasir = User::factory()->kasir()->create();
        $this->transaksiUntuk($kasir, Pelanggan::factory()->create());

        $this->actingAs($this->admin)->delete(route('users.destroy', $kasir))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'masih tercatat sebagai kasir'));
        $this->assertModelExists($kasir);
    }

    /** WB-DEL-P3: pelanggan yang memiliki transaksi ditolak. */
    public function test_destroy_p3_pelanggan_bertransaksi(): void
    {
        $pelanggan = Pelanggan::factory()->create();
        $this->transaksiUntuk(User::factory()->kasir()->create(), $pelanggan);

        $this->actingAs($this->admin)->delete(route('users.destroy', $pelanggan->user))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'masih memiliki riwayat transaksi'));
        $this->assertModelExists($pelanggan->user);
    }

    /** WB-DEL-P4: pengguna tanpa keterkaitan transaksi berhasil dihapus. */
    public function test_destroy_p4_berhasil(): void
    {
        $kasir = User::factory()->kasir()->create();

        $this->actingAs($this->admin)->delete(route('users.destroy', $kasir))
            ->assertSessionHas('success');
        $this->assertModelMissing($kasir);
    }

    /** WB-IDX-P1..P3: tanpa filter, dengan kata kunci, dengan role valid; role tidak valid diabaikan. */
    public function test_index_jalur_filter(): void
    {
        User::factory()->kasir()->create(['name' => 'Rina Kasir']);
        User::factory()->pelanggan()->create(['name' => 'Joko Pelanggan']);
        $total = fn ($res) => $res->viewData('users')->total();

        $this->assertSame(3, $total($this->actingAs($this->admin)->get(route('users.index'))));
        $this->assertSame(1, $total($this->actingAs($this->admin)->get(route('users.index', ['q' => 'Rina']))));
        $this->assertSame(1, $total($this->actingAs($this->admin)->get(route('users.index', ['role' => 'pelanggan']))));
        $this->assertSame(3, $total($this->actingAs($this->admin)->get(route('users.index', ['role' => 'hacker']))));
    }

    /** WB-UPD-P1/P2: after() hanya menolak bila akun sendiri DAN role berubah. */
    public function test_update_after_hook(): void
    {
        $data = fn (User $u, string $role) => ['name' => $u->name, 'username' => $u->username, 'email' => $u->email, 'role' => $role];

        $this->actingAs($this->admin)->put(route('users.update', $this->admin), $data($this->admin, 'kasir'))
            ->assertSessionHasErrors(['role' => 'Kamu tidak dapat mengubah peran akun yang sedang digunakan.']);

        $this->actingAs($this->admin)->put(route('users.update', $this->admin), $data($this->admin, 'admin'))
            ->assertSessionHasNoErrors();

        $kasir = User::factory()->kasir()->create();
        $this->actingAs($this->admin)->put(route('users.update', $kasir), $data($kasir, 'admin'))
            ->assertSessionHasNoErrors();
        $this->assertSame('admin', $kasir->fresh()->role);
    }

    /** WB-MW-P1..P3: RoleMiddleware. */
    public function test_role_middleware_jalur(): void
    {
        $this->get(route('users.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->kasir()->create())->get(route('users.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('users.index'))->assertOk();
    }

    /** WB-VAL-1: semua pesan batas panjang (min/max) memakai bahasa Indonesia dari UserValidationMessages. */
    public function test_pesan_validasi_min_max_berbahasa_indonesia(): void
    {
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => str_repeat('a', 256), 'username' => 'ab', 'email' => 'x@silaundry.test',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123', 'role' => 'kasir',
        ])->assertSessionHasErrors([
            'name' => 'Nama maksimal 255 karakter.',
            'username' => 'Nama Pengguna minimal 3 karakter.',
        ]);
    }
}
