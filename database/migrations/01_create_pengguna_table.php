<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengguna', function (Blueprint $table) {
            $table->integer('id_pengguna')->primary();
            $table->string('nama', 100);
            $table->string('email', 100)->unique();
            $table->string('password');
            $table->enum('role', ['penyewa', 'pemilik_kost', 'admin'])->default('penyewa');
            $table->string('nomor_telepon', 15);
            $table->text('alamat')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengguna');
    }
}; 