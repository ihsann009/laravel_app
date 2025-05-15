<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kost', function (Blueprint $table) {
            $table->integer('id_kost')->autoIncrement()->primary();
            $table->integer('id_pemilik');
            $table->string('nama_kost', 100);
            $table->text('alamat');
            $table->text('deskripsi')->nullable();
            $table->text('fasilitas')->nullable();
            $table->string('foto_utama')->nullable();
            $table->decimal('harga_sewa', 10, 2);
            $table->enum('status_kost', ['tersedia', 'terbooking', 'ditutup'])->default('tersedia');
            $table->timestamps();

            $table->foreign('id_pemilik')
                  ->references('id_pengguna')
                  ->on('pengguna')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kost');
    }
}; 