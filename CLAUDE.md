# CLAUDE.md — i-Finance (Laravel)

Sistem keuangan Inspektorat Daerah Provinsi Jawa Barat. Migrasi TOTAL dari
Google Apps Script (GAS) ke Laravel. Tidak ada rollback ke GAS.

Dokumen ini WAJIB dipatuhi. Kalau ada konflik antara asumsi dan dokumen ini,
dokumen ini yang menang. Kalau butuh detail yang tidak ada di sini, JANGAN
MENEBAK — baca sumber yang ditunjuk di bagian "Referensi GAS".

---

## POLA KERJA WAJIB (tiap task)

1. **Baca dulu sebelum menulis.** Baca kode/GAS relevan sebelum menulis apa
   pun. Jangan menebak isi fungsi/modul dari namanya.
2. **Transaksi DB** untuk operasi multi-record (DB::transaction).
3. **Otorisasi di backend**, bukan sekadar menyembunyikan menu di frontend.
4. **Test wajib** ditambahkan DAN dijalankan untuk tiap perubahan logika.
5. **Import selalu pola preview/dry-run** (tampilkan preview sebelum commit
   data).
6. **Commit HANYA saat diminta eksplisit.** Jangan auto-commit tiap selesai.
7. **PDF harus IDENTIK visual dengan GAS asli** — dokumen sudah ditandatangani
   di kantor. Ada test permanen `NpdPdfRenderTest.php` yang me-render semua PDF
   dengan data realistis tiap test run (dibuat setelah audit menemukan bug
   page-break di SPD Rampung yang memotong blok tanda tangan KPA). Jangan
   melewati/melemahkan test ini.

---

## ARSITEKTUR & KEPUTUSAN KUNCI

- **Realisasi anggaran DIHITUNG dari transaksi (NPD, SPM), TIDAK PERNAH
  disimpan sebagai angka statis.** Method terpusat di
  `AnggaranRealisasiService`.
- **PDF digenerate ON-DEMAND** (mPDF), tidak disimpan ke disk.
- Stack lokal: Laragon + MySQL. DB name: `ifinance`.

---

## MODEL REALISASI (istilah kantor — PENTING, jangan diubah maknanya)

```
dana_terikat_npd = SUM nominal NPD non-batal (termasuk draft)
realisasi_npd    = SUM nominal NPD berstatus Selesai
realisasi_ls     = SUM nominal SPM jenis LS
realisasi_aktual = realisasi_npd + realisasi_ls   ("Realisasi SPJ3")
sisa_tersedia    = pagu - dana_terikat_npd - realisasi_ls
realisasi_sp2d   = SPM LS + SPM UP/GU/TU   (kas keluar total, BEDA dari SPJ3)
```

**SPM ada 2 jenis:**
- **UP/GU/TU** — isi ulang kas. TIDAK mengurangi pagu, TIDAK masuk realisasi.
- **LS** — dicairkan langsung. MENGURANGI pagu, WAJIB terikat mata anggaran.

**SPM LS = header + detail:** satu SPM LS BISA mencakup BEBERAPA kode
rekening+tagging (1 penerima, 1 PPN/PPh untuk seluruh dokumen, banyak baris
nominal via tabel `spm_detail` terpisah).
**NPD TETAP 1 mata anggaran per dokumen** — JANGAN disamakan dengan SPM.

---

## STRUKTUR ROLE (login) vs JABATAN (TTD dokumen)

"Bendahara Pengeluaran" punya 2 makna berbeda — JANGAN dicampur di kode:
1. **ROLE login** (`bendahara_pengeluaran` / `bp`) — untuk akses sistem.
2. **JABATAN** di `pejabat_opd` — untuk tanda tangan dokumen.

Role login:
- `superadmin` — kuasa penuh (dulu bernama "bendahara"). Akun: `superadmin-if`.
- `bendahara_pengeluaran` (`bp`) — level OPD. Akses = seperti bpp + SPM +
  Manajemen Data + approve Pengembalian. Di alur NPD HANYA MEMANTAU
  (read-only, tanpa tombol aksi).
- `bpp` — Bendahara Pengeluaran Pembantu, level KPA. Aktif di alur NPD
  (teruskan/setuju/selesai).
- `pptk`, `verifikator`, `layanan` — seperti biasa. `layanan` = tanpa login.

---

## HIERARKI PEJABAT (modul Pelimpahan)

