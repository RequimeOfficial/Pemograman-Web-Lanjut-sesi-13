<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel transaksi laundry. Merujuk ke pelanggan, layanan, dan users (kasir),
     * sehingga wajib dibuat SETELAH ketiga tabel induk tersebut.
     */
    public function up(): void
    {
        Schema::create('transaksi', function (Blueprint $table) {
            $table->id();
            $table->string('kode_transaksi', 30)->unique();

            // Pelanggan dihapus -> riwayat transaksinya ikut terhapus
            $table->foreignId('pelanggan_id')
                ->constrained('pelanggan')
                ->cascadeOnDelete();

            // Layanan & kasir tidak boleh dihapus selama masih dipakai transaksi
            $table->foreignId('layanan_id')
                ->constrained('layanan')
                ->restrictOnDelete();

            $table->foreignId('kasir_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->decimal('berat_kg', 8, 2);
            $table->decimal('total_harga', 12, 2);

            // diterima | diproses | selesai | diambil
            $table->string('status', 20)->default('diterima')->index();

            $table->date('tanggal_masuk');
            $table->date('tanggal_selesai')->nullable();
            $table->text('catatan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi');
    }
};
