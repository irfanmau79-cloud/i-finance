<?php

namespace App\Services;

use App\Imports\RawSheetImport;
use App\Models\AuditLog;
use App\Models\Pegawai;
use App\Models\TunjanganKeluargaImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use RuntimeException;

class TunjanganKeluargaImportService
{
    public function preview(UploadedFile $file, int $userId): TunjanganKeluargaImport
    {
        $reader = new RawSheetImport(1);
        Excel::import($reader, $file);
        $rows = $reader->rows;
        if ($rows->isEmpty()) {
            throw new RuntimeException('File import kosong.');
        }

        $headers = collect($rows->shift())->map(fn ($value) => $this->header($value))->values();

        $this->pastikanCocokDenganDataPegawai($rows, $headers);

        return DB::transaction(function () use ($rows, $headers, $file, $userId) {
            $import = TunjanganKeluargaImport::create(['nama_file' => $file->getClientOriginalName(), 'user_id' => $userId, 'status' => 'preview']);
            $valid = $invalid = 0;
            foreach ($rows as $index => $row) {
                if (collect($row)->filter(fn ($value) => filled($value))->isEmpty()) {
                    continue;
                }
                $assoc = $headers->combine(collect($row)->pad($headers->count(), null)->take($headers->count()))->all();
                $hasil = $this->evaluasi($assoc);
                $hasil['valid'] ? $valid++ : $invalid++;
                $import->baris()->create(['nomor_baris' => $index + 2, 'valid' => $hasil['valid'], 'pesan' => $hasil['pesan'], 'payload' => $hasil['payload']]);
            }
            $import->update(['total_baris' => $valid + $invalid, 'baris_valid' => $valid, 'baris_invalid' => $invalid]);

            return $import->load('baris');
        });
    }

    public function confirm(TunjanganKeluargaImport $import, TunjanganKeluargaService $service, int $userId): int
    {
        return DB::transaction(function () use ($import, $service, $userId) {
            $import = TunjanganKeluargaImport::query()->lockForUpdate()->findOrFail($import->id);
            if ($import->status !== 'preview') {
                throw new RuntimeException('Batch import sudah diproses.');
            }
            if ($import->baris()->where('valid', false)->exists()) {
                throw new RuntimeException('Import tidak dapat dikonfirmasi selama masih ada baris invalid.');
            }
            $count = 0;
            foreach ($import->baris()->orderBy('nomor_baris')->lockForUpdate()->get() as $row) {
                $payload = $row->payload;
                $pegawai = Pegawai::where('nip', $payload['nip'])->lockForUpdate()->first();
                if (! $pegawai) {
                    throw new RuntimeException("Baris {$row->nomor_baris}: pegawai tidak lagi ditemukan.");
                }
                $service->simpanKeluarga($pegawai, $payload, $userId);
                $count++;
            }
            $import->update(['status' => 'committed', 'committed_at' => now()]);
            $user = User::find($userId);
            AuditLog::create([
                'user_id' => $userId,
                'username' => $user?->username ?? 'system',
                'role' => $user?->role ?? 'system',
                'aktivitas' => 'Import Awal Tunjangan Keluarga',
                'keterangan' => "Batch #{$import->id}: {$count} pegawai diperbarui dari {$import->nama_file}",
            ]);

            return $count;
        });
    }

    /**
     * Berkas import HARUS memuat pegawai yang sama persis dengan daftar Data
     * Tunjangan Keluarga: jumlahnya sama dan NIP-nya sama.
     *
     * Alur yang dipakai kepegawaian adalah unduh -> isi kolom keluarga ->
     * unggah lagi untuk MENIMPA data lama. Karena menimpa, selisih satu baris
     * saja berarti ada pegawai yang datanya diam-diam tidak ikut terbarui,
     * atau baris asing yang tidak seharusnya ada. Lebih baik ditolak utuh di
     * awal daripada separuh tersimpan.
     *
     * @param  Collection<int, array<int, mixed>>  $rows
     * @param  Collection<int, string>  $headers
     */
    private function pastikanCocokDenganDataPegawai(Collection $rows, Collection $headers): void
    {
        $kolomNip = $headers->search('nip');

        if ($kolomNip === false) {
            throw new RuntimeException('Kolom NIP tidak ditemukan pada berkas import.');
        }

        $nipBerkas = $rows
            ->reject(fn ($row) => collect($row)->filter(fn ($v) => filled($v))->isEmpty())
            ->map(fn ($row) => $this->normalNip($row[$kolomNip] ?? null))
            ->filter()
            ->values();

        $ganda = $nipBerkas->duplicates()->unique()->values();

        if ($ganda->isNotEmpty()) {
            throw new RuntimeException('NIP ganda pada berkas import: '.$ganda->take(5)->implode(', ').'.');
        }

        $nipSistem = Pegawai::query()->berhakTunjangan()->pluck('nip')
            ->map(fn ($nip) => $this->normalNip($nip))->filter()->values();

        if ($nipBerkas->count() !== $nipSistem->count()) {
            throw new RuntimeException(sprintf(
                'Jumlah pegawai tidak sama: berkas berisi %d baris, sedangkan Data Tunjangan Keluarga berisi %d pegawai. Unduh ulang berkasnya lalu isi tanpa menambah atau menghapus baris.',
                $nipBerkas->count(),
                $nipSistem->count()
            ));
        }

        $kurang = $nipSistem->diff($nipBerkas)->values();
        $lebih = $nipBerkas->diff($nipSistem)->values();

        if ($kurang->isNotEmpty() || $lebih->isNotEmpty()) {
            $pesan = 'Daftar NIP pada berkas tidak sama dengan Data Tunjangan Keluarga.';

            if ($kurang->isNotEmpty()) {
                $pesan .= ' Tidak ada di berkas: '.$kurang->take(5)->implode(', ').'.';
            }

            if ($lebih->isNotEmpty()) {
                $pesan .= ' Tidak dikenal di sistem: '.$lebih->take(5)->implode(', ').'.';
            }

            throw new RuntimeException($pesan);
        }
    }

