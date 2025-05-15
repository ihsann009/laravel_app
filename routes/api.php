<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\KostController;
use App\Http\Controllers\KamarController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\Admin\VerificationController;
use App\Http\Controllers\UlasanController;
use App\Http\Controllers\UserController;

// Authentication Routes
Route::post('/register', [RegisterController::class, 'registerPenyewa']);
Route::post('/register/pemilik-kost', [RegisterController::class, 'registerPemilikKost']);
Route::post('/login', [LoginController::class, 'login']);
Route::post('/register/admin', [RegisterController::class, 'registerAdmin'])->name('register.admin');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
    return $request->user();
    });
    
    Route::get('/protected-data', function () {
        return response()->json(['message' => 'This is protected data']);
    });

    // Auth
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::get('/me', [LoginController::class, 'me']);

    // Kost Management
    Route::get('/kost', [KostController::class, 'index']); // List kost (semua untuk penyewa, milik sendiri untuk pemilik)
    Route::get('/kost/search', [KostController::class, 'search']);
    Route::post('/kost', [KostController::class, 'store']); // Tambah kost baru
    Route::get('/kost/{id}', [KostController::class, 'show']); // Detail kost
    Route::put('/kost/{id}', [KostController::class, 'update']); // Update kost
    Route::delete('/kost/{id}', [KostController::class, 'destroy']); // Hapus kost

    // Booking Management (untuk pemilik kost)
    Route::get('/bookings/owner', [BookingController::class, 'indexForOwner']); // List semua booking untuk pemilik
    
    // Booking Management (untuk penyewa)
    Route::get('/bookings/my', [BookingController::class, 'indexForPenyewa']); // List booking milik penyewa
    Route::post('/bookings', [BookingController::class, 'store']); // Buat booking baru
    Route::get('/bookings/search', [BookingController::class, 'searchByLocation']); // Cari booking berdasarkan lokasi
    
    // Detail dan update booking (harus di bawah /my dan /owner)
    Route::get('/bookings/{id}', [BookingController::class, 'show']); // Detail booking
    Route::put('/bookings/{id}/status', [BookingController::class, 'updateStatus']); // Update status booking

    Route::post('/ulasan', [UlasanController::class, 'store']);

    Route::get('/user', [UserController::class, 'index']);
    Route::get('/user/{id}', [UserController::class, 'show']);
    Route::put('/user/{id}', [UserController::class, 'update']);
    Route::delete('/user/{id}', [UserController::class, 'destroy']);
});
