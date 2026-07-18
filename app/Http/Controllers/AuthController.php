<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
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

        $request->session()->regenerate();

        AuditLog::catat('Login', 'User berhasil masuk sistem');

        return redirect()->intended(route('surat-perintah.index'));
    }

    public function logout(Request $request)
    {
        AuditLog::catat('Logout', 'User keluar dari sistem');

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