    /** NIP dibandingkan tanpa karakter non-digit supaya spasi/strip tidak dianggap beda. */
    private function normalNip(mixed $nip): string
    {
        return preg_replace('/\D/', '', (string) $nip) ?? '';
    }

    /** Cari pegawai berhak tunjangan berdasarkan NIP, dibandingkan per digit. */
    private function cariPegawai(string $nip): ?Pegawai
    {
        $target = $this->normalNip($nip);

        if ($target === '') {
            return null;
        }

        return Pegawai::query()->berhakTunjangan()->get(['id', 'nip'])
            ->first(fn (Pegawai $p) => $this->normalNip($p->nip) === $target);
    }

    private function evaluasi(array $row): array
    {
        $payload = [
            'nama_pegawai' => trim((string) ($row['nama_pegawai'] ?? '')),
            'nip' => trim((string) ($row['nip'] ?? '')),
            'pasangan' => ['nama' => trim((string) ($row['nama_pasangan'] ?? '')), 'tanggal_lahir' => $this->tanggal($row['tanggal_lahir_pasangan'] ?? null), 'status_tunjangan' => $this->aktif($row['status_pasangan'] ?? null)],
            'anak' => [], 'catatan' => 'Import awal data lama',
        ];
        foreach ([1, 2] as $i) {
            $nama = trim((string) ($row["nama_anak_{$i}"] ?? ''));
            if ($nama === '') {
                continue;
            }
            $ket = trim((string) ($row["keterangan_anak_{$i}"] ?? ''));
            $payload['anak'][] = ['nama' => $nama, 'tanggal_lahir' => $this->tanggal($row["tanggal_lahir_anak_{$i}"] ?? null),
                'status_tunjangan' => $this->aktif($row["status_anak_{$i}"] ?? null), 'perpanjangan_kuliah' => str_contains(mb_strtolower($ket), 'sudah'), 'keterangan' => $ket ?: null];
        }
        $pesan = [];
        if ($payload['nama_pegawai'] === '' || $payload['nip'] === '') {
            $pesan[] = 'Nama Pegawai dan NIP wajib diisi.';
        }
        // NIP dicocokkan tanpa karakter non-digit, sama seperti penjagaan
        // jumlah/NIP di atas - spasi atau strip pada berkas tidak boleh
        // membuat pegawai yang sama dianggap tidak ditemukan.
        $pegawai = $payload['nip'] !== '' ? $this->cariPegawai($payload['nip']) : null;

        if ($payload['nip'] !== '' && ! $pegawai) {
            $pesan[] = 'NIP tidak ditemukan pada master pegawai, atau statusnya tidak berhak tunjangan keluarga.';
        }

        if ($pegawai) {
            // Simpan bentuk resmi dari master supaya commit() menemukannya.
            $payload['nip'] = $pegawai->nip;
        }
        foreach ($payload['anak'] as $index => $anak) {
            if (! $anak['tanggal_lahir']) {
                $pesan[] = 'Tanggal lahir Anak '.($index + 1).' kosong atau invalid.';
            }
        }
        if (collect($payload['anak'])->where('status_tunjangan', true)->count() > 2) {
            $pesan[] = 'Maksimal dua anak penerima tunjangan.';
        }

        return ['valid' => $pesan === [], 'pesan' => $pesan, 'payload' => $payload];
    }

    private function header(mixed $value): string
    {
        $text = mb_strtolower(trim((string) $value));
        $text = preg_replace('/[^a-z0-9]+/u', '_', $text);

        return trim($text, '_');
    }

    private function tanggal(mixed $value): ?string
    {
        if (is_numeric($value)) {
            try {
                return Date::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (\Throwable) {
            }
        }

        return TunjanganKeluargaService::parseTanggal($value)?->format('Y-m-d');
    }

    private function aktif(mixed $value): bool
    {
        $text = mb_strtolower(trim((string) $value));

        return in_array($text, ['1', 'ya', 'aktif', 'tunjangan aktif', 'true'], true);
    }
}
