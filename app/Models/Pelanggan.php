<?php

namespace App\Models;

use Database\Factories\PelangganFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Pelanggan extends Model
{
    /** @use HasFactory<PelangganFactory> */
    use HasFactory;

    protected $table = 'pelanggan';

    protected $fillable = [
        'user_id',
        'nama',
        'no_hp',
        'alamat',
    ];

    /**
     * 1:1 (sisi anak) — data pelanggan milik satu akun users.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 1:N — satu pelanggan memiliki banyak transaksi.
     */
    public function transaksi(): HasMany
    {
        return $this->hasMany(Transaksi::class, 'pelanggan_id');
    }

    /**
     * Seluruh pembayaran pelanggan, melewati tabel transaksi.
     */
    public function pembayaran(): HasManyThrough
    {
        return $this->hasManyThrough(Pembayaran::class, Transaksi::class, 'pelanggan_id', 'transaksi_id');
    }
}
