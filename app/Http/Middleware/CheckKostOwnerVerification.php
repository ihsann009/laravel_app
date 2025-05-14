<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckKostOwnerVerification
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user->role === 'pemilik_kost' && !$user->is_verified) {
            return response()->json([
                'message' => 'Akun Anda belum diverifikasi oleh admin. Silakan tunggu verifikasi untuk dapat menambahkan kost.'
            ], 403);
        }

        return $next($request);
    }
} 