```
Pengguna Anggaran (PA)         — 1 orang per OPD
Bendahara Pengeluaran          — 1 orang, milik PA (JABATAN TTD)
KPA                            — bisa banyak; tiap KPA punya TEPAT 1 BPP
PPTK                           — bisa banyak; 1 PPTK bisa banyak Sub Kegiatan
Tiap Sub Kegiatan -> 1 KPA (BPP ikut otomatis) + 1 PPTK
```

TTD NPD diambil lewat `PejabatResolver` dari pelimpahan sub kegiatan, fallback
ke `data_tambahan` lama + peringatan bila belum diset.

---

## LIMA JENIS NPD (semua sudah dibangun)

`bj` (Barang/Jasa), `pd` (perjalanan dinas, cluster A-D tarif uang harian),
`tr` (transport, turunan pd), `ns` (Narasumber, PPh 21), `kd` (Kontribusi
Diklat, 2 mode: kontribusi lalu perjalanan).

Semua: nominal = total BRUTO, validasi `<= sisa_tersedia`, alur transisi 8 aksi
generik, histori tersimpan, penomoran anti race-condition.

---

## KEBIJAKAN ADOPSI DARI GAS (PENTING)

GAS adalah versi lama yang terus di-update; Laravel adalah tujuan migrasi.
Aturan saat menyelaraskan Laravel dengan GAS:

1. **Modul yang SUDAH ADA di Laravel — JANGAN dihapus atau dirombak
   fondasinya** hanya karena tampak beda dengan GAS. Versi Laravel yang sudah
   jalan adalah basis. Yang sudah berfungsi tidak dibongkar.
2. **Update / perbaikan / fitur tambahan dari GAS pada modul yang sudah ada di
   Laravel — DIADOPSI** ke Laravel. Terapkan mengikuti arsitektur & pola
   Laravel yang sudah ada (service terpusat, transaksi DB, otorisasi backend,
   test), BUKAN menyalin kode GAS mentah-mentah.
3. **Fitur/modul yang BARU di GAS dan BELUM ADA padanannya di Laravel —
   DIBANGUN BARU** di Laravel mengikuti pola arsitektur yang ada.
4. Sebelum memutuskan sebuah update "belum ada di Laravel", CEK dulu apakah
   padanannya benar-benar belum ada (baca kode Laravel terkait). Jangan
   berasumsi dari changelog GAS saja.
5. Kalau sebuah adopsi akan mengubah PERILAKU modul Laravel yang sudah dipakai
   (mis. mengubah cara penomoran, alur approval, struktur data), konfirmasi ke
   Irfan dulu sebelum eksekusi.

---

## REFERENSI GAS (sumber kebenaran untuk adaptasi)

Kode GAS asli (read-only, untuk referensi saat migrasi modul) ada di:
```
C:\laragon\www\i-finance gas\
```
Folder terpisah dari project Laravel — JANGAN di-track git project ini.

**Aturan saat mengadaptasi modul dari GAS:**
- WAJIB baca file `.gs`/`.html` yang relevan di folder itu SEBELUM menulis kode
  Laravel. Jangan menebak logika dari nama fungsi.
- `README_PERUBAHAN.txt` di folder itu = changelog. Saat mengerjakan satu
  modul, baca HANYA bagian changelog yang relevan untuk modul itu (file besar;
  jangan baca seluruhnya tiap kali).
- Path punya spasi — selalu quote: `"C:\laragon\www\i-finance gas\CodeAuth.gs"`.

---

## STATUS PENGERJAAN (per akhir Agustus 2026)

**Sudah dibangun:** 5 jenis NPD, Surat Perintah, SPM CRUD, Manajemen Data
(export + import preview/dry-run), Rincian Realisasi, Analisis & Tren,
Dashboard Realisasi Anggaran, Dashboard Perjalanan Dinas, Dashboard SPJ
Pengawasan, Inventarisasi SPJ, Tunjangan Keluarga, Manajemen Users, Profil
Saya, Pelimpahan.

Migration untuk restrukturisasi SPM LS (header/detail) & tabel Pengembalian
sudah jalan di DB. Status eksekusi fitur di level kode BELUM tentu final —
konfirmasi kondisi aktual sebelum asumsi.

**Belum dikerjakan:** impor NPD lama (Jan-sekarang) dari GAS, Posisi Kas BPP,
deployment produksi (masih Laragon lokal), redesain "Buat NPD" interaktif.

---

## CATATAN

- Kalau ragu soal kondisi aktual modul yang sudah dibangun, TANYA — jangan
  menebak isi kode dari nama.
- Environment: 1 device (laptop). Alur git: kerja -> (bila diminta) commit +
  push. Tidak perlu pull.
