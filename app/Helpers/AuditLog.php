<?php

namespace App\Helpers;

use App\Models\AuditLog as AuditLogModel;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Pencatatan audit trail. Port dari _catatLog/_catatLogUser di gas-lama
 * (CodeAuditLog.gs): tidak pernah melempar error, sebab audit tidak boleh
 * menggagalkan operasi utama yang sedang dicatat.
 */
class AuditLog
{
    /**
     * Catat aktivitas milik user yang sedang login (session aktif). Untuk
     * aksi tanpa login (mis. /sp/input oleh role layanan), user/role dicatat
     * sebagai "layanan" dan user_id tetap null.
     */
    public static function catat(string $aktivitas, ?string $keterangan = null): void
    {
        $user = Auth::user();

        self::simpan(
            $user?->id,
            $user->username ?? 'layanan',
            $user->role ?? 'layanan',
            $aktivitas,
            $keterangan
        );
    }

    /**
     * Catat aktivitas dengan username/role eksplisit, untuk kasus tanpa
     * session user yang berlaku: percobaan login gagal (belum ter-autentikasi)
     * atau proses dari artisan command (tidak ada request/session).
     */
    public static function catatSebagai(string $username, string $role, string $aktivitas, ?string $keterangan = null, ?int $userId = null): void
    {
        self::simpan($userId, $username, $role, $aktivitas, $keterangan);
    }

    private static function simpan(?int $userId, string $username, string $role, string $aktivitas, ?string $keterangan): void
    {
        try {
            AuditLogModel::create([
                'user_id' => $userId,
                'username' => $username,
                'role' => $role,
                'aktivitas' => $aktivitas,
                'keterangan' => $keterangan,
                'ip_address' => self::ipAddress(),
            ]);
        } catch (Throwable $e) {
            // audit tidak boleh menggagalkan operasi utama
        }
    }

    private static function ipAddress(): ?string
    {
        try {
            return request()?->ip();
        } catch (Throwable $e) {
            return null;
        }
    }
}
