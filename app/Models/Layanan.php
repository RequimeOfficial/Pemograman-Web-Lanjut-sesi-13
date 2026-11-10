<?php

namespace App\Models;

use Database\Factories\LayananFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Layanan extends Model
{
    /** @use HasFactory<LayananFactory> */
    use HasFactory;

    protected $table = 'layanan';

    protected $fillable = [
        'nama_layanan',
        'harga_per_kg',
        'estimasi_hari',
        'deskripsi',
    ];

    protected function casts(): array
    {
        return [
            'harga_per_kg' => 'decimal:2',
            'estimasi_hari' => 'integer',
        ];
    }

    /**
     * 1:N — satu layanan dipakai oleh banyak transaksi.
     */
    public function transaksi(): HasMany
    {
        return $this->hasMany(Transaksi::class, 'layanan_id');
    }
}
