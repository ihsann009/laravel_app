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
        if ($user->role === 'pemilik_kost') {
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
        if ($user->role !== 'pemilik_kost') {
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
                // Simpan file ke folder public/kost
                $fotoPath = $foto->hashName();
                $foto->move(public_path('kost'), $fotoPath);
                // Simpan path relatif ke database
                $fotoPath = 'kost/' . $fotoPath;
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

            // Transform URL foto
            if ($kost->foto_utama) {
                // Pastikan path foto tidak mengandung URL lengkap
                $cleanPath = $kost->foto_utama;
                if (strpos($cleanPath, 'http://') === 0 || strpos($cleanPath, 'https://') === 0) {
                    // Jika sudah URL lengkap, gunakan as is
                    $kost->foto_utama = $cleanPath;
                } else {
                    // Jika path relatif, tambahkan base URL
                    $kost->foto_utama = config('app.url') . '/' . ltrim($cleanPath, '/');
                }
            }

            return response()->json([
                'message' => 'Kost berhasil ditambahkan',
                'kost' => $kost
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
            $kost = Kost::with(['pemilik'])->findOrFail($id);
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

            // Cek akses berdasarkan role
            if ($user->role !== 'pemilik_kost' || $kost->id_pemilik !== $user->id_pengguna) {
                return response()->json(['message' => 'Anda tidak memiliki akses untuk mengubah kost ini'], 403);
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
                $fotoPath = $foto->hashName();
                $foto->store('kost', 'public');
                $kost->foto_utama = $fotoPath ? 'kost/' . $fotoPath : null;
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
                'kost' => $kost->load(['pemilik'])
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
            if ($user->role !== 'pemilik_kost' || $kost->id_pemilik !== $user->id_pengguna) {
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
            $query = Kost::query()->where('status_kost', 'tersedia');

            // Filter berdasarkan lokasi (alamat) jika diisi
            if ($request->filled('lokasi')) {
                $lokasi = strtolower($request->lokasi);
                $query->where(function($q) use ($lokasi) {
                    $q->whereRaw('LOWER(alamat) LIKE ?', ["%{$lokasi}%"])
                      ->orWhereRaw('LOWER(nama_kost) LIKE ?', ["%{$lokasi}%"]);
                });
            }

            // Filter berdasarkan nama kost jika diisi
            if ($request->filled('nama_kost')) {
                $nama = strtolower($request->nama_kost);
                $query->whereRaw('LOWER(nama_kost) LIKE ?', ["%{$nama}%"]);
            }

            // Filter berdasarkan range harga jika diisi
            if ($request->filled('harga_min')) {
                $query->where('harga_sewa', '>=', $request->harga_min);
            }
            if ($request->filled('harga_max')) {
                $query->where('harga_sewa', '<=', $request->harga_max);
            }

            // Filter berdasarkan fasilitas jika diisi
            if ($request->filled('fasilitas')) {
                $fasilitas = strtolower($request->fasilitas);
                $query->whereRaw('LOWER(fasilitas) LIKE ?', ["%{$fasilitas}%"]);
            }

            // Sorting berdasarkan harga (opsional)
            if ($request->filled('sort_by')) {
                switch ($request->sort_by) {
                    case 'harga_terendah':
                        $query->orderBy('harga_sewa', 'asc');
                        break;
                    case 'harga_tertinggi':
                        $query->orderBy('harga_sewa', 'desc');
                        break;
                    case 'terbaru':
                        $query->orderBy('created_at', 'desc');
                        break;
                }
            }

            // Load relasi yang diperlukan
            $kosts = $query->with(['pemilik'])->get();

            // Transform data untuk menambahkan URL lengkap
            $kosts->transform(function ($kost) {
                if ($kost->foto_utama) {
                    // Pastikan path foto tidak mengandung URL lengkap
                    $cleanPath = $kost->foto_utama;
                    if (strpos($cleanPath, 'http://') === 0 || strpos($cleanPath, 'https://') === 0) {
                        // Jika sudah URL lengkap, gunakan as is
                        $kost->foto_utama = $cleanPath;
                    } else {
                        // Jika path relatif, tambahkan base URL
                        $kost->foto_utama = config('app.url') . '/' . ltrim($cleanPath, '/');
                    }
                }
                return $kost;
            });

            return response()->json([
                'message' => 'Hasil pencarian kost',
                'total' => $kosts->count(),
                'kost' => $kosts
            ]);

        } catch (\Exception $e) {
            \Log::error('Search Error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
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