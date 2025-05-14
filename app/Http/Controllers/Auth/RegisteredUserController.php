<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'nama' => ['required', 'string', 'max:100'],
                'email' => ['required', 'string', 'lowercase', 'email', 'max:100', 'unique:'.User::class],
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
                'nomor_telepon' => ['required', 'string', 'max:15'],
                'alamat' => ['nullable', 'string'],
            ]);

            // If old fields are present, return error
            if ($request->has('name')) {
                return response()->json([
                    'message' => 'Invalid fields detected',
                    'errors' => [
                        'name' => ['Please use "nama" instead of "name"']
                    ]
                ], 422);
            }

            $user = User::create([
                'nama' => $validated['nama'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'penyewa',
                'nomor_telepon' => $validated['nomor_telepon'],
                'alamat' => $validated['alamat'] ?? null,
            ]);

            event(new Registered($user));

            Auth::login($user);

            // Return success response with user data
            return response()->json([
                'message' => 'Registration successful',
                'user' => [
                    'id_pengguna' => $user->id_pengguna,
                    'nama' => $user->nama,
                    'email' => $user->email,
                    'role' => $user->role,
                    'nomor_telepon' => $user->nomor_telepon,
                    'alamat' => $user->alamat,
                    'tanggal_daftar' => $user->tanggal_daftar
                ]
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }
}
