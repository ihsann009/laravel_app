<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Kost extends Model
{
    use HasFactory;

    protected $table = 'kost';
    protected $primaryKey = 'id_kost';
    public $incrementing = true;

    protected $fillable = [
        'id_pemilik',
        'nama_kost',
        'alamat',
        'deskripsi',
        'fasilitas',
        'foto_utama',
        'harga_sewa',
        'status_kost'
    ];

    protected $casts = [
        'harga_sewa' => 'decimal:2',
        'status_kost' => 'string'
    ];

    // Relasi dengan pemilik kost
    public function pemilik()
    {
        return $this->belongsTo(Pengguna::class, 'id_pemilik', 'id_pengguna');
    }

    /**
     * Get the full URL for the kost's main photo.
     *
     * @return string|null
     */
    public function getFotoUtamaAttribute($value)
    {
        if ($value) {
            return Storage::url($value);
        }
        return null;
    }
} 