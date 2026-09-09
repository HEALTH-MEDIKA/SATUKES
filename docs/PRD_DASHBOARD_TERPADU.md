# Product Requirements Document — SATUKES

**Produk:** Dashboard Monitoring Fasilitas Kesehatan  
**Versi:** MVP 1.0  
**Tanggal:** 9 September 2026  
**Status:** Siap implementasi/UAT tahap awal

## 1. Ringkasan produk

SATUKES adalah aplikasi pusat monitoring yang terpisah dari sistem informasi masing-masing fasilitas kesehatan. Sistem menarik data agregat kunjungan melalui REST API v1 dari setiap faskes, menyimpannya sebagai agregat harian, lalu menyajikan analitik lintas faskes berdasarkan hak akses pengguna. Istilah faskes mencakup puskesmas, klinik, rumah sakit, balai kesehatan, dan jenis fasilitas kesehatan lainnya.

MVP hanya memproses angka kunjungan. Nama, NIK, nomor rekam medis, alamat, nomor telepon, diagnosis, dan data klinis pasien tidak dikirim atau disimpan.

## 2. Masalah yang diselesaikan

- Pimpinan belum memiliki satu tampilan konsisten untuk membandingkan aktivitas faskes.
- Data berada di sistem informasi milik masing-masing faskes dan tidak boleh diakses langsung melalui database.
- Kredensial dan akses analitik perlu dikelola terpusat tanpa menyamakan hak setiap pengguna.
- Gangguan koneksi satu faskes tidak boleh membuat data faskes lain tidak dapat dilihat.

## 3. Tujuan dan indikator keberhasilan

### Tujuan MVP

1. Menampilkan total dan tren kunjungan harian untuk periode maksimal 31 hari.
2. Menampilkan kontribusi kunjungan per faskes yang dapat diakses pengguna.
3. Memungkinkan admin mendaftarkan beberapa faskes, menguji koneksi, dan memicu sinkronisasi.
4. Menyediakan login serta role-based access control (RBAC) dan pembatasan faskes per pengguna.
5. Mengintegrasikan kontrak keamanan API sistem informasi faskes: HTTPS, HMAC-SHA256, nonce/timestamp, Bearer token, dan AES-256-GCM.

### KPI penerimaan

- Angka kunjungan per hari sama dengan response API sistem informasi faskes pada 100% sampel UAT.
- Pengguna tanpa permission menerima HTTP 403 dan tidak melihat menu terkait.
- Pengguna terbatas faskes tidak dapat melihat data faskes lain.
- Secret API tidak tampil kembali di UI, log, atau database dalam plaintext.
- Dashboard usable pada lebar 360 px, tablet, dan desktop.
- Sinkronisasi satu faskes selama tujuh hari menghasilkan tujuh agregat harian atau error operasional yang dapat ditindaklanjuti.

## 4. Persona dan kebutuhan

| Persona | Kebutuhan utama |
|---|---|
| Super Administrator | Mengelola seluruh konfigurasi, pengguna, peran, dan faskes |
| Administrator | Mengelola integrasi faskes dan menjalankan sinkronisasi |
| Analis | Melihat analitik faskes yang ditugaskan dan menjalankan sinkronisasi |
| Viewer/Pimpinan | Melihat dashboard faskes yang ditugaskan tanpa mengubah data |

## 5. Ruang lingkup

### Termasuk MVP

- Login email dan kata sandi; password disimpan dengan `password_hash()`.
- Pembatasan lima login gagal per kombinasi email/IP selama 15 menit.
- Session regeneration setelah login, CSRF protection, dan audit perubahan penting.
- RBAC: `super_admin`, `admin`, `analyst`, `viewer`.
- Assignment faskes per pengguna untuk role tanpa akses global.
- Master faskes: kode, nama, URL sistem informasi faskes, Cons ID, Secret Key, timezone, dan status.
- Secret Key faskes dienkripsi AES-256-GCM at rest dengan master key dari environment.
- Health check, sinkronisasi manual, dan command terjadwal.
- Kartu total kunjungan, jumlah faskes, rata-rata harian, tren harian, dan peringkat faskes.
- Filter tanggal maksimal 31 hari.
- Status dan catatan hasil sinkronisasi terakhir.
- UI Tabler responsif dan pesan empty/error state.

### Di luar MVP

- Identitas atau rekam klinis pasien.
- Diagnosis, resep, pendapatan, stok, klaim, dan data antrean detail.
- Write-back ke sistem informasi faskes.
- Real-time push/WebSocket.
- SSO/MFA, notifikasi, export, dan visual builder laporan.
- Pengubahan kode sistem informasi faskes di faskes.

