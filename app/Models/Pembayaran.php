<?php

namespace App\Models;

use Database\Factories\PembayaranFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pembayaran atas sebuah transaksi (bisa lebih dari satu kali: DP lalu pelunasan).
 */
class Pembayaran extends Model
{
    /** @use HasFactory<PembayaranFactory> */
    use HasFactory;

    public const METODE = ['tunai', 'transfer', 'qris'];

    protected $table = 'pembayaran';

    protected $fillable = [
        'transaksi_id',
        'diterima_oleh',
        'jumlah',
        'metode',
        'tanggal_bayar',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:2',
            'tanggal_bayar' => 'datetime',
        ];
    }

    /**
     * N:1 — pembayaran milik satu transaksi.
     */
    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class, 'transaksi_id');
    }

    /**
     * N:1 — kasir (users) yang menerima pembayaran.
     */
    public function penerima(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diterima_oleh');
    }
}
