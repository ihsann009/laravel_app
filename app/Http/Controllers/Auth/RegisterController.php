<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Pengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class RegisterController extends Controller
{
    protected function baseValidationRules()
    {
        return [
            'nama' => 'required|string|max:100',
            'email' => 'required|string|email|max:100|unique:pengguna',
            'password' => 'required|string|min:8|confirmed',
            'nomor_telepon' => 'required|string|max:15',
            'alamat' => 'nullable|string',
        ];
    }

    // Registrasi untuk penyewa
    public function registerPenyewa(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:100',
            'email' => 'required|string|email|max:100|unique:pengguna',
            'password' => 'required|string|min:8|confirmed',
            'nomor_telepon' => 'required|string|max:15',
            'alamat' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Generate ID Pengguna
        $lastId = DB::table('pengguna')->max('id_pengguna');
        $newId = $lastId ? $lastId + 1 : 1;

        $pengguna = Pengguna::create([
            'id_pengguna' => $newId,
            'nama' => $request->nama,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'nomor_telepon' => $request->nomor_telepon,
            'alamat' => $request->alamat,
            'role' => 'penyewa',
        ]);

        return response()->json([
            'message' => 'Registrasi penyewa berhasil, silakan login',
            'user' => $pengguna
        ], 201);
    }

    // Registrasi untuk pemilik kost
    public function registerPemilikKost(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:100',
            'email' => 'required|string|email|max:100|unique:pengguna',
            'password' => 'required|string|min:8|confirmed',
            'nomor_telepon' => 'required|string|max:15',
            'alamat' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Generate ID Pengguna
        $lastId = DB::table('pengguna')->max('id_pengguna');
        $newId = $lastId ? $lastId + 1 : 1;

        $pengguna = Pengguna::create([
            'id_pengguna' => $newId,
            'nama' => $request->nama,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'nomor_telepon' => $request->nomor_telepon,
            'alamat' => $request->alamat,
            'role' => 'pemilik_kost',
            'is_verified' => false,
        ]);

        return response()->json([
            'message' => 'Registrasi pemilik kost berhasil, silakan login setelah akun diverifikasi admin',
            'user' => $pengguna
        ], 201);
    }

    // Registrasi untuk admin (protected by middleware)
    public function registerAdmin(Request $request)
    {
        $rules = array_merge($this->baseValidationRules(), [
            'alamat' => 'required|string',
            'super_secret_key' => 'required|string', // tambahan keamanan
        ]);

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Verifikasi super_secret_key (dalam praktik nyata, gunakan env)
        if ($request->super_secret_key !== 'your-super-secret-key') {
            return response()->json([
                'message' => 'Invalid secret key'
            ], 403);
        }

        // Generate ID Pengguna
        $lastId = DB::table('pengguna')->max('id_pengguna');
        $newId = $lastId ? $lastId + 1 : 1;

        $pengguna = Pengguna::create([
            'id_pengguna' => $newId,
            'nama' => $request->nama,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'nomor_telepon' => $request->nomor_telepon,
            'alamat' => $request->alamat,
            'role' => 'admin',
            'is_verified' => true,
        ]);

        $token = $pengguna->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registrasi admin berhasil',
            'user' => $pengguna,
            'token' => $token
        ], 201);
    }
} 