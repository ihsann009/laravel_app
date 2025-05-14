<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kost', function (Blueprint $table) {
            $table->id('id_kost');
            $table->foreignId('id_pemilik')->constrained('pengguna', 'id_pengguna')->onDelete('cascade');
            $table->string('nama_kost', 100);
            $table->text('alamat');
            $table->text('deskripsi')->nullable();
            $table->string('foto_utama')->nullable();
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kost');
    }
}; 