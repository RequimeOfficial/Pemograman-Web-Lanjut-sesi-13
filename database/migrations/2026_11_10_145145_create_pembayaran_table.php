<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel pembayaran. Satu transaksi dapat dibayar beberapa kali (DP lalu pelunasan),
     * sehingga relasi transaksi -> pembayaran adalah 1:N.
     */
    public function up(): void
    {
        Schema::create('pembayaran', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transaksi_id')
                ->constrained('transaksi')
                ->cascadeOnDelete();

            // Kasir penerima pembayaran; bila akun kasir dihapus, catatan pembayaran tetap ada
            $table->foreignId('diterima_oleh')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->decimal('jumlah', 12, 2);
            // tunai | transfer | qris
            $table->string('metode', 20)->default('tunai');
            $table->dateTime('tanggal_bayar');
            $table->string('keterangan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
    }
};
