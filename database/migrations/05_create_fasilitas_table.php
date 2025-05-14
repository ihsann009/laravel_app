<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fasilitas', function (Blueprint $table) {
            $table->id('id_fasilitas');
            $table->string('nama_fasilitas', 100);
            $table->enum('kategori', ['kamar', 'umum']);
            $table->text('deskripsi')->nullable();
            $table->string('icon')->nullable();
            $table->timestamps();

            // Index
            $table->index('kategori');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fasilitas');
    }
}; 