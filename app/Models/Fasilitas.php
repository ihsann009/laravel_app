<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fasilitas extends Model
{
    use HasFactory;

    protected $table = 'fasilitas';
    protected $primaryKey = 'id_fasilitas';

    protected $fillable = [
        'nama_fasilitas',
        'kategori',
        'deskripsi',
        'icon',
    ];

    // Relasi dengan kamar
    public function kamar()
    {
        return $this->belongsToMany(Kamar::class, 'kamar_fasilitas', 'id_fasilitas', 'id_kamar')
                    ->withPivot('keterangan')
                    ->withTimestamps();
    }
} 