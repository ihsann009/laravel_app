<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ulasan extends Model
{
    use HasFactory;

    protected $table = 'ulasan';
    protected $primaryKey = 'id_ulasan';
    public $incrementing = true;

    protected $fillable = [
        'id_kost',
        'id_user',
        'id_booking',
        'rating',
        'komentar',
    ];

    // Relasi ke kost
    public function kost()
    {
        return $this->belongsTo(Kost::class, 'id_kost', 'id_kost');
    }

    // Relasi ke user (penyewa)
    public function user()
    {
        return $this->belongsTo(Pengguna::class, 'id_user', 'id_pengguna');
    }

    // Relasi ke booking
    public function booking()
    {
        return $this->belongsTo(Booking::class, 'id_booking', 'id_booking');
    }
} 