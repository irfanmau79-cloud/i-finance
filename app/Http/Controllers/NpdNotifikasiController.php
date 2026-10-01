<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Models\Npd;
use App\Models\NpdNotifikasi;
use App\Services\NotifikasiNpdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Aksi "Kirim Notifikasi" di Data NPD. Dua langkah yang sengaja dipisah:
 * preview() menyiapkan apa yang akan dikirim (tujuan, nomor, bunyi pesan,
 * riwayat) untuk ditampilkan lebih dulu, store() mencatat bahwa petugas
 * benar-benar membuka WhatsApp untuk NPD itu.
 *
 * Otorisasi tidak cukup di rute: status NPD ikut diperiksa di sini supaya
 * NPD yang belum cair tidak bisa dinotifikasi lewat permintaan langsung.
 */
class NpdNotifikasiController extends Controller
{
    public function __construct(private readonly NotifikasiNpdService $service) {}

    public function preview(Request $request, Npd $npd): JsonResponse
    {
        $this->pastikanBoleh($request, $npd);

        $daftar = $this->service->daftarTujuan($npd);
        $superadmin = $request->user()->isSuperadmin();

        // Tiap penerima membawa pesan & tautannya sendiri: nominal pembukanya
        // sama (total NPD), tetapi sapaan dan nomor tujuannya berbeda.
        $penerima = array_map(fn (array $t) => $t + [
            'nominal_teks' => 'Rp '.number_format((float) $t['nominal'], 2, ',', '.'),
            'pesan' => $this->service->pesan($npd, $t),
            'tautan' => $this->service->tautan($npd, $t),
            'url_ubah_pegawai' => $superadmin && ($t['pegawai_id'] ?? null) !== null
                ? route('tunjangan.pegawai.edit', $t['pegawai_id'])
                : null,
        ], $daftar);

        $utama = $penerima[0];

        return response()->json([
            'nomor_npd' => $npd->nomor_lengkap ?: '-',
            'nomor_sp' => $npd->suratPerintah?->nomor_sp,
            'total_teks' => 'Rp '.number_format($this->service->totalTujuan($npd), 2, ',', '.'),
            'penerima' => array_values($penerima),
            // Dipertahankan supaya tampilan penerima tunggal tidak berubah.
            'tujuan' => $utama,
            'pesan' => $utama['pesan'],
            'tautan' => $utama['tautan'],
            'boleh_ubah_pegawai' => $utama['url_ubah_pegawai'] !== null,
            'url_ubah_pegawai' => $utama['url_ubah_pegawai'],
            'riwayat' => $this->riwayat($npd),
        ]);
    }

    public function store(Request $request, Npd $npd): JsonResponse
    {
        $this->pastikanBoleh($request, $npd);

        $daftar = $this->service->daftarTujuan($npd);

        // Indeks menunjuk penerima mana yang WhatsApp-nya dibuka. Tanpa
        // indeks berarti penerima pertama - itulah yang terjadi pada NPD
        // berpenerima tunggal.
        $index = (int) $request->integer('penerima', 0);
        $tujuan = $daftar[$index] ?? null;

        abort_if($tujuan === null, 422, 'Penerima yang dituju tidak ada pada NPD ini.');
        abort_if($tujuan['nomor_wa'] === null, 422, 'Nomor handphone penerima belum diisi.');

        $catatan = $this->service->catat($npd, $request->user(), $tujuan);

        AuditLog::catat('Kirim Notifikasi NPD', sprintf(
            'NPD %s ke %s (%s)',
            $npd->nomor_lengkap ?: '#'.$npd->id,
            $catatan->tujuan_nama,
            $catatan->tujuan_nomor
        ));

        return response()->json(['riwayat' => $this->riwayat($npd)]);
    }

    private function pastikanBoleh(Request $request, Npd $npd): void
    {
        abort_unless($this->service->bolehKirim($request->user(), $npd), 403);
    }

    /**
     * @return array<int, array{waktu: string, oleh: string, nomor: string}>
     */
    private function riwayat(Npd $npd): array
    {
        return NpdNotifikasi::with('user')
            ->where('npd_id', $npd->id)
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (NpdNotifikasi $n) => [
                'waktu' => $n->created_at->translatedFormat('d M Y H:i'),
                'oleh' => $n->user?->nama ?? 'Sistem',
                'nomor' => $n->tujuan_nomor,
            ])
            ->all();
    }
}
