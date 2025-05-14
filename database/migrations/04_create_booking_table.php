<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking', function (Blueprint $table) {
            $table->integer('id_booking')->autoIncrement()->primary();
            $table->integer('id_penyewa');
            $table->integer('id_kamar');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->enum('status', ['pending', 'diterima', 'ditolak', 'batal'])->default('pending');
            $table->decimal('total_harga', 10, 2);
            $table->string('bukti_pembayaran')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('id_penyewa')
                  ->references('id_pengguna')
                  ->on('pengguna')
                  ->onDelete('cascade');

            $table->foreign('id_kamar')
                  ->references('id_kamar')
                  ->on('kamar')
                  ->onDelete('cascade');

            // Indexes
            $table->index('status');
            $table->index('tanggal_mulai');
            $table->index('tanggal_selesai');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking');
    }
}; 