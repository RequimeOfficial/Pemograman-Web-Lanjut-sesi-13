<?php

namespace App\Models;

use Database\Factories\TambahanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Layanan tambahan (add-on) yang dapat dipilih pada transaksi.
 */
class Tambahan extends Model
{
    /** @use HasFactory<TambahanFactory> */
    use HasFactory;

    protected $table = 'tambahan';

    protected $fillable = [
        'nama',
        'harga',
        'satuan',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'harga' => 'decimal:2',
            'aktif' => 'boolean',
        ];
    }

    /**
     * N:M — tambahan dipakai oleh banyak transaksi (melalui tabel pivot tambahan_transaksi).
     */
    public function transaksi(): BelongsToMany
    {
        return $this->belongsToMany(Transaksi::class, 'tambahan_transaksi')
            ->withPivot(['jumlah', 'harga_satuan', 'subtotal'])
            ->withTimestamps();
    }
}
