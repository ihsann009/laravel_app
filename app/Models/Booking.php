<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $table = 'booking';
    protected $primaryKey = 'id_booking';
    public $incrementing = false; // Set to false since we're using manual IDs

    protected $fillable = [
        'id_booking',
        'id_kamar',
        'id_penyewa',
        'tanggal_mulai',
        'tanggal_selesai',
        'status',
        'total_harga',
        'catatan'
    ];

    protected $casts = [
        'tanggal_mulai' => 'datetime',
        'tanggal_selesai' => 'datetime',
        'total_harga' => 'decimal:2'
    ];

    // Relasi dengan kamar
    public function kamar()
    {
        return $this->belongsTo(Kamar::class, 'id_kamar', 'id_kamar');
    }

    // Relasi dengan penyewa
    public function penyewa()
    {
        return $this->belongsTo(Pengguna::class, 'id_penyewa', 'id_pengguna');
    }
} 