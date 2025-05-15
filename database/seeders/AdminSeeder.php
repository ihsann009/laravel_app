<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('pengguna')->insert([
            'nama' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('admin123'),
            'nomor_telepon' => '08123456789',
            'alamat' => 'Jl. Admin No. 1',
            'role' => 'admin',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
} 