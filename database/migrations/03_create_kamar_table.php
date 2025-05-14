<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kamar', function (Blueprint $table) {
            $table->integer('id_kamar')->primary();
            $table->integer('id_kost');
            $table->string('nomor_kamar', 20);
            $table->decimal('harga_per_bulan', 10, 2);
            $table->string('ukuran_kamar', 20); // Format: "3x3", "4x4", etc.
            $table->enum('status', ['tersedia', 'terisi', 'maintenance'])->default('tersedia');
            $table->text('deskripsi')->nullable();
            $table->text('fasilitas')->nullable();
            $table->string('foto_kamar')->nullable(); // Store photo URL
            $table->timestamps();

            // Foreign key
            $table->foreign('id_kost')
                  ->references('id_kost')
                  ->on('kost')
                  ->onDelete('cascade');

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