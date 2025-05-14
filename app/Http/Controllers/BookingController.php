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

class BookingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // Menampilkan semua booking untuk pemilik kost
    public function indexForOwner()
    {
        $user = Auth::user();
        
        if ($user->role !== 'pemilik_kost') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Ambil semua kost milik pemilik
        $kostIds = Kost::where('id_pemilik', $user->id_pengguna)
                      ->pluck('id_kost');

        // Ambil semua booking dari kamar-kamar di kost milik pemilik
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

    // Menampilkan detail booking
    public function show($id)
    {
        $user = Auth::user();
        $booking = Booking::with(['penyewa:id_pengguna,nama,nomor_telepon,email', 'kamar.kost'])
                         ->find($id);

        if (!$booking) {
            return response()->json(['message' => 'Booking tidak ditemukan'], 404);
        }

        // Cek apakah user adalah pemilik kost
        if ($user->role === 'pemilik_kost') {
            if ($booking->kamar->kost->id_pemilik !== $user->id_pengguna) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }
        // Cek apakah user adalah penyewa yang membuat booking
        else if ($user->role === 'penyewa') {
            if ($booking->id_pengguna !== $user->id_pengguna) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        return response()->json([
            'message' => 'Detail booking berhasil diambil',
            'booking' => $booking
        ]);
    }

    // Update status booking (terima/tolak)
    public function updateStatus(Request $request, $id)
    {
        $user = Auth::user();
        $booking = Booking::with('kamar.kost')->find($id);

        if (!$booking) {
            return response()->json(['message' => 'Booking tidak ditemukan'], 404);
        }

        // Cek kepemilikan kost
        if ($booking->kamar->kost->id_pemilik !== $user->id_pengguna) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'status' => 'required|in:diterima,ditolak',
            'catatan' => 'nullable|string'
        ]);

        $booking->update([
            'status' => $request->status,
            'catatan' => $request->catatan
        ]);

        // Jika booking diterima, update status kamar menjadi terisi
        if ($request->status === 'diterima') {
            $booking->kamar->update(['status' => 'terisi']);
        }

        return response()->json([
            'message' => 'Status booking berhasil diupdate',
            'booking' => $booking
        ]);
    }

    // Menampilkan semua booking untuk penyewa yang sedang login
    public function indexForPenyewa()
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'message' => 'User tidak terautentikasi'
                ], 401);
            }

            if ($user->role !== 'penyewa') {
                return response()->json([
                    'message' => 'Unauthorized - Role bukan penyewa'
                ], 403);
            }

            // Cek data booking langsung dari database
            $rawQuery = DB::select("SELECT * FROM booking WHERE id_pengguna = ?", [$user->id_pengguna]);
            
            if (empty($rawQuery)) {
                return response()->json([
                    'message' => 'Data booking berhasil diambil',
                    'bookings' => []
                ]);
            }

            // Jika ada data di database, ambil dengan Eloquent
            $bookings = Booking::with(['kamar.kost'])
                        ->where('id_pengguna', $user->id_pengguna)
                        ->orderBy('created_at', 'desc')
                        ->get();

            return response()->json([
                'message' => 'Data booking berhasil diambil',
                'debug_info' => [
                    'user_id' => $user->id_pengguna,
                    'raw_count' => count($rawQuery),
                    'eloquent_count' => $bookings->count(),
                    'booking_ids' => $bookings->pluck('id_booking')
                ],
                'bookings' => $bookings->map(function ($booking) {
                    return [
                        'id_booking' => $booking->id_booking,
                        'tanggal_mulai' => $booking->tanggal_mulai,
                        'tanggal_selesai' => $booking->tanggal_selesai,
                        'status' => $booking->status,
                        'catatan' => $booking->catatan,
                        'kamar' => [
                            'id_kamar' => $booking->kamar->id_kamar,
                            'nomor_kamar' => $booking->kamar->nomor_kamar,
                            'ukuran_kamar' => $booking->kamar->ukuran_kamar,
                            'harga_per_bulan' => $booking->kamar->harga_per_bulan,
                            'kost' => [
                                'id_kost' => $booking->kamar->kost->id_kost,
                                'nama_kost' => $booking->kamar->kost->nama_kost,
                                'alamat' => $booking->kamar->kost->alamat
                            ]
                        ],
                        'created_at' => $booking->created_at,
                        'updated_at' => $booking->updated_at
                    ];
                })
            ]);

        } catch (\Exception $e) {
            Log::error('Error retrieving bookings', [
                'user_id' => $user->id_pengguna ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil data booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Membuat booking baru (untuk penyewa)
    public function store(Request $request)
    {
        $user = Auth::user();
        
        if ($user->role !== 'penyewa') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'id_kost' => 'required|exists:kost,id_kost',
            'id_kamar' => [
                'required',
                'exists:kamar,id_kamar',
                function ($attribute, $value, $fail) use ($request) {
                    // Validasi bahwa kamar memang milik kost yang dipilih
                    $kamar = Kamar::where('id_kamar', $value)
                        ->where('id_kost', $request->id_kost)
                        ->first();
                    
                    if (!$kamar) {
                        $fail('Kamar tidak ditemukan di kost yang dipilih.');
                    }
                },
            ],
            'tanggal_mulai' => 'required|date|after:today',
            'tanggal_selesai' => 'required|date|after:tanggal_mulai',
            'catatan' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // Cek apakah kamar tersedia
        $kamar = Kamar::with('kost')->find($request->id_kamar);
        
        // Validasi status kost
        if (!$kamar->kost->status_aktif) {
            return response()->json([
                'message' => 'Kost sedang tidak aktif untuk dibooking'
            ], 422);
        }

        // Validasi status kamar
        if ($kamar->status !== 'tersedia') {
            return response()->json([
                'message' => 'Kamar tidak tersedia untuk dibooking',
                'status_kamar' => $kamar->status
            ], 422);
        }

        // Cek apakah ada booking yang overlap
        $existingBooking = Booking::where('id_kamar', $request->id_kamar)
            ->where('status', 'diterima')
            ->where(function($query) use ($request) {
                $query->whereBetween('tanggal_mulai', [$request->tanggal_mulai, $request->tanggal_selesai])
                    ->orWhereBetween('tanggal_selesai', [$request->tanggal_mulai, $request->tanggal_selesai])
                    ->orWhere(function($q) use ($request) {
                        $q->where('tanggal_mulai', '<=', $request->tanggal_mulai)
                          ->where('tanggal_selesai', '>=', $request->tanggal_selesai);
                    });
            })
            ->first();

        if ($existingBooking) {
            return response()->json([
                'message' => 'Kamar sudah dibooking untuk periode tersebut',
                'periode_terisi' => [
                    'mulai' => $existingBooking->tanggal_mulai,
                    'selesai' => $existingBooking->tanggal_selesai
                ]
            ], 422);
        }

        try {
            $booking = Booking::create([
                'id_pengguna' => $user->id_pengguna,
                'id_kamar' => $request->id_kamar,
                'tanggal_mulai' => $request->tanggal_mulai,
                'tanggal_selesai' => $request->tanggal_selesai,
                'status' => 'pending',
                'catatan' => $request->catatan
            ]);

            // Load relations dengan informasi lengkap
            $booking->load([
                'kamar' => function($query) {
                    $query->select('id_kamar', 'id_kost', 'nomor_kamar', 'harga_per_bulan', 'ukuran_kamar', 'status');
                },
                'kamar.kost' => function($query) {
                    $query->select('id_kost', 'nama_kost', 'alamat');
                },
                'penyewa' => function($query) {
                    $query->select('id_pengguna', 'nama', 'nomor_telepon', 'email');
                }
            ]);

            // Format response yang lebih informatif
            return response()->json([
                'message' => 'Booking berhasil dibuat',
                'booking' => [
                    'id_booking' => $booking->id_booking,
                    'status_booking' => $booking->status,
                    'periode_sewa' => [
                        'mulai' => $booking->tanggal_mulai,
                        'selesai' => $booking->tanggal_selesai
                    ],
                    'informasi_kamar' => [
                        'nomor_kamar' => $booking->kamar->nomor_kamar,
                        'ukuran' => $booking->kamar->ukuran_kamar . ' m²',
                        'harga_per_bulan' => number_format($booking->kamar->harga_per_bulan, 0, ',', '.')
                    ],
                    'informasi_kost' => [
                        'nama_kost' => $booking->kamar->kost->nama_kost,
                        'alamat' => $booking->kamar->kost->alamat
                    ],
                    'informasi_penyewa' => [
                        'nama' => $booking->penyewa->nama,
                        'nomor_telepon' => $booking->penyewa->nomor_telepon,
                        'email' => $booking->penyewa->email
                    ],
                    'catatan' => $booking->catatan,
                    'created_at' => $booking->created_at
                ]
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating booking: ' . $e->getMessage());
            return response()->json([
                'message' => 'Gagal membuat booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Mencari booking berdasarkan lokasi
    public function searchByLocation(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'message' => 'User tidak terautentikasi'
                ], 401);
            }

            $request->validate([
                'lokasi' => 'required|string|min:3'
            ]);

            $searchTerm = $request->lokasi;

            // Query untuk mencari booking berdasarkan lokasi kost
            $bookings = Booking::with(['kamar.kost'])
                ->whereHas('kamar.kost', function($query) use ($searchTerm) {
                    $query->where('alamat', 'like', '%' . $searchTerm . '%')
                        ->orWhere('nama_kost', 'like', '%' . $searchTerm . '%');
                });

            // Jika user adalah penyewa, hanya tampilkan booking miliknya
            if ($user->role === 'penyewa') {
                $bookings->where('id_pengguna', $user->id_pengguna);
            }
            // Jika user adalah pemilik kost, hanya tampilkan booking di kostnya
            else if ($user->role === 'pemilik_kost') {
                $kostIds = Kost::where('id_pemilik', $user->id_pengguna)->pluck('id_kost');
                $bookings->whereHas('kamar', function($query) use ($kostIds) {
                    $query->whereIn('id_kost', $kostIds);
                });
            }

            $bookings = $bookings->orderBy('created_at', 'desc')->get();

            return response()->json([
                'message' => 'Data booking berhasil dicari',
                'search_term' => $searchTerm,
                'total_found' => $bookings->count(),
                'bookings' => $bookings->map(function ($booking) {
                    return [
                        'id_booking' => $booking->id_booking,
                        'tanggal_mulai' => $booking->tanggal_mulai,
                        'tanggal_selesai' => $booking->tanggal_selesai,
                        'status' => $booking->status,
                        'catatan' => $booking->catatan,
                        'kamar' => [
                            'nomor_kamar' => $booking->kamar->nomor_kamar,
                            'ukuran_kamar' => $booking->kamar->ukuran_kamar,
                            'harga_per_bulan' => $booking->kamar->harga_per_bulan,
                            'kost' => [
                                'nama_kost' => $booking->kamar->kost->nama_kost,
                                'alamat' => $booking->kamar->kost->alamat
                            ]
                        ],
                        'created_at' => $booking->created_at,
                        'updated_at' => $booking->updated_at
                    ];
                })
            ]);

        } catch (\Exception $e) {
            Log::error('Error searching bookings by location', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Terjadi kesalahan saat mencari booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 