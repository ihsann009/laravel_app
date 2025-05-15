<?php

namespace App\Http\Controllers;

use App\Models\Ulasan;
use App\Models\Booking;
use App\Models\Kost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class UlasanController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if ($user->role === 'admin') {
            $ulasan = Ulasan::with(['kost', 'penyewa'])->get();
        } else if ($user->role === 'pemilik_kost') {
            $kostIds = Kost::where('id_pemilik', $user->id_pengguna)->pluck('id_kost');
            $ulasan = Ulasan::whereIn('id_kost', $kostIds)
                           ->with(['kost', 'penyewa'])
                           ->get();
        } else {
            $ulasan = Ulasan::where('id_penyewa', $user->id_pengguna)
                           ->with(['kost', 'penyewa'])
                           ->get();
        }

        if ($ulasan->isEmpty()) {
            return response()->json([
                'message' => 'Belum ada ulasan',
                'ulasan' => []
            ]);
        }

        return response()->json([
            'message' => 'Data ulasan berhasil diambil',
            'ulasan' => $ulasan
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'admin' && $user->role !== 'penyewa') {
            return response()->json(['message' => 'Anda tidak memiliki akses untuk menambahkan ulasan'], 403);
        }

        $validator = Validator::make($request->all(), [
            'id_kost' => 'required|integer|exists:kost,id_kost',
            'rating' => 'required|integer|min:1|max:5',
            'komentar' => 'required|string',
            'id_penyewa' => 'required_if:role,admin|integer|exists:pengguna,id_pengguna'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Cek apakah kost sudah pernah diulas oleh penyewa ini
            $existingUlasan = Ulasan::where('id_kost', $request->id_kost)
                                  ->where('id_penyewa', $user->role === 'admin' ? $request->id_penyewa : $user->id_pengguna)
                                  ->first();

            if ($existingUlasan) {
                return response()->json([
                    'message' => 'Anda sudah memberikan ulasan untuk kost ini'
                ], 400);
            }

            // Generate ID ulasan
            $lastId = DB::table('ulasan')->max('id_ulasan');
            $newId = $lastId ? $lastId + 1 : 1;

            $ulasan = Ulasan::create([
                'id_ulasan' => $newId,
                'id_kost' => $request->id_kost,
                'id_penyewa' => $user->role === 'admin' ? $request->id_penyewa : $user->id_pengguna,
                'rating' => $request->rating,
                'komentar' => $request->komentar
            ]);

            return response()->json([
                'message' => 'Ulasan berhasil ditambahkan',
                'ulasan' => $ulasan->load(['kost', 'penyewa'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menambahkan ulasan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $ulasan = Ulasan::with(['kost', 'penyewa'])->findOrFail($id);
            return response()->json([
                'message' => 'Detail ulasan berhasil diambil',
                'ulasan' => $ulasan
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ulasan tidak ditemukan'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $ulasan = Ulasan::findOrFail($id);

            // Cek akses berdasarkan role
            if ($user->role === 'penyewa' && $ulasan->id_penyewa !== $user->id_pengguna) {
                return response()->json(['message' => 'Anda tidak memiliki akses untuk mengubah ulasan ini'], 403);
            }
            if ($user->role !== 'admin' && $user->role !== 'penyewa') {
                return response()->json(['message' => 'Anda tidak memiliki akses untuk mengubah ulasan'], 403);
            }

            $validator = Validator::make($request->all(), [
                'rating' => 'required|integer|min:1|max:5',
                'komentar' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $ulasan->update([
                'rating' => $request->rating,
                'komentar' => $request->komentar
            ]);

            return response()->json([
                'message' => 'Ulasan berhasil diperbarui',
                'ulasan' => $ulasan->load(['kost', 'penyewa'])
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ulasan tidak ditemukan'], 404);
        }
    }

    public function destroy($id)
    {
        try {
            $user = Auth::user();
            $ulasan = Ulasan::findOrFail($id);

            // Cek akses berdasarkan role
            if ($user->role === 'penyewa' && $ulasan->id_penyewa !== $user->id_pengguna) {
                return response()->json(['message' => 'Anda tidak memiliki akses untuk menghapus ulasan ini'], 403);
            }
            if ($user->role !== 'admin' && $user->role !== 'penyewa') {
                return response()->json(['message' => 'Anda tidak memiliki akses untuk menghapus ulasan'], 403);
            }

            $ulasan->delete();

            return response()->json([
                'message' => 'Ulasan berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ulasan tidak ditemukan'], 404);
        }
    }
} 