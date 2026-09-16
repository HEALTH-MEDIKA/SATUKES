# SATUKES — Dashboard Monitoring Fasilitas Kesehatan

MVP agregator kunjungan multi-faskes menggunakan CodeIgniter 3.1.13, Tabler 1.4.0, PHP 8.2, dan MySQL/MariaDB. SATUKES dapat memonitor berbagai jenis fasilitas kesehatan, seperti puskesmas, klinik, rumah sakit, dan fasilitas kesehatan lainnya.

## Fitur tersedia

- Login aman, throttling, session, dan CSRF.
- RBAC empat peran serta assignment faskes per pengguna.
- Daftar/tambah/ubah faskes, health check, dan sinkronisasi manual.
- Konektor API Sistem Informasi Faskes v1 dengan HKDF, HMAC-SHA256, Bearer token, dan AES-256-GCM.
- Dashboard responsif: total, pasien baru/lama, rawat jalan/inap, gawat darurat, rujukan, tren harian, dan kontribusi faskes.
- Sinkronisasi CLI terjadwal, audit log, dan skema database lengkap.

PRD lengkap: [`docs/PRD_DASHBOARD_TERPADU.md`](docs/PRD_DASHBOARD_TERPADU.md).

## Instalasi lokal XAMPP

Persyaratan: PHP 8.2 dengan `curl`, `openssl`, `mbstring`, dan `mysqli`; MySQL/MariaDB; Composer.

1. Buat database kosong:

   ```sql
   CREATE DATABASE satukes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. Atur environment Apache/OS berdasarkan `.env.example`. Minimal production:

   ```text
   APP_BASE_URL=http://localhost/SATUKES/
   APP_KEY=<acak-minimal-32-karakter>
   FASKES_SECRET_MASTER_KEY=<acak-minimal-32-karakter-yang-berbeda>
   DB_HOST=127.0.0.1
   DB_NAME=satukes
   DB_USER=root
   DB_PASS=
   ```

   Nama lama `CLINIC_SECRET_MASTER_KEY` masih dibaca sebagai fallback untuk kompatibilitas instalasi yang sudah berjalan, tetapi konfigurasi baru harus memakai `FASKES_SECRET_MASTER_KEY`.

   Aplikasi sengaja tidak memuat file `.env` otomatis untuk mengurangi risiko secret terpublikasi oleh Apache. Gunakan environment server atau VirtualHost `SetEnv` yang berada di luar repository.

3. Pasang dependency bila folder `vendor` belum tersedia:

   ```powershell
   composer install --no-dev --optimize-autoloader
   ```

4. Jalankan migration, seed RBAC, dan buat admin pertama. Pada environment development, cukup jalankan:

   ```powershell
   php index.php cli/install
   ```

   Kredensial development default:

   ```text
   Email: admin@example.id
   Password: admin123345
   ```

   Segera ubah password setelah login. Untuk menentukan kredensial sendiri atau ketika `CI_ENV=production`, gunakan:

   ```powershell
   $env:INSTALL_ADMIN_EMAIL='admin@example.id'
   $env:INSTALL_ADMIN_PASSWORD='gunakan-password-kuat-minimal-10'
   php index.php cli/install
   Remove-Item Env:INSTALL_ADMIN_PASSWORD
   ```

5. Buka `http://localhost/SATUKES/`, login, lalu tambahkan faskes.

Jika `mod_rewrite` tidak aktif, set `index_page` kembali ke `index.php` dan gunakan URL `http://localhost/SATUKES/index.php/login`.

## Deployment cPanel (`satukes.healthmedika.cloud`)

Atur document root subdomain ke direktori yang berisi `index.php`. Struktur minimum
di server harus seperti berikut (jangan mengunggah isi folder tanpa `vendor`):

```text
<document-root>/
|-- index.php
|-- .htaccess
|-- application/
`-- vendor/codeigniter/framework/system/
```

Dependency CodeIgniter disertakan dalam repository supaya deployment melalui Git
atau File Manager tetap dapat berjalan ketika Composer/SSH tidak tersedia. Jika
memilih tidak mengunggah `vendor`, jalankan perintah berikut dari direktori yang
sama dengan `index.php`:

```bash
composer install --no-dev --optimize-autoloader
```

Path framework di `index.php` dihitung dari `__DIR__`, sehingga tidak perlu memakai
path absolut `/home/...` yang berbeda untuk setiap akun cPanel.

Atur environment melalui menu **Software > MultiPHP INI Editor**, konfigurasi
Apache cPanel, atau fasilitas environment hosting. Nilai minimum production:

```text
CI_ENV=production
APP_BASE_URL=https://satukes.healthmedika.cloud/
APP_KEY=<acak-minimal-32-karakter>
FASKES_SECRET_MASTER_KEY=<acak-minimal-32-karakter-yang-berbeda>
DB_HOST=localhost
DB_NAME=<nama-database-cpanel>
DB_USER=<user-database-cpanel>
DB_PASS=<password-database-cpanel>
```

Setelah deploy, pastikan permission direktori `application/cache` dan
`application/logs` mengizinkan proses PHP menulis (umumnya `0755`, atau sesuai
konfigurasi PHP handler hosting). Jangan memakai permission `0777`.

### Memperbarui instalasi yang sudah ada

Jalankan migrasi tanpa membuat ulang pengguna admin:

```powershell
php index.php cli/migrate
```

Migrasi nomenklatur memperbarui label hak akses menjadi faskes. Tabel dan permission internal bernama `branches*` tetap dipertahankan agar data serta integrasi lama tidak terputus.

## Scheduler

Sinkronisasi rolling tujuh hari (disarankan setiap malam):

```powershell
php C:\xampp\htdocs\SATUKES\index.php cli/sync-visits 7
```

Untuk Windows Task Scheduler, gunakan `C:\xampp\php\php.exe` sebagai Program dan path `index.php cli/sync-visits 7` sebagai Arguments dengan Start in `C:\xampp\htdocs\SATUKES`.

## Menambah faskes

Siapkan dari sistem informasi faskes:

- Base URL tanpa `/api/v1`;
- `cons_id` unik;
- `secret_key` minimal 32 karakter;
- endpoint `/api/v1/health`, `/api/v1/auth/token`, serta delapan endpoint katalog pada `docs/README_API_CATALOG_MONITORING.md`.

Setelah disimpan, tekan **Tes** kemudian **Sinkron**. Secret lama tidak pernah dikirim kembali ke browser. Mengisi secret baru pada halaman ubah akan merotasi secret faskes yang disimpan oleh SATUKES.

## Production checklist

- Gunakan HTTPS untuk SATUKES dan seluruh sistem informasi faskes; jangan menonaktifkan validasi sertifikat.
- Ganti seluruh key development dan set `CI_ENV=production`.
- Set cookie `Secure`, `HttpOnly`, dan SameSite sesuai terminasi HTTPS.
- Batasi akses jaringan dari server SATUKES ke endpoint sistem informasi faskes yang diperlukan.
- Backup database dan `FASKES_SECRET_MASTER_KEY` sebagai pasangan.
- Uji restore, rotasi secret, angka kunjungan, serta assignment faskes sebelum rollout.
- Jangan commit credential, export Postman berisi secret, dump database, atau log production.

## Verifikasi kode

```powershell
Get-ChildItem application -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

Kontrak sumber API yang menjadi acuan tetap tersedia pada dokumen `README_MVP_REST_API_DASHBOARD_FASKES.md` dan folder `postman/`.
