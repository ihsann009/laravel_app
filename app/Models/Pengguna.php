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
    public $incrementing = false; // Set to false since we're using manual IDs

    protected $fillable = [
        'nama',
        'email',
        'password',
        'nomor_telepon',
        'role',
        'alamat',
        'is_verified'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'tanggal_daftar' => 'datetime',
    ];

    // Relasi dengan kost (untuk pemilik kost)
    public function kost()
    {
        return $this->hasMany(Kost::class, 'id_pemilik', 'id_pengguna');
    }

    // Relasi dengan booking (untuk penyewa)
    public function booking()
    {
        return $this->hasMany(Booking::class, 'id_penyewa', 'id_pengguna');
    }
} 