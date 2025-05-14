<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ulasan', function (Blueprint $table) {
            $table->integer('id_ulasan')->autoIncrement()->primary();
            $table->integer('id_kost');      // kost yang diulas
            $table->integer('id_user');      // user yang memberi ulasan
            $table->integer('id_booking');   // booking terkait
            $table->tinyInteger('rating');              // 1-5
            $table->text('komentar')->nullable();
            $table->timestamps();

            $table->foreign('id_kost')->references('id_kost')->on('kost')->onDelete('cascade');
            $table->foreign('id_user')->references('id_pengguna')->on('pengguna')->onDelete('cascade');
            $table->foreign('id_booking')->references('id_booking')->on('booking')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ulasan');
    }
}; 