<?php

namespace App\Http\Controllers;

use App\Models\Kamar;
use App\Models\Kost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class KamarController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // Menampilkan semua kamar dari kost tertentu
    public function index($id_kost)
    {
        try {
            $kost = Kost::findOrFail($id_kost);
            
            if (Auth::user()->role === 'pemilik_kost' && $kost->id_pemilik !== Auth::user()->id_pengguna) {
                return response()->json(['message' => 'Anda tidak memiliki akses untuk melihat kamar ini'], 403);
            }

            $kamar = Kamar::where('id_kost', $id_kost)->get();

            return response()->json([
                'message' => 'Data kamar berhasil diambil',
                'kamar' => $kamar
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Kost tidak ditemukan'], 404);
        }
    }

    // Menambah kamar baru
    public function store(Request $request, $id_kost)
    {
        try {
            $kost = Kost::findOrFail($id_kost);
            
            if ($kost->id_pemilik !== Auth::user()->id_pengguna) {
                return response()->json(['message' => 'Anda tidak memiliki akses untuk menambahkan kamar'], 403);
            }

            $validator = Validator::make($request->all(), [
                'nomor_kamar' => 'required|string|max:20',
                'harga_per_bulan' => 'required|numeric|min:0',
                'ukuran_kamar' => 'required|string|max:20',
                'deskripsi' => 'nullable|string',
                'foto_kamar' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $existingKamar = Kamar::where('id_kost', $id_kost)
                                ->where('nomor_kamar', $request->nomor_kamar)
                                ->first();
            if ($existingKamar) {
                return response()->json([
                    'message' => 'Nomor kamar sudah digunakan'
                ], 422);
            }

            try {
                DB::beginTransaction();

                $fotoPath = null;
                if ($request->hasFile('foto_kamar')) {
                    $foto = $request->file('foto_kamar');
                    $fotoPath = $foto->store('kamar', 'public');
                }

                $lastId = DB::table('kamar')->max('id_kamar');
                $newId = $lastId ? $lastId + 1 : 1;

                $kamar = Kamar::create([
                    'id_kamar' => $newId,
                    'id_kost' => $id_kost,
                    'nomor_kamar' => $request->nomor_kamar,
                    'harga_per_bulan' => $request->harga_per_bulan,
                    'ukuran_kamar' => $request->ukuran_kamar,
                    'status' => 'tersedia',
                    'deskripsi' => $request->deskripsi,
                    'foto_kamar' => $fotoPath
                ]);

                DB::commit();

                return response()->json([
                    'message' => 'Kamar berhasil ditambahkan',
                    'kamar' => $kamar
                ], 201);

            } catch (\Exception $e) {
                DB::rollback();
                return response()->json([
                    'message' => 'Gagal menambahkan kamar',
                    'error' => $e->getMessage()
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'Kost tidak ditemukan'], 404);
        }
    }

    // Menampilkan detail kamar
    public function show($id_kost, $id_kamar)
    {
        try {
            $kost = Kost::findOrFail($id_kost);
            $kamar = Kamar::where('id_kost', $id_kost)
                         ->where('id_kamar', $id_kamar)
                         ->firstOrFail();

            if (Auth::user()->role === 'pemilik_kost' && $kost->id_pemilik !== Auth::user()->id_pengguna) {
                return response()->json(['message' => 'Anda tidak memiliki akses untuk melihat kamar ini'], 403);
            }

            return response()->json([
                'message' => 'Detail kamar berhasil diambil',
                'kamar' => $kamar
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Kamar tidak ditemukan'], 404);
        }
    }

    // Mengupdate data kamar
    public function update(Request $request, $id_kost, $id_kamar)
    {
        try {
            $kost = Kost::findOrFail($id_kost);
            $kamar = Kamar::where('id_kost', $id_kost)
                         ->where('id_kamar', $id_kamar)
                         ->firstOrFail();

            if ($kost->id_pemilik !== Auth::user()->id_pengguna) {
                return response()->json(['message' => 'Anda tidak memiliki akses untuk mengubah kamar ini'], 403);
            }

            $validator = Validator::make($request->all(), [
                'nomor_kamar' => 'required|string|max:20',
                'harga_per_bulan' => 'required|numeric|min:0',
                'ukuran_kamar' => 'required|string|max:20',
                'status' => 'required|in:tersedia,terisi,maintenance',
                'deskripsi' => 'nullable|string',
                'foto_kamar' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $existingKamar = Kamar::where('id_kost', $id_kost)
                                ->where('nomor_kamar', $request->nomor_kamar)
                                ->where('id_kamar', '!=', $id_kamar)
                                ->first();
            if ($existingKamar) {
                return response()->json([
                    'message' => 'Nomor kamar sudah digunakan'
                ], 422);
            }

            if ($request->hasFile('foto_kamar')) {
                if ($kamar->foto_kamar) {
                    Storage::disk('public')->delete($kamar->foto_kamar);
                }
                $foto = $request->file('foto_kamar');
                $fotoPath = $foto->store('kamar', 'public');
                $kamar->foto_kamar = $fotoPath;
            }

            $kamar->update([
                'nomor_kamar' => $request->nomor_kamar,
                'harga_per_bulan' => $request->harga_per_bulan,
                'ukuran_kamar' => $request->ukuran_kamar,
                'status' => $request->status,
                'deskripsi' => $request->deskripsi
            ]);

            return response()->json([
                'message' => 'Data kamar berhasil diperbarui',
                'kamar' => $kamar
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Kamar tidak ditemukan'], 404);
        }
    }

    // Menghapus kamar
    public function destroy($id_kost, $id_kamar)
    {
        try {
            $kost = Kost::findOrFail($id_kost);
            $kamar = Kamar::where('id_kost', $id_kost)
                         ->where('id_kamar', $id_kamar)
                         ->firstOrFail();

            if ($kost->id_pemilik !== Auth::user()->id_pengguna) {
                return response()->json(['message' => 'Anda tidak memiliki akses untuk menghapus kamar ini'], 403);
            }

            if ($kamar->foto_kamar) {
                Storage::disk('public')->delete($kamar->foto_kamar);
            }

            $kamar->delete();

            return response()->json([
                'message' => 'Kamar berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Kamar tidak ditemukan'], 404);
        }
    }
} 