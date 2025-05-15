<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notifikasi extends Model
{
    use HasFactory;

    protected $table = 'notifikasi'; // Nama tabel yang digunakan model ini

    protected $fillable = [
        'id_pengguna',
        'subjek',
        'pesan',
        'link',
        'dibaca_pada',
    ];

    protected $casts = [
        'dibaca_pada' => 'datetime',
    ];

    /**
     * Mendapatkan pengguna yang memiliki notifikasi ini.
     */
    public function pengguna()
    {
        return $this->belongsTo(Pengguna::class, 'id_pengguna', 'id_pengguna');
    }
} 