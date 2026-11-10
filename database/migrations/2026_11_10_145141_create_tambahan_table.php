<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel master layanan tambahan (add-on), mis. parfum premium, setrika ekspres,
     * packing plastik. Satu transaksi dapat memiliki banyak tambahan dan satu
     * tambahan dapat dipakai banyak transaksi (relasi N:M).
     */
    public function up(): void
    {
        Schema::create('tambahan', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100)->unique();
            $table->decimal('harga', 10, 2);
            $table->string('satuan', 20)->default('pcs');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tambahan');
    }
};
