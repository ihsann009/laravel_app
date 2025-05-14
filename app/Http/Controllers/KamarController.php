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
    public function index($kostId)
    {
        Log::info('Accessing kamar index with kostId: ' . $kostId);
        Log::info('KostId type: ' . gettype($kostId));
        
        // Convert kostId to integer if it's a string
        $kostId = is_string($kostId) ? intval($kostId) : $kostId;
        
        // Enable query logging
        DB::enableQueryLog();
        
        $kost = Kost::find($kostId);
        
        // Log the SQL query
        Log::info('SQL Query:', ['queries' => DB::getQueryLog()]);
        Log::info('Kost query result:', ['kost' => $kost]);
        
        if (!$kost) {
            Log::warning('Kost not found with ID: ' . $kostId);
            return response()->json([
                'message' => 'Kost tidak ditemukan',
                'debug_info' => [
                    'kost_id' => $kostId,
                    'kost_id_type' => gettype($kostId),
                    'queries' => DB::getQueryLog()
                ]
            ], 404);
        }

        // Reset query log before next query
        DB::flushQueryLog();
        
        $kamar = Kamar::where('id_kost', $kostId)
                    ->with(['fasilitas'])
                    ->get();
                    
        Log::info('Kamar query:', ['queries' => DB::getQueryLog()]);
        Log::info('Found kamar:', ['count' => $kamar->count()]);

        return response()->json([
            'message' => 'Data kamar berhasil diambil',
            'kamar' => $kamar
        ]);
    }

    // Menambah kamar baru
    public function store(Request $request, $kostId)
    {
        Log::info('Creating new kamar for kostId: ' . $kostId);
        Log::info('KostId type: ' . gettype($kostId));
        
        // Convert kostId to integer if it's a string
        $kostId = is_string($kostId) ? intval($kostId) : $kostId;
        
        $user = Auth::user();
        Log::info('User data:', ['user' => $user->toArray()]);
        
        // Enable query logging
        DB::enableQueryLog();
        
        $kost = Kost::find($kostId);
        Log::info('SQL Query:', ['queries' => DB::getQueryLog()]);
        Log::info('Kost data:', ['kost' => $kost]);
        
        if (!$kost) {
            Log::warning('Kost not found with ID: ' . $kostId);
            return response()->json([
                'message' => 'Kost tidak ditemukan',
                'debug_info' => [
                    'kost_id' => $kostId,
                    'kost_id_type' => gettype($kostId),
                    'user_id' => $user->id_pengguna,
                    'queries' => DB::getQueryLog()
                ]
            ], 404);
        }

        // Cek kepemilikan kost
        Log::info('Checking ownership:', [
            'kost_owner_id' => $kost->id_pemilik,
            'user_id' => $user->id_pengguna,
            'is_owner' => $kost->id_pemilik === $user->id_pengguna
        ]);
        
        if ($kost->id_pemilik !== $user->id_pengguna) {
            Log::warning('Unauthorized access attempt', [
                'kost_owner_id' => $kost->id_pemilik,
                'user_id' => $user->id_pengguna
            ]);
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'nomor_kamar' => 'required|string|max:20',
            'harga_per_bulan' => 'required|numeric|min:0',
            'ukuran_kamar' => 'required|numeric|min:0',
            'deskripsi' => 'nullable|string',
            'foto_kamar.*' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'fasilitas_kamar' => 'nullable|json',
            'fasilitas_umum' => 'nullable|json'
        ]);

        if ($validator->fails()) {
            Log::warning('Validation failed:', ['errors' => $validator->errors()->toArray()]);
            return response()->json($validator->errors(), 422);
        }

        // Reset query log for the next operation
        DB::flushQueryLog();

        try {
            // Handle multiple foto kamar
            $fotoKamar = [];
            if ($request->hasFile('foto_kamar')) {
                foreach ($request->file('foto_kamar') as $foto) {
                    $path = $foto->store('kamar', 'public');
                    $fotoKamar[] = $path;
                }
            }

            $kamar = Kamar::create([
                'id_kost' => $kostId,
                'nomor_kamar' => $request->nomor_kamar,
                'harga_per_bulan' => $request->harga_per_bulan,
                'ukuran_kamar' => $request->ukuran_kamar,
                'status' => 'tersedia',
                'deskripsi' => $request->deskripsi,
                'foto_kamar' => $fotoKamar,
                'fasilitas_kamar' => $request->fasilitas_kamar,
                'fasilitas_umum' => $request->fasilitas_umum
            ]);

            Log::info('Kamar created successfully:', ['kamar' => $kamar->toArray()]);

            return response()->json([
                'message' => 'Kamar berhasil ditambahkan',
                'kamar' => $kamar
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating kamar:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Gagal menambahkan kamar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Menampilkan detail kamar
    public function show($kostId, $kamarId)
    {
        $kamar = Kamar::with(['kost'])
                    ->where('id_kost', $kostId)
                    ->find($kamarId);

        if (!$kamar) {
            return response()->json(['message' => 'Kamar tidak ditemukan'], 404);
        }

        return response()->json([
            'message' => 'Detail kamar berhasil diambil',
            'kamar' => $kamar
        ]);
    }

    // Mengupdate data kamar
    public function update(Request $request, $kostId, $kamarId)
    {
        $user = Auth::user();
        $kamar = Kamar::where('id_kost', $kostId)->find($kamarId);

        if (!$kamar) {
            return response()->json(['message' => 'Kamar tidak ditemukan'], 404);
        }

        // Cek kepemilikan kost
        if ($kamar->kost->id_pemilik !== $user->id_pengguna) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'nomor_kamar' => 'required|string|max:20',
            'harga_per_bulan' => 'required|numeric|min:0',
            'ukuran_kamar' => 'required|numeric|min:0',
            'status' => 'required|in:tersedia,terisi,maintenance',
            'deskripsi' => 'nullable|string',
            'foto_kamar.*' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'fasilitas_kamar' => 'nullable|json',
            'fasilitas_umum' => 'nullable|json'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Handle foto baru jika ada
        if ($request->hasFile('foto_kamar')) {
            // Hapus foto lama
            if ($kamar->foto_kamar) {
                foreach ($kamar->foto_kamar as $foto) {
                    Storage::disk('public')->delete($foto);
                }
            }
            
            // Upload foto baru
            $fotoKamar = [];
            foreach ($request->file('foto_kamar') as $foto) {
                $path = $foto->store('kamar', 'public');
                $fotoKamar[] = $path;
            }
            $kamar->foto_kamar = $fotoKamar;
        }

        $kamar->update([
            'nomor_kamar' => $request->nomor_kamar,
            'harga_per_bulan' => $request->harga_per_bulan,
            'ukuran_kamar' => $request->ukuran_kamar,
            'status' => $request->status,
            'deskripsi' => $request->deskripsi,
            'fasilitas_kamar' => $request->fasilitas_kamar,
            'fasilitas_umum' => $request->fasilitas_umum
        ]);

        return response()->json([
            'message' => 'Data kamar berhasil diupdate',
            'kamar' => $kamar
        ]);
    }

    // Menghapus kamar
    public function destroy($kostId, $kamarId)
    {
        $user = Auth::user();
        $kamar = Kamar::where('id_kost', $kostId)->find($kamarId);

        if (!$kamar) {
            return response()->json(['message' => 'Kamar tidak ditemukan'], 404);
        }

        // Cek kepemilikan kost
        if ($kamar->kost->id_pemilik !== $user->id_pengguna) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Hapus foto-foto kamar
        if ($kamar->foto_kamar) {
            foreach ($kamar->foto_kamar as $foto) {
                Storage::disk('public')->delete($foto);
            }
        }

        $kamar->delete();

        return response()->json([
            'message' => 'Kamar berhasil dihapus'
        ]);
    }
} 