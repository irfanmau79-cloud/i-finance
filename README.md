# i-Finance Inspektorat Daerah Jawa Barat

Aplikasi Laravel untuk pengelolaan Surat Perintah, NPD, SPM, RAK bulanan, realisasi anggaran, SPJ, perjalanan dinas, dan tunjangan keluarga.

## Lingkup operasional

Lingkup operasional saat ini adalah Tahun Anggaran 2026. Sebelum mengimpor master anggaran, RAK, NPD, atau SPM untuk tahun lain, aplikasi harus menerima migrasi master data multi-tahun dan workflow rollover khusus.

## Kebutuhan lokal

- Laragon dengan PHP 8.3, MySQL/MariaDB, dan Composer.
- Node.js dan npm untuk build aset.
- Ekstensi PHP yang dibutuhkan Laravel, Maatwebsite Excel, dan mPDF.

## Instalasi di Laragon

```powershell
Set-Location C:\laragon\www\ifinance
composer install
Copy-Item .env.example .env
php artisan key:generate
npm ci
npm run build
```

Buat database kosong, lalu isi koneksi `DB_*` di `.env`. Jangan menyimpan atau mengirim `.env`, `APP_KEY`, password database, maupun kredensial lain ke Git.

Untuk instalasi baru:

```powershell
php artisan migrate
php artisan storage:link
```

Untuk database yang sudah berisi data, buat dan verifikasi backup terlebih dahulu. Periksa `php artisan migrate:status`, lalu jalankan hanya migrasi yang telah direview. Jangan menggunakan `migrate:fresh`, `migrate:refresh`, `migrate:reset`, atau rollback destruktif pada database operasional.

## Konfigurasi lingkungan

Konfigurasi pengembangan lokal dapat memakai debug. Produksi wajib menggunakan setidaknya:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id
APP_FALLBACK_LOCALE=id
LOG_LEVEL=warning
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Produksi wajib memakai HTTPS. Setelah mengubah `.env`, jalankan:

```powershell
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Queue database memerlukan worker yang diawasi service manager:

```powershell
php artisan queue:work --tries=3 --timeout=120
```

## Seeding aman

`DatabaseSeeder` membuat akun pengembangan dan master yang tersedia secara idempoten. Jangan menjalankannya sembarangan pada database produksi. `UserSeeder` menolak password default di environment produksi dan membutuhkan:

```dotenv
IFINANCE_SEED_PASSWORD=password-kuat-yang-unik
```

Jalankan hanya seeder yang sudah diperiksa:

```powershell
php artisan db:seed --class=UserSeeder
```

Jangan memakai `--seed` bersama perintah migrasi destruktif.

## Import awal

Import Master Anggaran, RAK Bulanan, NPD Historis, SPM, dan Tunjangan Keluarga dilakukan melalui halaman Manajemen Data yang berotorisasi. Alur aman adalah:

1. Unduh dan isi template resmi.
2. Upload ke preview/dry-run.
3. Periksa seluruh error, warning, duplikasi, tahun anggaran, nominal, pajak, dan mapping.
4. Buat backup database dan storage.
5. Konfirmasi import hanya setelah hasil preview disetujui.

Preview dapat membuat record staging import, tetapi tidak boleh membuat transaksi operasional. File sumber berisi data pribadi harus disimpan private dan dihapus sesuai kebijakan retensi.

## Pengujian dan kualitas kode

Test menggunakan konfigurasi database pada `phpunit.xml`, bukan database lokal operasional.

```powershell
php artisan test
vendor\bin\pint --test
git diff --check
npm run build
```

Untuk fokus NPD:

```powershell
php artisan test tests/Feature/NpdNavigationTest.php tests/Feature/NpdAntreanTest.php
```

PDF harus diuji melalui endpoint PDF dan dirender menjadi gambar sebelum deployment untuk memastikan tabel, glyph, tanda tangan, dan page break tetap benar.

## Backup dan restore

Simpan backup di luar repository dan batasi hak akses foldernya.

Contoh backup konsisten MySQL/MariaDB:

```powershell
New-Item -ItemType Directory -Force C:\laragon\backups\ifinance
& 'C:\path\to\mysqldump.exe' --single-transaction --routines --triggers --events --databases ifinance --result-file='C:\laragon\backups\ifinance\ifinance-YYYYMMDD-HHMMSS.sql'
Get-FileHash 'C:\laragon\backups\ifinance\ifinance-YYYYMMDD-HHMMSS.sql' -Algorithm SHA256
```

Backup storage private dan public secara terpisah dengan ACL tetap terjaga:

```powershell
Compress-Archive -Path storage\app\private -DestinationPath C:\laragon\backups\ifinance\storage-private-YYYYMMDD-HHMMSS.zip
Compress-Archive -Path storage\app\public -DestinationPath C:\laragon\backups\ifinance\storage-public-YYYYMMDD-HHMMSS.zip
```

Restore harus diuji lebih dahulu pada database dan direktori terisolasi, bukan menimpa sistem aktif:

```powershell
& 'C:\path\to\mysql.exe' -e 'CREATE DATABASE ifinance_restore_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
Get-Content 'C:\laragon\backups\ifinance\ifinance-YYYYMMDD-HHMMSS.sql' | & 'C:\path\to\mysql.exe' ifinance_restore_test
```

Sesudah restore, periksa jumlah record penting, foreign key, `php artisan migrate:status`, akses file storage, login setiap role, dan seluruh test smoke yang relevan. Jangan melakukan restore langsung ke produksi tanpa maintenance window dan rencana rollback berbasis backup terverifikasi.

## Checklist deployment

1. Review `git status`, dependency lock files, dan seluruh migrasi pending.
2. Backup serta uji hash database dan storage.
3. Jalankan test penuh, Pint, pemeriksaan syntax, dan build aset.
4. Terapkan migrasi setelah backup, satu checkpoint pada satu waktu.
5. Set `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, secure cookie, dan log level produksi.
6. Jalankan cache Laravel serta worker queue.
7. Smoke-test login dan otorisasi setiap role, NPD lima jenis, import dry-run, export, PDF, endpoint publik, dashboard, dan audit log.
8. Pantau log aplikasi, failed jobs, kapasitas database, dan storage setelah go-live.

Jangan melakukan deployment hanya berdasarkan kelulusan test otomatis. Mapping pejabat, batas akses data publik, retensi upload, backup/restore, dan hasil render PDF harus mendapat persetujuan pemilik proses bisnis.
