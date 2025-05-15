<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Kost;
use App\Models\Kamar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BookingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index()
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Anda belum login'
            ], 401);
        }

        if ($user->role !== 'penyewa' && $user->role !== 'admin') {
            return response()->json([
                'message' => 'Anda tidak memiliki akses untuk melihat daftar booking ini'
            ], 403);
        }

        $query = Booking::query();
        
        if ($user->role === 'penyewa') {
            $query->where('id_penyewa', $user->id_pengguna);
        }

        $bookings = $query->with(['kost', 'penyewa'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Data booking berhasil diambil',
            'bookings' => $bookings
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'penyewa' && $user->role !== 'admin') {
            return response()->json(['message' => 'Anda tidak memiliki akses untuk membuat booking'], 403);
        }

        $validator = Validator::make($request->all(), [
            'id_kost' => 'required|integer|exists:kost,id_kost',
            'tanggal_mulai' => 'required|date|after:today',
            'tanggal_selesai' => 'required|date|after:tanggal_mulai',
            'catatan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $kost = \App\Models\Kost::findOrFail($request->id_kost);
            if ($kost->status_kost !== 'tersedia') {
                return response()->json([
                    'message' => 'Kost tidak tersedia untuk dibooking'
                ], 422);
            }

            // Hitung total harga (misal per bulan)
            $durasi = strtotime($request->tanggal_selesai) - strtotime($request->tanggal_mulai);
            $bulan = ceil($durasi / (30 * 24 * 60 * 60));
            $totalHarga = $kost->harga_sewa * $bulan;

            $lastId = DB::table('booking')->max('id_booking');
            $newId = $lastId ? $lastId + 1 : 1;

            $booking = \App\Models\Booking::create([
                'id_booking' => $newId,
                'id_kost' => $request->id_kost,
                'id_penyewa' => $user->role === 'admin' ? $request->id_penyewa : $user->id_pengguna,
                'tanggal_mulai' => $request->tanggal_mulai,
                'tanggal_selesai' => $request->tanggal_selesai,
                'status' => 'pending',
                'total_harga' => $totalHarga,
                'catatan' => $request->catatan
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Booking berhasil dibuat',
                'booking' => $booking
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Gagal membuat booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $booking = Booking::with(['kost', 'penyewa'])->findOrFail($id);
            $user = Auth::user();

            if ($user->role === 'penyewa' && $booking->id_penyewa !== $user->id_pengguna) {
                return response()->json(['message' => 'Anda tidak memiliki akses untuk melihat booking ini'], 403);
            } elseif ($user->role === 'pemilik_kost' && $booking->kost->id_pemilik !== $user->id_pengguna) {
                return response()->json(['message' => 'Anda tidak memiliki akses untuk melihat booking ini'], 403);
            }

            return response()->json([
                'message' => 'Detail booking berhasil diambil',
                'booking' => $booking
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Booking tidak ditemukan'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $booking = Booking::findOrFail($id);
            $user = Auth::user();

            if ($user->role === 'penyewa' && $booking->id_penyewa !== $user->id_pengguna) {
                return response()->json(['message' => 'Anda tidak memiliki akses untuk mengubah booking ini'], 403);
            } elseif ($user->role === 'pemilik_kost' && $booking->kamar->kost->id_pemilik !== $user->id_pengguna) {
                return response()->json(['message' => 'Anda tidak memiliki akses untuk mengubah booking ini'], 403);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:pending,diterima,ditolak,batal',
                'catatan' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($request->status === 'diterima' && $booking->status === 'pending') {
                $booking->kamar->update(['status' => 'terisi']);
            }

            $booking->update([
                'status' => $request->status,
                'catatan' => $request->catatan
            ]);

            return response()->json([
                'message' => 'Status booking berhasil diperbarui',
                'booking' => $booking
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Booking tidak ditemukan'], 404);
        }
    }

    public function destroy($id)
    {
        try {
            $booking = Booking::findOrFail($id);
            $user = Auth::user();

            if ($user->role === 'penyewa' && $booking->id_penyewa !== $user->id_pengguna) {
                return response()->json(['message' => 'Anda tidak memiliki akses untuk menghapus booking ini'], 403);
            } elseif ($user->role === 'pemilik_kost' && $booking->kamar->kost->id_pemilik !== $user->id_pengguna) {
                return response()->json(['message' => 'Anda tidak memiliki akses untuk menghapus booking ini'], 403);
            }

            if ($booking->status === 'diterima') {
                $booking->kamar->update(['status' => 'tersedia']);
            }

            $booking->delete();

            return response()->json([
                'message' => 'Booking berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Booking tidak ditemukan'], 404);
        }
    }

    public function indexForOwner()
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'message' => 'Anda belum login'
                ], 401);
            }
            
            if ($user->role !== 'pemilik_kost' && $user->role !== 'admin') {
                return response()->json([
                    'message' => 'Anda tidak memiliki akses untuk melihat daftar booking ini'
                ], 403);
            }

            $query = Booking::query();

            if ($user->role === 'pemilik_kost') {
                $kostIds = Kost::where('id_pemilik', $user->id_pengguna)
                              ->pluck('id_kost');
                $query->whereIn('id_kost', $kostIds);
            }

            $bookings = $query->with(['kost', 'penyewa:id_pengguna,nama,nomor_telepon'])
                        ->orderBy('created_at', 'desc')
                        ->get();

            return response()->json([
                'message' => 'Data booking berhasil diambil',
                'bookings' => $bookings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil data booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function indexForPenyewa()
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'message' => 'Anda belum login'
                ], 401);
            }

            if ($user->role !== 'penyewa' && $user->role !== 'admin') {
                return response()->json([
                    'message' => 'Anda tidak memiliki akses untuk melihat daftar booking ini'
                ], 403);
            }

            $query = Booking::query();

            if ($user->role === 'penyewa') {
                $query->where('id_penyewa', $user->id_pengguna);
            }

            $bookings = $query->with(['kost', 'penyewa'])
                        ->orderBy('created_at', 'desc')
                        ->get();

            return response()->json([
                'message' => 'Data booking berhasil diambil',
                'bookings' => $bookings
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil data booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function searchByLocation(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'message' => 'Anda belum login'
                ], 401);
            }

            $request->validate([
                'lokasi' => 'required|string|min:3'
            ]);

            $searchTerm = $request->lokasi;

            $query = Booking::with(['kost'])
                ->whereHas('kost', function($query) use ($searchTerm) {
                    $query->where('alamat', 'like', '%' . $searchTerm . '%')
                        ->orWhere('nama_kost', 'like', '%' . $searchTerm . '%');
                });

            if ($user->role === 'penyewa') {
                $query->where('id_penyewa', $user->id_pengguna);
            }
            else if ($user->role === 'pemilik_kost') {
                $kostIds = Kost::where('id_pemilik', $user->id_pengguna)->pluck('id_kost');
                $query->whereIn('id_kost', $kostIds);
            }

            $bookings = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'message' => 'Hasil pencarian booking',
                'bookings' => $bookings
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat mencari booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'message' => 'Anda belum login'
                ], 401);
            }

            if ($user->role !== 'pemilik_kost' && $user->role !== 'admin') {
                return response()->json([
                    'message' => 'Anda tidak memiliki akses untuk mengubah status booking ini'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:diterima,ditolak',
                'catatan' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $booking = Booking::with(['kost'])->findOrFail($id);

            // Cek apakah kost ini milik pemilik_kost (kecuali admin)
            if ($user->role === 'pemilik_kost' && $booking->kost->id_pemilik !== $user->id_pengguna) {
                return response()->json([
                    'message' => 'Anda tidak memiliki akses untuk mengubah status booking ini'
                ], 403);
            }

            // Cek apakah status booking masih pending
            if ($booking->status !== 'pending') {
                return response()->json([
                    'message' => 'Status booking tidak dapat diubah karena sudah ' . $booking->status
                ], 422);
            }

            DB::beginTransaction();

            // Update status booking
            $booking->update([
                'status' => $request->status,
                'catatan' => $request->catatan
            ]);

            // Jika booking diterima, update status kost
            if ($request->status === 'diterima') {
                $booking->kost->update([
                    'status_kost' => 'terbooking'
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Status booking berhasil diperbarui',
                'booking' => $booking->load(['kost', 'penyewa'])
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengubah status booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 