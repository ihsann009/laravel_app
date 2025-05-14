# API Documentation

## Authentication
- **POST /api/register** — Register penyewa
- **POST /api/register/pemilik-kost** — Register pemilik kost
- **POST /api/register/admin** — Register admin
- **POST /api/login** — Login

## Kost (Public)
- **GET /api/kost/search** — Cari kost (public, tanpa token)

## Kost (Auth)
- **GET /api/kost** — List kost (semua untuk penyewa, milik sendiri untuk pemilik)
- **POST /api/kost** — Tambah kost baru (pemilik)
- **GET /api/kost/{id}** — Detail kost
- **PUT /api/kost/{id}** — Update kost (pemilik)
- **DELETE /api/kost/{id}** — Hapus kost (pemilik)

## Kamar (Auth)
- **GET /api/kost/{kostId}/kamar** — List kamar di kost
- **POST /api/kost/{kostId}/kamar** — Tambah kamar ke kost
- **GET /api/kost/{kostId}/kamar/{kamarId}** — Detail kamar
- **PUT /api/kost/{kostId}/kamar/{kamarId}** — Update kamar
- **DELETE /api/kost/{kostId}/kamar/{kamarId}** — Hapus kamar

## Booking (Auth)
- **GET /api/bookings/owner** — List booking untuk pemilik kost
- **GET /api/bookings/my** — List booking milik penyewa
- **POST /api/bookings** — Buat booking baru
- **GET /api/bookings/search** — Cari booking berdasarkan lokasi
- **GET /api/bookings/{id}** — Detail booking
- **PUT /api/bookings/{id}/status** — Update status booking

## User (Auth)
- **GET /api/user** — Info user login
- **POST /api/logout** — Logout
- **GET /api/me** — Info user login (detail)

## Admin Only
- **GET /api/admin/unverified-owners** — List pemilik kost yang belum diverifikasi
- **POST /api/admin/verify-owner/{id_pengguna}** — Verifikasi pemilik kost
- **POST /api/admin/unverify-owner/{id_pengguna}** — Batalkan verifikasi pemilik kost 