## 6. User stories dan kriteria penerimaan

### Autentikasi

- Sebagai pengguna aktif, saya dapat login dan diarahkan ke dashboard.
- Sebagai pengguna nonaktif atau dengan password salah, saya tidak memperoleh session.
- Sebagai sistem, saya membatasi brute force dan meregenerasi session ID saat login.

### Dashboard

- Sebagai viewer, saya hanya melihat agregat faskes yang ditugaskan.
- Saat periode valid dipilih, semua kartu, grafik, dan tabel memakai periode yang sama.
- Rentang tidak valid atau lebih dari 31 hari kembali ke tujuh hari terakhir.
- Saat belum ada data, UI memberi instruksi untuk melakukan sinkronisasi.

### Faskes

- Sebagai admin, saya dapat menambah dan mengubah faskes tanpa melihat secret lama.
- Secret baru minimal 32 karakter dan tersimpan terenkripsi.
- Base URL hanya menerima HTTP/HTTPS tanpa kredensial pada URL; HTTPS wajib pada production.
- Uji koneksi memanggil `/api/v1/health` tanpa mengakses data pasien.
- Sinkronisasi mengambil token baru lalu meminta ringkasan satu hari untuk setiap tanggal.
- Kegagalan sebuah faskes tercatat dan tidak menghapus agregat sukses sebelumnya.

### Hak akses

- Super admin dapat mengelola user dan seluruh faskes.
- Admin dapat mengelola seluruh faskes tetapi tidak mengelola user.
- Analyst dapat melihat dan menyinkronkan hanya faskes assignment.
- Viewer hanya dapat membaca dashboard dan daftar faskes assignment.

## 7. Kontrak data kunjungan

Sumber data di sistem informasi faskes mengikuti ketentuan yang tersedia:

- tabel sumber `reg_periksa`;
- tanggal `tgl_registrasi`;
- hitungan `COUNT(DISTINCT no_rawat)`;
- rentang inklusif;
- baris `stts = 'Batal'` tidak dihitung;
- timezone faskes eksplisit;
- request maksimal 31 hari.

SATUKES memanggil `POST /api/v1/auth/token`, lalu `POST /api/v1/monitoring/kunjungan/summary`. Untuk menghasilkan tren yang konsisten dengan endpoint MVP, scheduler meminta agregat per hari dan melakukan upsert pada `(branch_id, visit_date)`.

## 8. Arsitektur

```text
Browser pengguna
      |
      | session + CSRF + RBAC
      v
SATUKES / CodeIgniter 3.1.13 ----> MySQL (user, faskes, agregat, audit)
      |
      | HTTPS + HMAC + Bearer + AES-GCM
      +------> Sistem Informasi Faskes A /api/v1
      +------> Sistem Informasi Faskes B /api/v1
      +------> Sistem Informasi Faskes N /api/v1
```

Model integrasi adalah pull. Sistem informasi masing-masing faskes tetap menjadi source of truth. Database SATUKES berfungsi sebagai read model/cache agregat agar dashboard tidak bergantung pada ketersediaan seluruh faskes pada saat halaman dibuka.

## 9. Model data

| Tabel | Fungsi |
|---|---|
| `users` | Akun dan status pengguna |
| `roles`, `permissions` | Definisi RBAC |
| `user_roles`, `role_permissions` | Relasi pengguna/peran/permission |
| `branches` | Konfigurasi dan kondisi sinkronisasi faskes |
| `user_branches` | Scope faskes per pengguna |
| `visit_daily` | Agregat kunjungan per faskes dan tanggal |
| `sync_logs` | Riwayat job, durasi, status, dan error aman |
| `login_attempts` | Throttling autentikasi |
| `audit_logs` | Jejak perubahan konfigurasi tanpa secret |

Skema rinci tersedia di `database/schema.sql` dan migration CodeIgniter.

Nama teknis `branches`, `user_branches`, `branch_id`, route `/branches`, dan permission `branches.*` merupakan identifier internal versi awal. Identifier tersebut dipertahankan sebagai lapisan kompatibilitas; seluruh nomenklatur bisnis dan antarmuka pengguna memakai istilah faskes, dengan route utama `/faskes`.

## 10. Keamanan dan privasi

