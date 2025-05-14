<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Pengguna extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'pengguna';
    protected $primaryKey = 'id_pengguna';

    protected $fillable = [
        'nama',
        'email',
        'password',
        'role',
        'nomor_telepon',
        'alamat',
        'ktp_number',
        'is_verified',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'tanggal_daftar' => 'datetime',
    ];

    // Relasi dengan kost (untuk pemilik kost)
    public function kost()
    {
        return $this->hasMany(Kost::class, 'id_pemilik');
    }

    // Relasi dengan booking (untuk penyewa)
    public function booking()
    {
        return $this->hasMany(Booking::class, 'id_pengguna');
    }
} 