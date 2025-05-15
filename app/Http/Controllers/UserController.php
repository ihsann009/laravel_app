<?php

namespace App\Http\Controllers;

use App\Models\Pengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    // List semua user kecuali admin
    public function index()
    {
        $this->authorizeAdmin();
        return response()->json([
            'users' => Pengguna::where('role', '!=', 'admin')->get()
        ]);
    }

    // Detail user
    public function show($id)
    {
        $this->authorizeAdmin();
        $user = Pengguna::findOrFail($id);
        
        // Jika user yang dicari adalah admin, return 404
        if ($user->role === 'admin') {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }
        
        return response()->json(['user' => $user]);
    }

    // Update user
    public function update(Request $request, $id)
    {
        $this->authorizeAdmin();
        $user = Pengguna::findOrFail($id);
        
        // Jika user yang akan diupdate adalah admin, return 403
        if ($user->role === 'admin') {
            return response()->json(['message' => 'Tidak dapat mengupdate data admin'], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'nama' => 'sometimes|required|string|max:100',
            'email' => 'sometimes|required|email|max:100|unique:pengguna,email,' . $id . ',id_pengguna',
            'nomor_telepon' => 'sometimes|required|string|max:15',
            'alamat' => 'nullable|string',
            'role' => 'in:penyewa,admin'
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }
        $user->update($validator->validated());
        return response()->json(['message' => 'User berhasil diupdate', 'user' => $user]);
    }

    // Hapus user
    public function destroy($id)
    {
        $this->authorizeAdmin();
        $user = Pengguna::findOrFail($id);
        
        // Jika user yang akan dihapus adalah admin, return 403
        if ($user->role === 'admin') {
            return response()->json(['message' => 'Tidak dapat menghapus data admin'], 403);
        }
        
        $user->delete();
        return response()->json(['message' => 'User berhasil dihapus']);
    }

    // Helper authorize admin
    private function authorizeAdmin()
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'admin') {
            abort(403, 'Hanya admin yang boleh mengakses');
        }
    }
} 