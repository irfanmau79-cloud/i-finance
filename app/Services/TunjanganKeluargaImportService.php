<?php

namespace App\Services;

use App\Imports\RawSheetImport;
use App\Models\AuditLog;
use App\Models\Pegawai;
use App\Models\TunjanganKeluargaImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
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
        if ($payload['nip'] !== '' && ! Pegawai::where('nip', $payload['nip'])->exists()) {
            $pesan[] = 'NIP tidak ditemukan pada master pegawai.';
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
