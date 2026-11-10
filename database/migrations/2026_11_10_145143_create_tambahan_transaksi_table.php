<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel pivot N:M antara transaksi dan tambahan.
     * Nama mengikuti konvensi Laravel: nama tabel tunggal kedua model, urut abjad.
     */
    public function up(): void
    {
        Schema::create('tambahan_transaksi', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transaksi_id')
                ->constrained('transaksi')
                ->cascadeOnDelete();

            $table->foreignId('tambahan_id')
                ->constrained('tambahan')
                ->restrictOnDelete();

            $table->unsignedSmallInteger('jumlah')->default(1);
            // Harga disalin saat transaksi agar riwayat tidak berubah bila harga master naik
            $table->decimal('harga_satuan', 10, 2);
            $table->decimal('subtotal', 12, 2);

            $table->timestamps();

            // Satu jenis tambahan hanya dicatat sekali per transaksi
            $table->unique(['transaksi_id', 'tambahan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tambahan_transaksi');
    }
};
