<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kamar extends Model
{
    use HasFactory;

    protected $table = 'kamar';
    protected $primaryKey = 'id_kamar';
    public $incrementing = false; // Set to false since we're using manual IDs

    protected $fillable = [
        'id_kamar',
        'id_kost',
        'nomor_kamar',
        'harga_per_bulan',
        'ukuran_kamar',
        'status',
        'deskripsi',
        'fasilitas',
        'foto_kamar',
        'fasilitas_kamar',
        'fasilitas_umum'
    ];

    protected $casts = [
        'harga_per_bulan' => 'decimal:2',
        'ukuran_kamar' => 'decimal:2'
    ];

    // Relasi dengan kost
    public function kost()
    {
        return $this->belongsTo(Kost::class, 'id_kost', 'id_kost');
    }

    // Relasi dengan booking
    public function booking()
    {
        return $this->hasMany(Booking::class, 'id_kamar');
    }

    // Scope untuk kamar tersedia
    public function scopeTersedia($query)
    {
        return $query->where('status', 'tersedia');
    }
} 