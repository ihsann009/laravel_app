<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VerificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin');
    }

    // Mendapatkan daftar pemilik kost yang belum diverifikasi
    public function getUnverifiedOwners()
    {
        $owners = Pengguna::where('role', 'pemilik_kost')
                         ->where('is_verified', false)
                         ->select('id_pengguna', 'nama', 'email', 'nomor_telepon', 'alamat', 'created_at')
                         ->get();

        return response()->json([
            'message' => 'Daftar pemilik kost yang belum diverifikasi',
            'owners' => $owners
        ]);
    }

    // Verifikasi pemilik kost
    public function verifyOwner($id_pengguna)
    {
        try {
            $owner = Pengguna::where('role', 'pemilik_kost')
                            ->where('id_pengguna', $id_pengguna)
                            ->firstOrFail();

            if ($owner->is_verified) {
                return response()->json([
                    'message' => 'Pemilik kost sudah diverifikasi sebelumnya'
                ], 400);
            }

            $owner->update(['is_verified' => true]);

            return response()->json([
                'message' => 'Pemilik kost berhasil diverifikasi',
                'owner' => $owner
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Pemilik kost tidak ditemukan'
            ], 404);
        }
    }

    // Batalkan verifikasi pemilik kost
    public function unverifyOwner($id_pengguna)
    {
        try {
            $owner = Pengguna::where('role', 'pemilik_kost')
                            ->where('id_pengguna', $id_pengguna)
                            ->firstOrFail();

            if (!$owner->is_verified) {
                return response()->json([
                    'message' => 'Pemilik kost belum diverifikasi'
                ], 400);
            }

            $owner->update(['is_verified' => false]);

            return response()->json([
                'message' => 'Verifikasi pemilik kost berhasil dibatalkan',
                'owner' => $owner
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Pemilik kost tidak ditemukan'
            ], 404);
        }
    }
} 