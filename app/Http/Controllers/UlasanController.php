<?php

namespace App\Http\Controllers;

use App\Models\Ulasan;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UlasanController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_kost' => 'required|integer|exists:kost,id_kost',
            'id_booking' => 'required|integer|exists:booking,id_booking',
            'rating' => 'required|integer|min:1|max:5',
            'komentar' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $booking = Booking::where('id_booking', $request->id_booking)
            ->where('id_penyewa', $user->id_pengguna)
            ->where('id_kamar', function($q) use ($request) {
                $q->select('id_kamar')
                  ->from('kamar')
                  ->where('id_kost', $request->id_kost);
            })
            ->first();

        if (!$booking) {
            return response()->json(['message' => 'Anda hanya bisa mengulas kost yang sudah Anda booking'], 403);
        }

        $ulasan = Ulasan::create([
            'id_kost' => $request->id_kost,
            'id_user' => $user->id_pengguna,
            'id_booking' => $request->id_booking,
            'rating' => $request->rating,
            'komentar' => $request->komentar,
        ]);

        return response()->json([
            'message' => 'Ulasan berhasil ditambahkan',
            'ulasan' => $ulasan
        ], 201);
    }
} 