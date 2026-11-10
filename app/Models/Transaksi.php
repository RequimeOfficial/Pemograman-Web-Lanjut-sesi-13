<?php

namespace App\Models;

use Database\Factories\TransaksiFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Transaksi extends Model
{
    /** @use HasFactory<TransaksiFactory> */
    use HasFactory;

    public const STATUS = ['diterima', 'diproses', 'selesai', 'diambil'];

    protected $table = 'transaksi';

    protected $fillable = [
        'kode_transaksi',
        'pelanggan_id',
        'layanan_id',
        'kasir_id',
        'berat_kg',
        'total_harga',
        'status',
        'tanggal_masuk',
        'tanggal_selesai',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'berat_kg' => 'decimal:2',
            'total_harga' => 'decimal:2',
            'tanggal_masuk' => 'date',
            'tanggal_selesai' => 'date',
        ];
    }

    /**
     * Kode transaksi dibuat otomatis bila belum diisi, mis. TRX-20261110-AB12C.
     */
    protected static function booted(): void
    {
        static::creating(function (Transaksi $transaksi) {
            if (blank($transaksi->kode_transaksi)) {
                $tanggal = $transaksi->tanggal_masuk ?? now();
                $transaksi->kode_transaksi = 'TRX-'.$tanggal->format('Ymd').'-'.Str::upper(Str::random(5));
            }
        });
    }

    /**
     * N:1 — transaksi milik satu pelanggan.
     */
    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class, 'pelanggan_id');
    }

    /**
     * N:1 — transaksi memakai satu layanan utama.
     */
    public function layanan(): BelongsTo
    {
        return $this->belongsTo(Layanan::class, 'layanan_id');
    }

    /**
     * N:1 — kasir (users) yang mencatat transaksi.
     */
    public function kasir(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kasir_id');
    }

    /**
     * 1:N — notifikasi status yang dikirim untuk transaksi ini.
     */
    public function notifikasi(): HasMany
    {
        return $this->hasMany(Notifikasi::class, 'transaksi_id');
    }

    /**
     * 1:N — riwayat pembayaran (DP / pelunasan).
     */
    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class, 'transaksi_id');
    }

    /**
     * N:M — layanan tambahan yang dipilih, beserta data pada tabel pivot.
     */
    public function tambahan(): BelongsToMany
    {
        return $this->belongsToMany(Tambahan::class, 'tambahan_transaksi')
            ->withPivot(['jumlah', 'harga_satuan', 'subtotal'])
            ->withTimestamps();
    }

    /**
     * Total yang sudah dibayar (memakai jumlah dari relasi pembayaran).
     */
    protected function totalDibayar(): Attribute
    {
        return Attribute::get(fn () => (float) $this->pembayaran()->sum('jumlah'));
    }

    /**
     * Sisa tagihan = total harga - total dibayar (tidak kurang dari 0).
     */
    protected function sisaTagihan(): Attribute
    {
        return Attribute::get(fn () => max(0, (float) $this->total_harga - $this->total_dibayar));
    }

    public function isLunas(): bool
    {
        return $this->sisa_tagihan <= 0;
    }
}
