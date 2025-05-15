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
            'nomor_telepon' => 'required|string|max:15|regex:/^[0-9]+$/',
            'alamat' => 'nullable|string',
        ];
    }

    protected function getValidationMessages()
    {
        return [
            'nama.required' => 'Nama harus diisi',
            'nama.max' => 'Nama maksimal 100 karakter',
            'email.required' => 'Email harus diisi',
            'email.email' => 'Format email tidak valid',
            'email.max' => 'Email maksimal 100 karakter',
            'email.unique' => 'Email sudah terdaftar',
            'password.required' => 'Password harus diisi',
            'password.min' => 'Password minimal 8 karakter',
            'password.confirmed' => 'Konfirmasi password tidak sesuai',
            'nomor_telepon.required' => 'Nomor telepon harus diisi',
            'nomor_telepon.max' => 'Nomor telepon maksimal 15 karakter',
            'nomor_telepon.regex' => 'Masukkan nomor telepon yang valid',
        ];
    }

    // Registrasi untuk penyewa
    public function registerPenyewa(Request $request)
    {
        $validator = Validator::make($request->all(), $this->baseValidationRules(), $this->getValidationMessages());

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $pengguna = Pengguna::create([
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
        $validator = Validator::make($request->all(), array_merge($this->baseValidationRules(), [
            'alamat' => 'required|string',
        ]), array_merge($this->getValidationMessages(), [
            'alamat.required' => 'Alamat harus diisi',
        ]));

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $pengguna = Pengguna::create([
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
        $validator = Validator::make($request->all(), $this->baseValidationRules(), $this->getValidationMessages());

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $pengguna = Pengguna::create([
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