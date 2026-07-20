<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('pegawai')->orderBy('username')->get();

        return view('users.index', compact('users'));
    }

    public function create()
    {
        $pegawaiList = Pegawai::where('aktif', true)->orderBy('nama')->get(['id', 'nama', 'nip', 'jabatan', 'bidang']);

        return view('users.create', ['pegawaiList' => $pegawaiList]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'nama' => ['required', 'string', 'max:150'],
            'role' => ['required', Rule::in(User::ROLE_OPTIONS)],
            'nip' => ['nullable', 'string', 'max:30', 'unique:users,nip'],
            'pegawai_id' => ['nullable', 'exists:pegawai,id'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        if (filled($validated['pegawai_id'] ?? null)) {
            $validated['nip'] = Pegawai::findOrFail($validated['pegawai_id'])->nip;
        }

        $validated['aktif'] = true;

        $user = User::create($validated);

        AuditLog::catat('Tambah User', "username: {$user->username}, role: ".(config('akses.role_label')[$user->role] ?? $user->role));

        return redirect()->route('users.index')->with('success', "User \"{$user->username}\" berhasil ditambahkan.");
    }

    public function edit(User $user)
    {
        $pegawaiList = Pegawai::where('aktif', true)->orderBy('nama')->get(['id', 'nama', 'nip', 'jabatan', 'bidang']);

        return view('users.edit', ['user' => $user, 'pegawaiList' => $pegawaiList]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'role' => ['required', Rule::in(User::ROLE_OPTIONS)],
            'nip' => ['nullable', 'string', 'max:30', Rule::unique('users', 'nip')->ignore($user->id)],
            'pegawai_id' => ['nullable', 'exists:pegawai,id'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        if (filled($validated['pegawai_id'] ?? null)) {
            $validated['nip'] = Pegawai::findOrFail($validated['pegawai_id'])->nip;
        }

        // Menurunkan superadmin aktif terakhir dari role-nya sama berbahayanya
        // dengan menonaktifkan/menghapusnya — jaga invarian yang sama di sini.
        $passwordDireset = filled($validated['password'] ?? null);

        if (! $passwordDireset) {
            unset($validated['password']);
        }

        DB::transaction(function () use ($user, $validated) {
            $this->kunciSuperadminAktif();

            if ($user->isSuperadmin() && $user->aktif && $validated['role'] !== User::ROLE_SUPERADMIN
                && ! $this->masihAdaSuperadminAktifLain($user)) {
                throw ValidationException::withMessages([
                    'role' => 'Minimal harus ada satu Superadmin aktif. Jadikan user lain Superadmin terlebih dahulu.',
                ]);
            }

            $user->update($validated);
        });

        AuditLog::catat('Ubah User', "username: {$user->username}".($passwordDireset ? ' (password direset)' : ''));

        return redirect()->route('users.index')->with('success', "User \"{$user->username}\" berhasil diperbarui.");
    }

    /** Nyalakan/matikan akun tanpa menghapus data. Diutamakan daripada hapus permanen. */
    public function toggleAktif(User $user)
    {
        if ($user->id === auth()->id() && $user->aktif) {
            return back()->withErrors(['user' => 'Tidak dapat menonaktifkan akun yang sedang login.']);
        }

        DB::transaction(function () use ($user) {
            $this->kunciSuperadminAktif();

            if ($user->aktif && ! $this->masihAdaSuperadminAktifLain($user)) {
                throw ValidationException::withMessages(['user' => 'Minimal harus ada satu Superadmin aktif.']);
            }

            $user->aktif = ! $user->aktif;
            $user->save();
        });

        AuditLog::catat($user->aktif ? 'Aktifkan User' : 'Nonaktifkan User', "username: {$user->username}");

        return back()->with('success', "User \"{$user->username}\" berhasil ".($user->aktif ? 'diaktifkan' : 'dinonaktifkan').'.');
    }

    /** Hapus permanen. Nonaktifkan lebih dianjurkan — lihat toggleAktif(). */
    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'Tidak dapat menghapus akun yang sedang login.']);
        }

        $username = $user->username;

        DB::transaction(function () use ($user) {
            $this->kunciSuperadminAktif();

            if ($user->aktif && ! $this->masihAdaSuperadminAktifLain($user)) {
                throw ValidationException::withMessages(['user' => 'Minimal harus ada satu Superadmin aktif.']);
            }

            $user->delete();
        });

        AuditLog::catat('Hapus User', "username: {$username}");

        return redirect()->route('users.index')->with('success', "User \"{$username}\" berhasil dihapus.");
    }

    /** True kalau masih ada superadmin aktif selain target. */
    private function masihAdaSuperadminAktifLain(User $target): bool
    {
        if (! $target->isSuperadmin()) {
            return true;
        }

        return User::where('role', User::ROLE_SUPERADMIN)
            ->where('aktif', true)
            ->where('id', '!=', $target->id)
            ->exists();
    }

    private function kunciSuperadminAktif(): void
    {
        User::where('role', User::ROLE_SUPERADMIN)
            ->where('aktif', true)
            ->lockForUpdate()
            ->get();
    }
}
