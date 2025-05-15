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
            ->where('id_kost', $request->id_kost)
            ->first();

        if (!$booking) {
            return response()->json(['message' => 'Anda hanya bisa mengulas kost yang sudah Anda booking'], 403);
        }

        // Batasi user hanya bisa mengulas satu kali per kost
        $sudahUlas = Ulasan::where('id_kost', $request->id_kost)
            ->where('id_user', $user->id_pengguna)
            ->exists();
        if ($sudahUlas) {
            return response()->json([
                'message' => 'Anda sudah pernah mengulas kost ini. Setiap pengguna hanya boleh mengulas satu kali per kost.'
            ], 422);
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