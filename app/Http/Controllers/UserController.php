<?php

namespace App\Http\Controllers;

use App\Models\Pengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Notifikasi;
use Carbon\Carbon;

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

    // Mengambil notifikasi kustom untuk pengguna yang sedang login
    public function indexNotifications(Request $request)
    {
        $user = Auth::user();
        $limit = $request->query('limit', 15);
        $unreadOnly = filter_var($request->query('unread', false), FILTER_VALIDATE_BOOLEAN);

        $query = Notifikasi::where('id_pengguna', $user->id_pengguna);

        if ($unreadOnly) {
            $query->whereNull('dibaca_pada');
        }

        // Urutkan berdasarkan yang terbaru dulu dan terapkan limit
        $notifikasiList = $query->orderBy('created_at', 'desc')->paginate($limit);

        if ($notifikasiList->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada notifikasi untuk saat ini.',
                'notifications' => []
            ]);
        }

        // Format notifikasi untuk menghapus field 'link'
        $formattedNotifications = $notifikasiList->map(function ($notifikasi) {
            return [
                'id' => $notifikasi->id,
                'id_pengguna' => $notifikasi->id_pengguna,
                'subjek' => $notifikasi->subjek,
                'pesan' => $notifikasi->pesan,
                'dibaca_pada' => $notifikasi->dibaca_pada,
                'created_at' => $notifikasi->created_at,
                'updated_at' => $notifikasi->updated_at,
            ];
        });

        return response()->json([
            'message' => 'Notifikasi berhasil diambil.',
            'notifications' => $formattedNotifications->all() // Ambil array dari koleksi hasil map
        ]);
    }

    // Menandai notifikasi kustom sebagai sudah dibaca
    public function markNotificationAsRead(Request $request, $notification_id)
    {
        $user = Auth::user();
        $notifikasi = Notifikasi::where('id', $notification_id)
                                ->where('id_pengguna', $user->id_pengguna)
                                ->first();

        if ($notifikasi) {
            if (is_null($notifikasi->dibaca_pada)) {
                $notifikasi->dibaca_pada = Carbon::now();
                $notifikasi->save();
            }
            return response()->json(['message' => 'Notifikasi ditandai sudah dibaca']);
        }

        return response()->json(['message' => 'Notifikasi tidak ditemukan atau Anda tidak berhak mengaksesnya'], 404);
    }
} 