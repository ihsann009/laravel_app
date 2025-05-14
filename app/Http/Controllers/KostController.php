<?php

namespace App\Http\Controllers;

use App\Models\Kost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class KostController extends Controller
{
    public function __construct()
    {
        // Middleware hanya untuk method tertentu, kecuali search
        $this->middleware('auth:sanctum')->except(['search']);
    }

    // Menampilkan semua kost milik pemilik yang sedang login
    public function index()
    {
        $user = Auth::user();
        if ($user->role === 'pemilik_kost') {
            $kost = Kost::where('id_pemilik', $user->id_pengguna)
                        ->with('kamar')
                        ->get();
        } else {
            // Untuk penyewa, tampilkan semua kost yang aktif
            $kost = Kost::where('status_aktif', true)
                        ->with(['kamar' => function($query) {
                            $query->where('status', 'tersedia');
                        }])
                        ->get();
        }

        return response()->json([
            'message' => 'Data kost berhasil diambil',
            'kost' => $kost
        ]);
    }

    // Menambah data kost baru
    public function store(Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'pemilik_kost') {
            return response()->json(['message' => 'Anda tidak memiliki akses untuk menambahkan kost'], 403);
        }

        $validator = Validator::make($request->all(), [
            'nama_kost' => 'required|string|max:100',
            'alamat' => 'required|string',
            'deskripsi' => 'nullable|string',
            'fasilitas' => 'nullable|string',
            'foto_utama' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Handle upload foto jika ada
            $fotoPath = null;
            if ($request->hasFile('foto_utama')) {
                $foto = $request->file('foto_utama');
                $fotoPath = $foto->store('kost', 'public');
            }

            // Generate ID kost
            $lastId = DB::table('kost')->max('id_kost');
            $newId = $lastId ? $lastId + 1 : 1;

            // Buat kost baru
            $kost = Kost::create([
                'id_kost' => $newId,
                'id_pemilik' => $user->id_pengguna,
                'nama_kost' => $request->nama_kost,
                'alamat' => $request->alamat,
                'deskripsi' => $request->deskripsi,
                'fasilitas' => $request->fasilitas,
                'foto_utama' => $fotoPath,
                'status_aktif' => true
            ]);

            DB::commit();

            // Load relasi yang diperlukan
            $kost->load(['pemilik:id_pengguna,nama,nomor_telepon']);

            return response()->json([
                'message' => 'Kost berhasil ditambahkan',
                'kost' => [
                    'id_kost' => $kost->id_kost,
                    'nama_kost' => $kost->nama_kost,
                    'alamat' => $kost->alamat,
                    'deskripsi' => $kost->deskripsi,
                    'foto_utama' => $kost->foto_utama ? url('storage/' . $kost->foto_utama) : null,
                    'status_aktif' => $kost->status_aktif,
                    'pemilik' => $kost->pemilik,
                    'created_at' => $kost->created_at,
                    'updated_at' => $kost->updated_at
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Gagal menambahkan kost',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Menampilkan detail kost
    public function show($id)
    {
        try {
            $kost = Kost::with(['kamar', 'pemilik'])->findOrFail($id);
            
            if (Auth::user()->role !== 'pemilik_kost' && !$kost->status_aktif) {
                return response()->json(['message' => 'Kost tidak ditemukan'], 404);
            }

            return response()->json([
                'message' => 'Detail kost berhasil diambil',
                'kost' => $kost
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Kost tidak ditemukan'], 404);
        }
    }

    // Mengupdate data kost
    public function update(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $kost = Kost::findOrFail($id);

            if ($kost->id_pemilik !== $user->id_pengguna) {
                return response()->json(['message' => 'Anda tidak memiliki akses untuk mengubah kost ini'], 403);
            }

            $validator = Validator::make($request->all(), [
                'nama_kost' => 'required|string|max:100',
                'alamat' => 'required|string',
                'deskripsi' => 'nullable|string',
                'fasilitas' => 'nullable|string',
                'foto_utama' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'status_aktif' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Handle upload foto baru jika ada
            if ($request->hasFile('foto_utama')) {
                // Hapus foto lama jika ada
                if ($kost->foto_utama) {
                    Storage::disk('public')->delete($kost->foto_utama);
                }
                $foto = $request->file('foto_utama');
                $fotoPath = $foto->store('kost', 'public');
                $kost->foto_utama = $fotoPath;
            }

            $kost->update([
                'nama_kost' => $request->nama_kost,
                'alamat' => $request->alamat,
                'deskripsi' => $request->deskripsi,
                'fasilitas' => $request->fasilitas,
                'status_aktif' => $request->status_aktif ?? $kost->status_aktif
            ]);

            return response()->json([
                'message' => 'Data kost berhasil diperbarui',
                'kost' => $kost
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Kost tidak ditemukan'], 404);
        }
    }

    // Menghapus data kost
    public function destroy($id)
    {
        try {
            $user = Auth::user();
            $kost = Kost::findOrFail($id);

            if ($kost->id_pemilik !== $user->id_pengguna) {
                return response()->json(['message' => 'Anda tidak memiliki akses untuk menghapus kost ini'], 403);
            }

            // Hapus foto jika ada
            if ($kost->foto_utama) {
                Storage::disk('public')->delete($kost->foto_utama);
            }

            $kost->delete();

            return response()->json([
                'message' => 'Kost berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Kost tidak ditemukan'], 404);
        }
    }

    // Menambahkan method untuk pencarian kost
    public function search(Request $request)
    {
        try {
            $query = Kost::query()->where('status_aktif', true);

            // Filter berdasarkan alamat
            if ($request->has('alamat')) {
                $alamat = $request->alamat;
                $query->where('alamat', 'like', "%{$alamat}%");
            }

            // Filter berdasarkan nama kost
            if ($request->has('nama_kost')) {
                $nama = $request->nama_kost;
                $query->where('nama_kost', 'like', "%{$nama}%");
            }

            // Filter berdasarkan harga minimum
            if ($request->has('harga_min')) {
                $query->whereHas('kamar', function($q) use ($request) {
                    $q->where('harga_per_bulan', '>=', $request->harga_min)
                      ->where('status', 'tersedia');
                });
            }

            // Filter berdasarkan harga maksimum
            if ($request->has('harga_max')) {
                $query->whereHas('kamar', function($q) use ($request) {
                    $q->where('harga_per_bulan', '<=', $request->harga_max)
                      ->where('status', 'tersedia');
                });
            }

            // Filter berdasarkan ukuran kamar minimum
            if ($request->has('ukuran_min')) {
                $query->whereHas('kamar', function($q) use ($request) {
                    $q->where('ukuran_kamar', '>=', $request->ukuran_min)
                      ->where('status', 'tersedia');
                });
            }

            // Filter berdasarkan fasilitas
            if ($request->has('fasilitas')) {
                $fasilitas = $request->fasilitas;
                $query->whereHas('kamar', function($q) use ($fasilitas) {
                    $q->where('fasilitas', 'like', "%{$fasilitas}%")
                      ->where('status', 'tersedia');
                });
            }

            // Load relasi yang diperlukan
            $kost = $query->with(['kamar' => function($query) {
                $query->where('status', 'tersedia');
            }, 'pemilik:id_pengguna,nama,nomor_telepon'])
            ->get()
            ->map(function ($kost) {
                return [
                    'id_kost' => $kost->id_kost,
                    'nama_kost' => $kost->nama_kost,
                    'alamat' => $kost->alamat,
                    'deskripsi' => $kost->deskripsi,
                    'foto_utama' => $kost->foto_utama ? url('storage/' . $kost->foto_utama) : null,
                    'status_aktif' => $kost->status_aktif,
                    'pemilik' => $kost->pemilik,
                    'kamar' => $kost->kamar->map(function ($kamar) {
                        return [
                            'id_kamar' => $kamar->id_kamar,
                            'nomor_kamar' => $kamar->nomor_kamar,
                            'harga_per_bulan' => $kamar->harga_per_bulan,
                            'ukuran_kamar' => $kamar->ukuran_kamar,
                            'status' => $kamar->status,
                            'deskripsi' => $kamar->deskripsi,
                            'fasilitas' => $kamar->fasilitas,
                            'foto_kamar' => $kamar->foto_kamar ? url('storage/' . $kamar->foto_kamar) : null
                        ];
                    }),
                    'created_at' => $kost->created_at,
                    'updated_at' => $kost->updated_at
                ];
            });

            return response()->json([
                'message' => 'Hasil pencarian kost',
                'total' => $kost->count(),
                'kost' => $kost
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat mencari kost',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 