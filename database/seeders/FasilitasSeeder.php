<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Fasilitas;

class FasilitasSeeder extends Seeder
{
    public function run(): void
    {
        $fasilitas = [
            // Fasilitas Kamar
            [
                'nama_fasilitas' => 'AC',
                'kategori' => 'kamar',
                'keterangan' => 'Air Conditioner'
            ],
            [
                'nama_fasilitas' => 'Kamar Mandi Dalam',
                'kategori' => 'kamar',
                'keterangan' => 'Kamar mandi pribadi di dalam kamar'
            ],
            [
                'nama_fasilitas' => 'Kasur',
                'kategori' => 'kamar',
                'keterangan' => 'Tempat tidur'
            ],
            [
                'nama_fasilitas' => 'Lemari',
                'kategori' => 'kamar',
                'keterangan' => 'Lemari pakaian'
            ],
            [
                'nama_fasilitas' => 'Meja Belajar',
                'kategori' => 'kamar',
                'keterangan' => 'Meja untuk belajar/kerja'
            ],
            [
                'nama_fasilitas' => 'WiFi',
                'kategori' => 'kamar',
                'keterangan' => 'Internet WiFi'
            ],

            // Fasilitas Umum
            [
                'nama_fasilitas' => 'Parkir Motor',
                'kategori' => 'umum',
                'keterangan' => 'Area parkir motor'
            ],
            [
                'nama_fasilitas' => 'Parkir Mobil',
                'kategori' => 'umum',
                'keterangan' => 'Area parkir mobil'
            ],
            [
                'nama_fasilitas' => 'Dapur Bersama',
                'kategori' => 'umum',
                'keterangan' => 'Dapur untuk digunakan bersama'
            ],
            [
                'nama_fasilitas' => 'Ruang Tamu',
                'kategori' => 'umum',
                'keterangan' => 'Ruang tamu bersama'
            ],
            [
                'nama_fasilitas' => 'Laundry',
                'kategori' => 'umum',
                'keterangan' => 'Layanan laundry'
            ],
            [
                'nama_fasilitas' => 'CCTV',
                'kategori' => 'umum',
                'keterangan' => 'Keamanan CCTV 24 jam'
            ]
        ];

        foreach ($fasilitas as $f) {
            Fasilitas::create($f);
        }
    }
} 