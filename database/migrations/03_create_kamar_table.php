<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kamar', function (Blueprint $table) {
            $table->id('id_kamar');
            $table->foreignId('id_kost')->constrained('kost', 'id_kost')->onDelete('cascade');
            $table->string('nomor_kamar', 20);
            $table->decimal('harga_per_bulan', 10, 2);
            $table->decimal('ukuran_kamar', 5, 2); // in square meters
            $table->enum('status', ['tersedia', 'terisi', 'maintenance'])->default('tersedia');
            $table->text('deskripsi')->nullable();
            $table->json('foto_kamar')->nullable(); // Store multiple photo URLs
            $table->json('fasilitas_kamar')->nullable(); // Store facilities as {"nama": "AC", "keterangan": "1.5 PK"}
            $table->json('fasilitas_umum')->nullable(); // Store general facilities
            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('harga_per_bulan');
            $table->unique(['id_kost', 'nomor_kamar']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kamar');
    }
}; 