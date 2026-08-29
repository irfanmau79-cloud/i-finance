<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfilController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load('pegawai');

        return view('profil.show', compact('user'));
    }

    /**
     * Ubah nama & password milik SENDIRI. Port dari updateProfil di
     * gas-lama/CodeAuth.gs: target selalu pemilik sesi ($request->user()),
     * tidak pernah dari input, jadi user tidak bisa mengubah akun lain
     * lewat halaman ini. Ganti password wajib password lama yang benar.
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'nama_panggilan' => ['nullable', 'string', 'max:60'],
            'jenis_kelamin' => ['nullable', Rule::in(array_keys(User::KELAMIN))],
            'password_lama' => ['nullable', 'string'],
            'password_baru' => ['nullable', 'string', 'min:6'],
        ]);

        $ubah = [];

        if ($validated['nama'] !== $user->nama) {
            $user->nama = $validated['nama'];
            $ubah[] = 'nama';
        }

        $panggilanBaru = trim((string) ($validated['nama_panggilan'] ?? '')) ?: null;

        if ($panggilanBaru !== $user->nama_panggilan) {
            $user->nama_panggilan = $panggilanBaru;
            $ubah[] = 'nama panggilan';
        }

        $kelaminBaru = $validated['jenis_kelamin'] ?? null;

        if ($kelaminBaru !== $user->jenis_kelamin) {
            $user->jenis_kelamin = $kelaminBaru;
            $ubah[] = 'jenis kelamin';
        }

        if (filled($validated['password_baru'] ?? null)) {
            if (empty($validated['password_lama'])) {
                return back()->withErrors(['password_lama' => 'Masukkan password lama untuk mengganti password.']);
            }

            if (! Hash::check($validated['password_lama'], $user->password)) {
                return back()->withErrors(['password_lama' => 'Password lama salah.']);
            }

            $user->password = $validated['password_baru'];
            $ubah[] = 'password';
        }

        if (! $ubah) {
            return back()->withErrors(['nama' => 'Tidak ada perubahan untuk disimpan.']);
        }

        $user->save();

        AuditLog::catat('Ubah Profil', implode(' & ', $ubah).' diubah');

        return back()->with('success', 'Profil berhasil diperbarui ('.implode(' & ', $ubah).').');
    }
}