- TLS valid wajib untuk semua sistem informasi faskes production; verifikasi sertifikat tidak dinonaktifkan.
- Cons ID dan secret unik per faskes; jangan memakai ulang kredensial BPJS/SATUSEHAT.
- Secret faskes dienkripsi dengan master key minimal 32 karakter yang tidak masuk Git.
- Request memakai timestamp, nonce UUID v4, request ID, HMAC canonical body, token 15 menit, HKDF-SHA256, serta envelope AES-256-GCM sesuai kontrak v1.
- Tidak menyimpan bearer token, signature, plaintext/ciphertext payload, atau data pasien pada log.
- Query aplikasi menggunakan Query Builder/binding.
- Cookie production harus Secure, HttpOnly, dan SameSite; aplikasi dijalankan di balik HTTPS.
- Backup database dan master key dilakukan terpisah tetapi dipulihkan sebagai satu pasangan.

## 11. Non-functional requirements

- PHP 8.2 pada XAMPP; CodeIgniter 3.1.13 (maintenance release terakhir).
- MySQL/MariaDB dengan InnoDB dan `utf8mb4`.
- Target dashboard dari data lokal: p95 < 500 ms untuk 100 faskes dan periode 31 hari.
- Timeout koneksi API 5 detik; total request 15 detik.
- Operasi sinkronisasi idempotent melalui unique key dan upsert.
- Tampilan mendukung Chrome/Edge/Firefox modern dan breakpoint mobile 360 px.
- Scheduler dipanggil dari OS/Task Scheduler; bukan bergantung pada request pengguna.

## 12. Observability dan operasi

- Catat status, rentang, durasi, jumlah agregat, faskes, dan pesan error aman setiap sinkronisasi.
- Tampilkan waktu/status sinkronisasi terakhir pada daftar faskes.
- Bersihkan `login_attempts` dan log sesuai retention policy organisasi (rekomendasi: login 30 hari, sync/audit minimal 1 tahun).
- Jadwal awal: sinkron tujuh hari terakhir setiap pukul 01:00 untuk menangkap koreksi data di sistem informasi faskes.
- Alert fase berikutnya jika faskes gagal tiga kali berturut-turut atau tidak sinkron lebih dari 24 jam.

## 13. Tahapan delivery

1. **MVP fondasi:** auth, RBAC, master faskes, konektor API, kunjungan, dashboard, migration.
2. **UAT satu faskes:** cocokkan angka dengan sistem informasi faskes, uji clock skew, rotasi secret, timeout, dan mobile.
3. **Rollout:** tambah faskes bertahap, aktifkan scheduler dan monitoring kegagalan.
4. **Fase lanjutan:** pasien unik, poli, penjamin, rawat jalan/inap, export, alert, SSO/MFA setelah kajian privasi.

## 14. Risiko dan mitigasi

| Risiko | Mitigasi |
|---|---|
| Definisi kunjungan berbeda | Kontrak v1 dan UAT query yang sama pada setiap faskes |
| Sistem informasi faskes lambat/tidak tersedia | Timeout, penyimpanan agregat lokal, error per faskes |
| Secret bocor | Enkripsi at rest, redaksi log, kredensial unik, rotasi/revoke |
| Jam server berbeda | NTP dan toleransi maksimal 300 detik pada sistem informasi faskes |
| Angka terlambat berubah | Sinkron ulang rolling tujuh hari dengan upsert |
| Akses data berlebih | Permission dan assignment faskes diuji server-side |
| Master key hilang | Backup key terpisah dan prosedur disaster recovery |

## 15. Definition of done MVP

- Migration dan installer berhasil pada database kosong.
- Login, logout, throttling, CSRF, dan empat role berfungsi.
- Faskes dapat ditambah, diubah, diuji, dan disinkronkan.
- Konektor lolos terhadap Postman collection/endpoint sistem informasi faskes staging.
- Angka satu dan tujuh hari cocok dengan sistem informasi faskes.
- Pembatasan permission dan faskes lolos pengujian negatif.
- Responsive check pada 360, 768, dan 1440 px.
- Tidak ada secret/token/data pasien pada log atau HTML.
- Panduan instalasi, scheduler, backup, dan rotasi secret tersedia.

## 16. Keputusan bisnis yang perlu dikonfirmasi saat UAT

- Apakah status `Batal` selalu dikeluarkan di seluruh faskes.
- Apakah satu `no_rawat` selalu berarti satu kunjungan.
- Apakah rawat jalan dan rawat inap tetap digabung.
- Timezone standar untuk pelaporan lintas zona.
- Jam finalisasi data harian dan kebijakan koreksi historis.
