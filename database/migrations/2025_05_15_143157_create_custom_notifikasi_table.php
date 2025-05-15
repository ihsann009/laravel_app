<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifikasi', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_pengguna');
            $table->string('subjek')->nullable();
            $table->text('pesan');
            $table->string('link')->nullable();
            $table->timestamp('dibaca_pada')->nullable();
            $table->timestamps();

            $table->foreign('id_pengguna')->references('id_pengguna')->on('pengguna')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifikasi');
    }
};
