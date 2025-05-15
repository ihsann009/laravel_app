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

    // Menampilkan semua kost
    public function index()
    {
        $user = Auth::user();
        if ($user->role === 'admin') {
            $kosts = Kost::with(['pemilik'])->get();
        } else if ($user->role === 'pemilik_kost') {
            $kosts = Kost::where('id_pemilik', $user->id_pengguna)
                        ->with(['pemilik'])
                        ->get();
        } else {
            $kosts = Kost::where('status_kost', 'tersedia')
                        ->with(['pemilik'])
                        ->get();
        }

        if ($kosts->isEmpty()) {
            return response()->json([
                'message' => 'Kost belum ada',
                'kost' => []
            ]);
        }
        return response()->json([
            'message' => 'Data kost berhasil diambil',
            'kost' => $kosts
        ]);
    }

    // Menambah data kost baru
    public function store(Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'admin' && $user->role !== 'pemilik_kost') {
            return response()->json(['message' => 'Anda tidak memiliki akses untuk menambahkan kost'], 403);
        }

        $validator = Validator::make($request->all(), [
            'nama_kost' => 'required|string|max:100',
            'alamat' => 'required|string',
            'deskripsi' => 'nullable|string',
            'fasilitas' => 'nullable|string',
            'foto_utama' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'harga_sewa' => 'required|numeric|min:0',
            'status_kost' => 'required|in:tersedia,terbooking,ditutup'
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
                'harga_sewa' => $request->harga_sewa,
                'status_kost' => $request->status_kost
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
                    'harga_sewa' => $kost->harga_sewa,
                    'status_kost' => $kost->status_kost,
                    'pemilik' => $kost->pemilik ? [
                        'id_pengguna' => $kost->pemilik->id_pengguna,
                        'nama' => $kost->pemilik->nama,
                        'nomor_telepon' => $kost->pemilik->nomor_telepon,
                    ] : null,
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
            $user = Auth::user();
            $kost = Kost::with(['pemilik'])->findOrFail($id);
            
            // Cek akses berdasarkan role
            if ($user->role === 'pemilik_kost' && $kost->id_pemilik !== $user->id_pengguna) {
                return response()->json(['message' => 'Anda tidak memiliki akses untuk melihat kost ini'], 403);
            }
            
            if ($user->role === 'penyewa' && $kost->status_kost !== 'tersedia') {
                return response()->json(['message' => 'Kost tidak ditemukan'], 404);
            }

            return response()->json([
                'message' => 'Detail kost berhasil diambil',
                'kost' => [
                    'id_kost' => $kost->id_kost,
                    'nama_kost' => $kost->nama_kost,
                    'alamat' => $kost->alamat,
                    'deskripsi' => $kost->deskripsi,
                    'foto_utama' => $kost->foto_utama ? url('storage/' . $kost->foto_utama) : null,
                    'harga_sewa' => $kost->harga_sewa,
                    'status_kost' => $kost->status_kost,
                    'pemilik' => $kost->pemilik ? [
                        'id_pengguna' => $kost->pemilik->id_pengguna,
                        'nama' => $kost->pemilik->nama,
                        'nomor_telepon' => $kost->pemilik->nomor_telepon,
                    ] : null,
                    'created_at' => $kost->created_at,
                    'updated_at' => $kost->updated_at
                ]
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

            // Cek akses berdasarkan role
            if ($user->role === 'pemilik_kost' && $kost->id_pemilik !== $user->id_pengguna) {
                return response()->json(['message' => 'Anda tidak memiliki akses untuk mengubah kost ini'], 403);
            }
            if ($user->role !== 'admin' && $user->role !== 'pemilik_kost') {
                return response()->json(['message' => 'Anda tidak memiliki akses untuk mengubah kost'], 403);
            }

            $validator = Validator::make($request->all(), [
                'nama_kost' => 'required|string|max:100',
                'alamat' => 'required|string',
                'deskripsi' => 'nullable|string',
                'fasilitas' => 'nullable|string',
                'foto_utama' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'harga_sewa' => 'required|numeric|min:0',
                'status_kost' => 'required|in:tersedia,terbooking,ditutup'
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
                'harga_sewa' => $request->harga_sewa,
                'status_kost' => $request->status_kost
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

            // Cek akses berdasarkan role
            if ($user->role === 'pemilik_kost' && $kost->id_pemilik !== $user->id_pengguna) {
                return response()->json(['message' => 'Anda tidak memiliki akses untuk menghapus kost ini'], 403);
            }
            if ($user->role !== 'admin' && $user->role !== 'pemilik_kost') {
                return response()->json(['message' => 'Anda tidak memiliki akses untuk menghapus kost'], 403);
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
            $query = Kost::query()->where('status_kost', 'tersedia');

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
                $query->where('harga_sewa', '>=', $request->harga_min);
            }

            // Filter berdasarkan harga maksimum
            if ($request->has('harga_max')) {
                $query->where('harga_sewa', '<=', $request->harga_max);
            }

            // Filter berdasarkan fasilitas
            if ($request->has('fasilitas')) {
                $fasilitas = $request->fasilitas;
                $query->where('fasilitas', 'like', "%{$fasilitas}%");
            }

            // Load relasi yang diperlukan
            $kosts = $query->with(['pemilik'])->get();

            return response()->json([
                'message' => 'Hasil pencarian kost',
                'total' => $kosts->count(),
                'kost' => $kosts
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat mencari kost',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Endpoint untuk menampilkan semua kost tanpa filter
    public function allKosts()
    {
        $kosts = Kost::all();
        if ($kosts->isEmpty()) {
            return response()->json([
                'message' => 'Kost belum ada',
                'kost' => []
            ]);
        }
        return response()->json([
            'message' => 'Semua data kost berhasil diambil',
            'kost' => $kosts
        ]);
    }
} 