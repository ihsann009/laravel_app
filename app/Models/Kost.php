<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kost extends Model
{
    use HasFactory;

    protected $table = 'kost';
    protected $primaryKey = 'id_kost';
    public $incrementing = false; // Set to false since we're using manual IDs

    protected $fillable = [
        'id_kost',
        'id_pemilik',
        'nama_kost',
        'alamat',
        'deskripsi',
        'foto_utama',
        'status_aktif'
    ];

    protected $casts = [
        'status_aktif' => 'boolean'
    ];

    // Relasi dengan pemilik kost
    public function pemilik()
    {
        return $this->belongsTo(Pengguna::class, 'id_pemilik', 'id_pengguna');
    }

    // Relasi dengan kamar
    public function kamar()
    {
        return $this->hasMany(Kamar::class, 'id_kost');
    }
} 