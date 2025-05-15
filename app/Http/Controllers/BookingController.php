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
        $query = Booking::query();

        if ($user->role === 'penyewa') {
            $query->where('id_penyewa', $user->id_pengguna);
        } elseif ($user->role === 'pemilik_kost') {
            $query->whereHas('kamar.kost', function($q) use ($user) {
                $q->where('id_pemilik', $user->id_pengguna);
            });
        }

        $bookings = $query->with(['kamar.kost', 'penyewa'])->get();

        return response()->json([
            'message' => 'Data booking berhasil diambil',
            'bookings' => $bookings
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'penyewa') {
            return response()->json(['message' => 'Anda tidak memiliki akses untuk membuat booking'], 403);
        }

        $validator = Validator::make($request->all(), [
            'id_kamar' => 'required|integer',
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

            $kamar = Kamar::findOrFail($request->id_kamar);
            if ($kamar->status !== 'tersedia') {
                return response()->json([
                    'message' => 'Kamar tidak tersedia untuk dibooking'
                ], 422);
            }

            $existingBooking = Booking::where('id_kamar', $request->id_kamar)
                ->where(function($query) use ($request) {
                    $query->whereBetween('tanggal_mulai', [$request->tanggal_mulai, $request->tanggal_selesai])
                        ->orWhereBetween('tanggal_selesai', [$request->tanggal_mulai, $request->tanggal_selesai])
                        ->orWhere(function($q) use ($request) {
                            $q->where('tanggal_mulai', '<=', $request->tanggal_mulai)
                                ->where('tanggal_selesai', '>=', $request->tanggal_selesai);
                        });
                })
                ->where('status', '!=', 'batal')
                ->first();

            if ($existingBooking) {
                return response()->json([
                    'message' => 'Kamar sudah dibooking untuk periode tersebut'
                ], 422);
            }

            $durasi = strtotime($request->tanggal_selesai) - strtotime($request->tanggal_mulai);
            $bulan = ceil($durasi / (30 * 24 * 60 * 60));
            $totalHarga = $kamar->harga_per_bulan * $bulan;

            $lastId = DB::table('booking')->max('id_booking');
            $newId = $lastId ? $lastId + 1 : 1;

            $booking = Booking::create([
                'id_booking' => $newId,
                'id_kamar' => $request->id_kamar,
                'id_penyewa' => $user->id_pengguna,
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
            $booking = Booking::with(['kamar.kost', 'penyewa'])->findOrFail($id);
            $user = Auth::user();

            if ($user->role === 'penyewa' && $booking->id_penyewa !== $user->id_pengguna) {
                return response()->json(['message' => 'Anda tidak memiliki akses untuk melihat booking ini'], 403);
            } elseif ($user->role === 'pemilik_kost' && $booking->kamar->kost->id_pemilik !== $user->id_pengguna) {
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
        $user = Auth::user();
        
        if ($user->role !== 'pemilik_kost') {
            return response()->json(['message' => 'Anda tidak memiliki akses untuk melihat daftar booking ini'], 403);
        }

        $kostIds = Kost::where('id_pemilik', $user->id_pengguna)
                      ->pluck('id_kost');

        $bookings = Booking::whereHas('kamar', function($query) use ($kostIds) {
                        $query->whereIn('id_kost', $kostIds);
                    })
                    ->with(['penyewa:id_pengguna,nama,nomor_telepon', 'kamar.kost'])
                    ->orderBy('created_at', 'desc')
                    ->get();

        return response()->json([
            'message' => 'Data booking berhasil diambil',
            'bookings' => $bookings
        ]);
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

            if ($user->role !== 'penyewa') {
                return response()->json([
                    'message' => 'Anda tidak memiliki akses untuk melihat daftar booking ini'
                ], 403);
            }

            $rawQuery = DB::select("SELECT * FROM booking WHERE id_pengguna = ?", [$user->id_pengguna]);
            
            if (empty($rawQuery)) {
                return response()->json([
                    'message' => 'Data booking berhasil diambil',
                    'bookings' => []
                ]);
            }

            $bookings = Booking::with(['kamar.kost'])
                        ->where('id_pengguna', $user->id_pengguna)
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

            $bookings = Booking::with(['kamar.kost'])
                ->whereHas('kamar.kost', function($query) use ($searchTerm) {
                    $query->where('alamat', 'like', '%' . $searchTerm . '%')
                        ->orWhere('nama_kost', 'like', '%' . $searchTerm . '%');
                });

            if ($user->role === 'penyewa') {
                $bookings->where('id_pengguna', $user->id_pengguna);
            }
            else if ($user->role === 'pemilik_kost') {
                $kostIds = Kost::where('id_pemilik', $user->id_pengguna)->pluck('id_kost');
                $bookings->whereHas('kamar', function($query) use ($kostIds) {
                    $query->whereIn('id_kost', $kostIds);
                });
            }

            $bookings = $bookings->orderBy('created_at', 'desc')->get();

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
} 