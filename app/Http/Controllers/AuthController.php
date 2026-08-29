<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Helpers\GuestSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            $existing = User::where('username', $credentials['username'])->first();

            AuditLog::catatSebagai(
                $credentials['username'],
                $existing->role ?? '-',
                'Login Gagal',
                'Percobaan login dengan username: '.$credentials['username'],
                $existing?->id
            );

            return back()
                ->withErrors(['username' => 'Username atau password salah.'])
                ->onlyInput('username');
        }

        if (! Auth::user()->aktif) {
            AuditLog::catat('Login Gagal', 'Akun dinonaktifkan: '.$credentials['username']);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['username' => 'Akun dinonaktifkan, hubungi Superadmin.'])
                ->onlyInput('username');
        }

        $request->session()->regenerate();

        Auth::user()->forceFill(['last_login_at' => now()])->save();

        AuditLog::catat('Login', 'User berhasil masuk sistem');

        return redirect()->intended(route('surat-perintah.index'));
    }

    /**
     * Gerbang Pengguna Layanan: satu kata sandi bersama, tanpa akun dan tanpa
     * pendaftaran. Yang lolos mendapat "sesi tamu" (GuestSession) - itu yang
     * dipakai middleware gerbang-layanan untuk membuka halaman Input SP,
     * Monitoring SP, Cetak SPJ, dan Perubahan Tunjangan Keluarga.
     *
     * Kata sandinya dibandingkan di peladen, bukan di peramban: jendela di
     * halaman login cuma tampilan, bukan pengaman.
     */
    public function masukLayanan(Request $request)
    {
        $request->validate(
            ['sandi' => ['required', 'string']],
            ['sandi.required' => 'Kata sandi wajib diisi.']
        );

        $benar = (string) config('akses.sandi_layanan');

        if (! hash_equals($benar, (string) $request->input('sandi'))) {
            AuditLog::catatSebagai('(layanan)', 'layanan', 'Login Gagal', 'Kata sandi Pengguna Layanan salah');

            return back()->withErrors(['sandi' => 'Kata sandi salah.']);
        }

        $request->session()->regenerate();
        GuestSession::login();

        AuditLog::catatSebagai('(layanan)', 'layanan', 'Login', 'Masuk sebagai Pengguna Layanan');

        return redirect()->intended(route('sp.input.create'));
    }

    public function logout(Request $request)
    {
        if (Auth::check()) {
            AuditLog::catat('Logout', 'User keluar dari sistem');

            Auth::logout();
        }

        // session()->invalidate() juga membersihkan sesi tamu layanan (GuestSession).
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
