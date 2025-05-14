<?php

namespace App\Http\Controllers;

use App\Models\Kost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class KostController extends Controller
{
    public function __construct()
    {
        // Middleware untuk memastikan user sudah login
        $this->middleware('auth:sanctum');
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
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'nama_kost' => 'required|string|max:100',
            'alamat' => 'required|string',
            'deskripsi' => 'nullable|string',
            'foto_utama' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Handle upload foto jika ada
        $fotoPath = null;
        if ($request->hasFile('foto_utama')) {
            $foto = $request->file('foto_utama');
            $fotoPath = $foto->store('kost', 'public');
        }

        $kost = Kost::create([
            'id_pemilik' => $user->id_pengguna,
            'nama_kost' => $request->nama_kost,
            'alamat' => $request->alamat,
            'deskripsi' => $request->deskripsi,
            'foto_utama' => $fotoPath,
            'status_aktif' => true
        ]);

        return response()->json([
            'message' => 'Kost berhasil ditambahkan',
            'kost' => $kost
        ], 201);
    }

    // Menampilkan detail kost
    public function show($id)
    {
        $kost = Kost::with(['kamar', 'pemilik'])->find($id);
        
        if (!$kost) {
            return response()->json(['message' => 'Kost tidak ditemukan'], 404);
        }

        // Jika bukan pemilik dan kost tidak aktif, jangan tampilkan
        if (Auth::user()->role !== 'pemilik_kost' && !$kost->status_aktif) {
            return response()->json(['message' => 'Kost tidak ditemukan'], 404);
        }

        return response()->json([
            'message' => 'Detail kost berhasil diambil',
            'kost' => $kost
        ]);
    }

    // Mengupdate data kost
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $kost = Kost::find($id);

        if (!$kost) {
            return response()->json(['message' => 'Kost tidak ditemukan'], 404);
        }

        // Cek kepemilikan kost
        if ($kost->id_pemilik !== $user->id_pengguna) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'nama_kost' => 'required|string|max:100',
            'alamat' => 'required|string',
            'deskripsi' => 'nullable|string',
            'foto_utama' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'status_aktif' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
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
            'status_aktif' => $request->status_aktif ?? $kost->status_aktif
        ]);

        return response()->json([
            'message' => 'Data kost berhasil diupdate',
            'kost' => $kost
        ]);
    }

    // Menghapus data kost
    public function destroy($id)
    {
        $user = Auth::user();
        $kost = Kost::find($id);

        if (!$kost) {
            return response()->json(['message' => 'Kost tidak ditemukan'], 404);
        }

        // Cek kepemilikan kost
        if ($kost->id_pemilik !== $user->id_pengguna) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Hapus foto jika ada
        if ($kost->foto_utama) {
            Storage::disk('public')->delete($kost->foto_utama);
        }

        $kost->delete();

        return response()->json([
            'message' => 'Kost berhasil dihapus'
        ]);
    }

    // Menambahkan method untuk pencarian kost
    public function search(Request $request)
    {
        // Validasi input pencarian
        $validator = Validator::make($request->all(), [
            'alamat' => 'nullable|string',
            'harga_min' => 'nullable|numeric|min:0',
            'harga_max' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Format pencarian tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validasi jika harga_min lebih besar dari harga_max
        if ($request->has('harga_min') && $request->has('harga_max')) {
            if ($request->harga_min > $request->harga_max) {
                return response()->json([
                    'message' => 'Rentang harga tidak valid',
                    'error' => 'Harga minimum tidak boleh lebih besar dari harga maksimum'
                ], 422);
            }
        }

        $query = Kost::where('status_aktif', true);

        // Filter berdasarkan alamat
        if ($request->has('alamat')) {
            $query->where(function($q) use ($request) {
                $q->where('alamat', 'like', '%' . $request->alamat . '%')
                  ->orWhere('nama_kost', 'like', '%' . $request->alamat . '%');
            });
        }

        // Filter berdasarkan ketersediaan kamar dan range harga
        $query->whereHas('kamar', function($q) use ($request) {
            $q->where('status', 'tersedia');
            
            // Terapkan filter harga jika ada
            if ($request->has('harga_min')) {
                $q->where('harga_per_bulan', '>=', $request->harga_min);
            }
            if ($request->has('harga_max')) {
                $q->where('harga_per_bulan', '<=', $request->harga_max);
            }
        }, '>=', 1); // Pastikan ada minimal 1 kamar yang memenuhi kriteria

        // Pastikan tidak ada kamar di luar range harga yang ditentukan
        if ($request->has('harga_min') || $request->has('harga_max')) {
            $query->whereDoesntHave('kamar', function($q) use ($request) {
                if ($request->has('harga_min')) {
                    $q->where('harga_per_bulan', '<', $request->harga_min);
                }
                if ($request->has('harga_max')) {
                    $q->where('harga_per_bulan', '>', $request->harga_max);
                }
            });
        }

        // Load kost dengan kamar yang memenuhi kriteria
        $kost = $query->with(['kamar' => function($q) use ($request) {
            $q->where('status', 'tersedia');
            if ($request->has('harga_min')) {
                $q->where('harga_per_bulan', '>=', $request->harga_min);
            }
            if ($request->has('harga_max')) {
                $q->where('harga_per_bulan', '<=', $request->harga_max);
            }
        }, 'pemilik:id_pengguna,nama,nomor_telepon'])
        ->get();

        // Jika tidak ada hasil pencarian
        if ($kost->isEmpty()) {
            $message = 'Tidak ditemukan kost';
            $details = [];

            if ($request->has('alamat')) {
                $details[] = "di lokasi '{$request->alamat}'";
            }
            if ($request->has('harga_min') && $request->has('harga_max')) {
                $details[] = "dengan rentang harga Rp " . number_format($request->harga_min, 0, ',', '.') . 
                           " - Rp " . number_format($request->harga_max, 0, ',', '.');
            } elseif ($request->has('harga_min')) {
                $details[] = "dengan harga minimal Rp " . number_format($request->harga_min, 0, ',', '.');
            } elseif ($request->has('harga_max')) {
                $details[] = "dengan harga maksimal Rp " . number_format($request->harga_max, 0, ',', '.');
            }

            if (!empty($details)) {
                $message .= ' ' . implode(' ', $details);
            }

            return response()->json([
                'message' => $message,
                'kost' => [],
                'filters_applied' => [
                    'alamat' => $request->alamat ?? null,
                    'harga_min' => $request->harga_min ?? null,
                    'harga_max' => $request->harga_max ?? null
                ]
            ]);
        }

        return response()->json([
            'message' => 'Data kost berhasil diambil',
            'total_found' => $kost->count(),
            'filters_applied' => [
                'alamat' => $request->alamat ?? null,
                'harga_min' => $request->has('harga_min') ? (int)$request->harga_min : null,
                'harga_max' => $request->has('harga_max') ? (int)$request->harga_max : null
            ],
            'kost' => $kost
        ]);
    }
} 