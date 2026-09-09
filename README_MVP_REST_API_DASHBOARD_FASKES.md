# Konsep MVP REST API Monitoring Sistem Informasi Faskes

## 1. Ringkasan

Dokumen ini adalah rencana MVP untuk menyediakan REST API pada sistem informasi faskes. API dapat dipasang pada puskesmas, klinik, rumah sakit, maupun fasilitas kesehatan lain dan dikonsumsi oleh aplikasi eksternal yang memiliki kredensial sah.

Pada tahap MVP, sistem informasi faskes menyediakan kemampuan untuk:

1. Mendaftarkan konsumen API menggunakan `cons_id` dan `secret_key`.
2. Mengautentikasi konsumen dan menerbitkan access token berumur pendek.
3. Menerima filter rentang tanggal dalam payload terenkripsi.
4. Mengambil total kunjungan pasien dari tabel `reg_periksa`.
5. Mengembalikan hasil agregat dalam payload terenkripsi.

Pembuatan dashboard pusat, UI monitoring, database agregator, scheduler sinkronisasi, dan konektor multi-faskes berada di aplikasi/repository terpisah dan tidak termasuk pekerjaan pada sistem informasi faskes ini.

REST API ditempatkan di `application/controllers/api` karena folder tersebut sudah ada dan proyek telah memakai `chriskacerguis/codeigniter-restserver` pada CodeIgniter 3.1.11.

> Status implementasi: MVP pada dokumen ini telah diimplementasikan pada sistem informasi faskes, termasuk CRUD web admin khusus super-admin. Petunjuk instalasi, provisioning client, spesifikasi signature/enkripsi, contoh client, dan daftar error pada repositori sumber perlu memakai nomenklatur `README_REST_API_MONITORING_FASKES.md`.

## 2. Tujuan MVP

- Menyediakan satu kontrak API yang konsisten untuk seluruh faskes.
- Mengirim hanya data agregat yang dibutuhkan konsumen API, bukan data identitas pasien.
- Menjamin kerahasiaan dan integritas payload dengan HTTPS, request signature, dan authenticated encryption.
- Memisahkan kredensial konsumen REST API dari kredensial API lama/BPJS yang sudah ada.
- Menyediakan fondasi yang dapat ditambah dengan metrik faskes lain tanpa mengubah pola autentikasi.

## 3. Batasan MVP

### Termasuk dalam MVP

- API versioning melalui prefix `/api/v1`.
- Endpoint pengecekan layanan.
- Endpoint autentikasi dan penerbitan access token berumur pendek.
- Endpoint ringkasan total kunjungan berdasarkan rentang tanggal.
- Enkripsi request dan response pada endpoint data.
- Validasi timestamp dan nonce untuk mencegah replay attack.
- Rate limit dasar per `cons_id`.
- Audit log API tanpa mencatat secret, token, atau data mentah.
- CRUD web admin khusus super-admin untuk generate, mengubah, merotasi, mencabut, dan menghapus API client.
- Dokumentasi kontrak API dan contoh pemanggilan untuk aplikasi konsumen.

### Tidak termasuk dalam MVP

- Data identitas pasien seperti nama, NIK, nomor rekam medis, alamat, atau nomor telepon.
- Sinkronisasi seluruh isi tabel sistem informasi faskes.
- Endpoint transaksi tulis dari aplikasi eksternal ke sistem informasi faskes.
- Real-time push/WebSocket.
- Analitik klinis, diagnosis, resep, pendapatan, stok, dan klaim.
- Autentikasi pengguna manusia atau single sign-on aplikasi lain.
- Pembuatan dashboard pusat, form konfigurasi faskes, penyimpanan secret pada aplikasi lain, scheduler pengambilan data, dan tampilan analitik multi-faskes.

## 4. Arsitektur MVP

```text
+--------------------------+
| Aplikasi Konsumen        |
| (di luar repository sistem informasi faskes) |
| - Base URL API           |
| - Cons ID                |
| - Secret Key             |
+------------+-------------+
             |
             | HTTPS + HMAC signature + encrypted payload
             |
      +------+----------------+
      | Sistem Informasi Faskes             |
      | REST API /api/v1       |
      | sumber: reg_periksa    |
      +------------------------+
```

Model komunikasi REST API adalah **pull**:

1. Aplikasi konsumen menghubungi URL REST API sistem informasi faskes.
2. Aplikasi konsumen memperoleh access token dari sistem informasi faskes.
3. Aplikasi konsumen meminta ringkasan kunjungan untuk rentang tanggal tertentu.
4. Sistem informasi faskes memvalidasi request, membaca agregat `reg_periksa`, lalu mengembalikan response terenkripsi.

Setiap instalasi sistem informasi faskes tetap menjadi sumber kebenaran dan tidak membuka akses database langsung. Cara aplikasi eksternal menyimpan, menggabungkan, menjadwalkan, dan menampilkan hasil bukan bagian dari MVP REST API ini.

## 5. Prinsip Keamanan

### 5.1 HTTPS tetap wajib

Enkripsi payload tidak menggantikan TLS. Semua endpoint production wajib menggunakan HTTPS dengan sertifikat valid. HTTP hanya diperbolehkan pada development lokal yang terisolasi.

Konfigurasi `force_https` pada `application/config/rest.php` saat ini belum aktif. Aktivasi perlu dilakukan saat deployment production atau dipaksa melalui reverse proxy/web server.

### 5.2 Kredensial per faskes

Setiap instalasi sistem informasi faskes membuat pasangan kredensial khusus untuk aplikasi konsumen yang diizinkan:

- `cons_id`: identifier publik, unik, dan tidak mudah ditebak.
- `secret_key`: secret acak minimal 32 byte dari CSPRNG, lalu ditampilkan satu kali saat provisioning.

Jangan menggunakan ulang secret BPJS, SATUSEHAT, user sistem informasi faskes, atau secret dari API lama. Jika satu faskes bocor, kredensial faskes lain tidak boleh ikut terdampak.

### 5.3 Secret at rest

- Pada sistem informasi faskes, `secret_key` perlu tersedia untuk verifikasi HMAC dan dekripsi payload. Simpan dalam bentuk terenkripsi, dengan master key dari environment server atau file lokal terlindungi untuk instalasi single-server.
- Opsi lokal dibuat otomatis melalui web admin/CLI pada `application/config/monitoring_api_secret.php`, diabaikan Git, dan tidak memerlukan restart Apache. Environment tetap disarankan untuk production multi-server.
- Master key dan secret tidak boleh masuk Git, file log, response error, atau dokumentasi. File key lokal wajib dibackup secara aman di luar repository.
- Password hashing tidak dapat menggantikan penyimpanan terenkripsi untuk kasus ini karena server perlu memperoleh kembali key untuk HMAC dan AES.

### 5.4 Request signature

Setiap request yang dilindungi membawa header:

```http
X-Cons-Id: monitoring-client-001
X-Timestamp: 1788842400
X-Nonce: 60871af4-93bf-4a78-bab7-e759344f6cc8
X-Signature: <base64-hmac-sha256>
X-Request-Id: 95113868-b7d0-4206-8364-d32d5036dac5
```

Canonical string yang ditandatangani:

```text
HTTP_METHOD\n
REQUEST_PATH\n
CONS_ID\n
TIMESTAMP\n
NONCE\n
SHA256_RAW_REQUEST_BODY
```

`X-Signature` dihitung dengan HMAC-SHA256 menggunakan signing key yang diturunkan dari `secret_key`. Verifikasi wajib memakai `hash_equals()`.

Aturan validasi:

- Selisih waktu maksimum disarankan 300 detik.
- Kombinasi `cons_id + nonce` hanya boleh dipakai satu kali dalam jendela tersebut.
- Server menolak signature, timestamp, nonce, atau client yang tidak valid dengan HTTP `401`.

### 5.5 Enkripsi payload

Gunakan AES-256-GCM, bukan pola AES-CBC dengan IV tetap yang terdapat pada kode lama.

- Encryption key diturunkan dari `secret_key` dengan HKDF-SHA256 dan context khusus `his-monitoring-api-encryption-v1`.
- IV dibuat acak 12 byte untuk setiap payload.
- Authentication tag berukuran 16 byte.
- `ciphertext`, `iv`, dan `tag` dikirim dalam Base64.
- AAD mengikat versi protokol, `cons_id`, timestamp, nonce, dan request ID.
- IV tidak boleh digunakan ulang dengan key yang sama.

Nilai `his-monitoring-api-*` dan `service: his-monitoring-api` dipertahankan sebagai identifier protokol v1 agar signature serta dekripsi pada instalasi lama tidak rusak. Nilai tersebut bukan nomenklatur bisnis yang ditampilkan kepada pengguna.

Envelope terenkripsi:

```json
{
  "version": "1",
  "alg": "A256GCM",
  "iv": "<base64>",
  "tag": "<base64>",
  "ciphertext": "<base64>"
}
```

Seluruh business payload pada endpoint monitoring menggunakan envelope ini, baik request maupun response. Metadata HTTP umum dan kode error aman boleh tetap plaintext supaya operasional lebih mudah.

### 5.6 Access token

- Token berupa nilai opaque acak minimal 32 byte, bukan token dengan secret hardcoded.
- Masa berlaku awal: 15 menit.
- Database sistem informasi faskes hanya menyimpan hash SHA-256 token.
- Token dikirim melalui `Authorization: Bearer <token>`.
- Token terikat ke `cons_id`, status client, scope, dan waktu kedaluwarsa.
- Token dapat dicabut saat kredensial dinonaktifkan atau dirotasi.

Signature per request tetap digunakan bersama Bearer token. Token membatasi sesi dan scope; HMAC membuktikan bahwa request dibuat oleh pemegang secret serta melindungi body dari perubahan.

## 6. Alur Autentikasi dan Pengambilan Data

```text
Aplikasi Konsumen                 Sistem Informasi Faskes
      |                                |
      | POST /api/v1/auth/token        |
      | headers + signature            |
      |------------------------------->|
      |                                | validasi client/timestamp/nonce/HMAC
      | encrypted access token         |
      |<-------------------------------|
      | decrypt token                  |
      |                                |
      | POST /api/v1/monitoring/       |
      | kunjungan/summary              |
      | Bearer + signature + envelope  |
      |------------------------------->|
      |                                | autentikasi, decrypt, validasi tanggal
      |                                | query agregat reg_periksa
      | encrypted aggregate response   |
      |<-------------------------------|
      | decrypt dan tampilkan          |
```

## 7. Kontrak Endpoint MVP

Base URL contoh:

```text
https://sistem-faskes-a.example.id/api/v1
```

### 7.1 Health check

```http
GET /api/v1/health
```

Tujuan: memastikan aplikasi API dapat dijangkau. Endpoint ini tidak membaca data pasien dan tidak memerlukan token.

Response:

```json
{
  "status": true,
  "message": "API tersedia",
  "data": {
    "service": "his-monitoring-api",
    "api_version": "v1",
    "server_time": "2026-09-08T10:00:00+07:00"
  }
}
```

Health check tidak boleh mengungkap versi PHP, detail database, stack trace, path server, atau secret.

### 7.2 Mendapatkan access token

```http
POST /api/v1/auth/token
Content-Type: application/json
X-Cons-Id: <cons_id>
X-Timestamp: <unix_timestamp>
X-Nonce: <uuid_v4>
X-Signature: <signature>
X-Request-Id: <uuid_v4>
```

Body dapat berupa JSON kosong `{}`. Response sukses menggunakan envelope terenkripsi. Hasil setelah didekripsi:

```json
{
  "access_token": "<opaque_token>",
  "token_type": "Bearer",
  "expires_in": 900,
  "scope": ["visits:read"]
}
```

HTTP status:

- `200`: token berhasil dibuat.
- `400`: format request tidak valid.
- `401`: client, timestamp, nonce, atau signature tidak valid.
- `403`: client nonaktif atau scope tidak diperbolehkan.
- `429`: rate limit tercapai.
- `500`: kesalahan internal tanpa detail sensitif.

### 7.3 Ringkasan total kunjungan

```http
POST /api/v1/monitoring/kunjungan/summary
Authorization: Bearer <access_token>
Content-Type: application/json
X-Cons-Id: <cons_id>
X-Timestamp: <unix_timestamp>
X-Nonce: <uuid_v4>
X-Signature: <signature>
X-Request-Id: <uuid_v4>
```

Body request adalah envelope terenkripsi. Plaintext setelah didekripsi:

```json
{
  "date_from": "2026-09-01",
  "date_to": "2026-09-08"
}
```

Aturan input:

- Format tanggal wajib `YYYY-MM-DD`.
- `date_from` tidak boleh lebih besar dari `date_to`.
- Rentang maksimum MVP disarankan 31 hari.
- Tanggal dihitung menurut timezone faskes, default `Asia/Jakarta` dan harus dibuat konsisten di seluruh faskes.
- Tanggal masa depan ditolak atau dibatasi sampai tanggal server.

Response HTTP tetap memakai envelope terenkripsi. Plaintext setelah didekripsi:

```json
{
  "facility": {
    "code": "FASKES-A",
    "name": "Puskesmas Melati",
    "timezone": "Asia/Jakarta"
  },
  "period": {
    "date_from": "2026-09-01",
    "date_to": "2026-09-08"
  },
  "metrics": {
    "total_visits": 842
  },
  "generated_at": "2026-09-08T10:01:15+07:00"
}
```

Nama objek `facility` adalah nomenklatur kanonis. Implementasi sumber yang sudah terlanjur memakai objek `clinic` pada kontrak v1 perlu menyediakan alias selama masa transisi. Konektor SATUKES tetap kompatibel karena membaca metrik dari `metrics.total_visits`, bukan dari nama objek identitas tersebut.

### 7.4 Format error

Error sebelum payload berhasil didekripsi dapat dikirim sebagai JSON plaintext yang aman:

```json
{
  "status": false,
  "message": "Request tidak valid",
  "error": {
    "code": "INVALID_DATE_RANGE"
  },
  "request_id": "95113868-b7d0-4206-8364-d32d5036dac5"
}
```

Jangan mengirim query SQL, stack trace, plaintext hasil dekripsi, detail key, atau nama konfigurasi internal.

## 8. Definisi Total Kunjungan

Definisi MVP yang direkomendasikan:

- Sumber: tabel `reg_periksa`.
- Kolom tanggal: `tgl_registrasi`.
- Unit hitung: `COUNT(DISTINCT no_rawat)`.
- Rentang: inklusif terhadap `date_from` dan `date_to`.
- Registrasi dengan `stts = 'Batal'` tidak dihitung.

Query konseptual:

```sql
SELECT COUNT(DISTINCT no_rawat) AS total_visits
FROM reg_periksa
WHERE tgl_registrasi >= ?
  AND tgl_registrasi <= ?
  AND stts <> 'Batal';
```

Parameter harus dikirim melalui Query Builder atau binding, bukan concatenation SQL.

Catatan bisnis yang harus disetujui sebelum produksi:

- Apakah registrasi batal memang harus dikeluarkan.
- Apakah satu `no_rawat` selalu merepresentasikan satu kunjungan.
- Apakah rawat jalan dan rawat inap dihitung bersama.
- Timezone baku antar-faskes.

MVP menggunakan definisi di atas agar angka antar-faskes konsisten. Breakdown rawat jalan/rawat inap, pasien unik, kunjungan harian, dan kunjungan per poli menjadi kandidat fase berikutnya.

## 9. Struktur Kode yang Direncanakan

```text
application/
├── controllers/
│   ├── admin/
│   │   └── MonitoringApi.php
│   ├── cli/
│   │   └── MonitoringApi.php
│   └── api/
│       └── v1/
│           ├── Auth.php
│           ├── Health.php
│           └── Kunjungan.php
├── core/
│   └── HM_MonitoringApiController.php
├── libraries/
│   ├── MonitoringApiCrypto.php
│   └── MonitoringApiSecurity.php
├── models/
│   └── api/
│       ├── MonitoringApiClientModel.php
│       ├── MonitoringApiTokenModel.php
│       ├── MonitoringApiNonceModel.php
│       ├── MonitoringApiAuditModel.php
│       ├── MonitoringApiAdminLogModel.php
│       └── KunjunganApiModel.php
└── config/
    └── monitoring_api.php
```

Tanggung jawab:

- `HM_MonitoringApiController`: response JSON, request ID, validasi header umum, dan error handling.
- `MonitoringApiCrypto`: HKDF, AES-256-GCM encrypt/decrypt, validasi envelope, dan AAD.
- `MonitoringApiSecurity`: verifikasi HMAC, timestamp, nonce, Bearer token, scope, dan rate limit.
- `Auth`: menerbitkan access token.
- `Health`: status minimal API.
- `Kunjungan`: validasi request dan orkestrasi endpoint kunjungan.
- `KunjunganApiModel`: hanya menangani query agregat `reg_periksa`.

Controller menggunakan pola method RestServer seperti `token_post()` dan `summary_post()`. Nama helper internal menggunakan camelCase sesuai standar proyek.

Rute yang direncanakan pada `application/config/routes.php`:

```php
$route['api/v1/health']['get'] = 'api/v1/Health/index';
$route['api/v1/auth/token']['post'] = 'api/v1/Auth/token';
$route['api/v1/monitoring/kunjungan/summary']['post'] = 'api/v1/Kunjungan/summary';
```

## 10. Struktur Database Pendukung

Gunakan migration CodeIgniter dan sediakan method `down()` untuk rollback.

### 10.1 `monitoring_api_clients`

| Field | Kegunaan |
|---|---|
| `id` | Primary key |
| `cons_id` | Identifier client, unique index |
| `client_name` | Nama aplikasi konsumen/integrator |
| `secret_ciphertext` | Secret yang terenkripsi at rest |
| `secret_key_version` | Versi key untuk rotasi |
| `scopes` | Scope yang diizinkan, awalnya `visits:read` |
| `status` | `active`, `inactive`, atau `revoked` |
| `allowed_ips` | Allowlist IP opsional |
| `rate_limit_per_minute` | Batas request per menit |
| `last_used_at` | Terakhir berhasil digunakan |
| `created_at`, `updated_at` | Audit waktu |

### 10.2 `monitoring_api_tokens`

| Field | Kegunaan |
|---|---|
| `id` | Primary key |
| `client_id` | Foreign key ke client |
| `token_hash` | SHA-256 dari token opaque, unique index |
| `scopes` | Scope token |
| `expires_at` | Waktu kedaluwarsa, index |
| `revoked_at` | Waktu pencabutan opsional |
| `created_at` | Waktu penerbitan |

### 10.3 `monitoring_api_nonces`

| Field | Kegunaan |
|---|---|
| `id` | Primary key |
| `client_id` | Foreign key ke client |
| `nonce_hash` | Hash nonce |
| `expires_at` | Waktu aman untuk dihapus |
| `created_at` | Waktu penerimaan |

Berikan unique index gabungan pada `client_id + nonce_hash`. Jika tersedia Redis, nonce dan rate limit lebih baik dipindahkan ke Redis pada fase hardening; database cukup untuk MVP.

### 10.4 `monitoring_api_audit_logs`

| Field | Kegunaan |
|---|---|
| `id` | Primary key |
| `request_id` | ID korelasi request, unique index |
| `client_id` | Client pemanggil, nullable jika auth gagal |
| `endpoint` | Route API |
| `http_method` | Method HTTP |
| `http_status` | Status response |
| `duration_ms` | Durasi proses |
| `ip_address` | IP asal sesuai kebijakan proxy |
| `error_code` | Kode error aman |
| `created_at` | Waktu request, index |

Audit log tidak menyimpan Authorization header, signature, secret, plaintext/ciphertext payload, atau data pasien.

### 10.5 `monitoring_api_admin_logs`

| Field | Kegunaan |
|---|---|
| `id` | Primary key |
| `client_id` | Client yang dikelola, nullable setelah client dihapus |
| `actor_nik` | NIK admin yang melakukan aksi |
| `action` | Create, update, rotate, revoke, atau delete |
| `ip_address` | IP admin |
| `metadata` | Metadata aman tanpa secret |
| `created_at` | Waktu aksi |

Nama file migration direncanakan mengikuti nomor berikut yang tersedia, misalnya:

```text
application/migrations/20260908140000_create_monitoring_api_tables.php
```

Sebelum menambahkan index ke tabel inti `reg_periksa`, jalankan `SHOW INDEX FROM reg_periksa`. Jika index untuk `tgl_registrasi` belum ada dan volume data besar, penambahannya harus dijadwalkan pada maintenance window serta diuji terlebih dahulu.

## 11. Konfigurasi REST API pada sistem informasi faskes

Konfigurasi server disimpan di `application/config/monitoring_api.php`, sedangkan nilai rahasia berasal dari environment server.

Konfigurasi minimum:

```php
$config['monitoring_api_facility_code'] = 'FASKES-A';
$config['monitoring_api_facility_name'] = 'Puskesmas Melati';
$config['monitoring_api_timezone'] = 'Asia/Jakarta';
$config['monitoring_api_token_ttl'] = 900;
$config['monitoring_api_clock_skew'] = 300;
$config['monitoring_api_max_date_range_days'] = 31;
$config['monitoring_api_auth_rate_limit'] = 10;
$config['monitoring_api_data_rate_limit'] = 60;
```

Untuk instalasi yang sudah berjalan, `monitoring_api_clinic_code` dan `monitoring_api_clinic_name` dapat dibaca sebagai alias sementara. Konfigurasi baru harus memakai nama `monitoring_api_facility_*`.

Environment minimum:

```text
MONITORING_API_MASTER_KEY=<random-secret-minimum-32-byte>
```

Aturan konfigurasi sistem informasi faskes:

- Aplikasi gagal tertutup (`fail closed`) jika master key tidak tersedia atau tidak valid.
- Nilai environment tidak boleh dicatat ke log atau masuk source control.
- Identitas faskes pada response berasal dari konfigurasi server, bukan input konsumen.
- Setiap konsumen didaftarkan pada tabel client dengan scope dan status eksplisit.
- Allowed IP dapat diterapkan per client setelah alamat sumber production diketahui.
- Konfigurasi API lama tidak diubah dan kredensial lama tidak digunakan ulang.

## 12. Rate Limit dan Ketersediaan

Rekomendasi awal MVP:

- Auth token: maksimum 10 request per menit per `cons_id` dan IP.
- Ringkasan kunjungan: maksimum 60 request per menit per `cons_id`.
- Query timeout dan HTTP timeout ditentukan eksplisit.
- Batasi rentang query maksimum 31 hari untuk menjaga beban database.
- Response dapat diberi cache singkat per client dan rentang tanggal jika hasil pengujian menunjukkan kebutuhan.
- Server mengembalikan `Retry-After` saat rate limit tercapai.
- Kegagalan dependency/database menghasilkan error generik dan tercatat dengan `request_id`.

## 13. Rencana Implementasi

### Fase 0 — Validasi kebutuhan dan data

- Sepakati definisi total kunjungan, status batal, timezone, dan batas rentang tanggal.
- Periksa struktur serta index aktual tabel `reg_periksa` pada satu database staging.
- Tentukan domain/IP aplikasi konsumen yang diizinkan mengakses REST API.
- Pastikan TLS tersedia untuk setiap sistem informasi faskes.

### Fase 1 — Fondasi keamanan

- Tambahkan konfigurasi environment dan validasi keberadaan master key.
- Buat migration client, token, nonce, dan audit log.
- Buat library crypto dan security.
- Buat command/prosedur provisioning, deaktivasi, dan rotasi kredensial.
- Tambahkan unit test terhadap encryption round-trip, tampered tag, signature, timestamp, nonce replay, dan token expiry.

### Fase 2 — Endpoint API

- Buat base controller API v1.
- Implementasikan health check.
- Implementasikan auth token.
- Implementasikan model query kunjungan dengan Query Builder/binding.
- Implementasikan endpoint ringkasan kunjungan dan format error konsisten.
- Tambahkan route API tanpa mengubah route API lama.

### Fase 3 — Dokumentasi integrasi

- Buat `readme/README_REST_API_MONITORING_FASKES.md` yang berisi kontrak final.
- Dokumentasikan canonical string, header, algoritma key derivation, AAD, contoh encrypt/decrypt, dan daftar error.
- Sertakan contoh cURL untuk health/auth serta contoh PHP/JavaScript server-side untuk signature dan crypto.
- Siapkan Postman collection dengan script pre-request tanpa menyimpan secret production.
- Dokumentasikan prosedur provisioning, rotasi, revoke, dan troubleshooting clock skew.

### Fase 4 — Hardening dan deployment REST API

- Terapkan HTTPS production dan trusted proxy yang benar.
- Aktifkan rate limit, allowed IP opsional, audit log, dan pembersihan token/nonce kedaluwarsa.
- Pastikan endpoint API tidak bergantung pada session login pengguna sistem informasi faskes.
- Pastikan secret, token, signature, payload, dan stack trace tidak masuk log.
- Siapkan prosedur backup, rollback migration, revoke client, dan rotasi key.

### Fase 5 — UAT dan rollout

- Uji lebih dahulu pada satu faskes staging.
- Cocokkan hasil API dengan query/laporan sistem informasi faskes pada rentang tanggal yang sama.
- Uji pemanggilan dari aplikasi konsumen menggunakan kredensial staging.
- Uji rotasi key tanpa downtime yang tidak perlu.
- Aktifkan monitoring error rate, latency, auth failure, replay attempt, dan token issuance.
- Terapkan modul REST API yang sama secara bertahap pada setiap instalasi sistem informasi faskes.

## 14. Skenario Pengujian Minimum

### Fungsional

- Rentang satu hari menghasilkan angka yang sama dengan sumber sistem informasi faskes.
- Rentang beberapa hari bersifat inklusif.
- Registrasi `Batal` tidak dihitung.
- Rentang kosong tetap mengembalikan `total_visits: 0`.
- Format tanggal salah dan rentang lebih dari 31 hari ditolak.

### Keamanan

- `cons_id`, signature, token, tag, atau ciphertext salah ditolak.
- Perubahan satu byte pada body menyebabkan signature/dekripsi gagal.
- Nonce yang dipakai ulang ditolak.
- Timestamp kedaluwarsa atau terlalu jauh ke depan ditolak.
- Token kedaluwarsa/revoked dan scope salah ditolak.
- Client inactive tidak dapat memperoleh token.
- Rate limit menghasilkan HTTP `429`.
- Log tidak mengandung secret, Bearer token, payload, atau data pasien.

### Performa dan ketahanan

- Query menggunakan index tanggal yang sesuai.
- Target awal response endpoint agregat di jaringan lokal: p95 di bawah 1 detik, setelah kondisi database disepakati.
- Endpoint tetap memberikan format error yang konsisten saat database atau dependency sementara tidak tersedia.
- Timeout, retry, dan clock skew menghasilkan error yang dapat ditindaklanjuti.

## 15. Kriteria Penerimaan MVP

MVP dianggap selesai apabila:

- sistem informasi faskes dapat mendaftarkan konsumen menggunakan `cons_id` dan `secret_key` yang dapat dirotasi/dicabut.
- Auth token hanya berhasil dengan signature, timestamp, dan nonce valid.
- Request filter berhasil didekripsi oleh sistem informasi faskes dan response kunjungan terenkripsi AES-256-GCM dapat didekripsi oleh contoh client pengujian.
- Endpoint hanya mengembalikan data agregat tanpa identitas pasien.
- Total kunjungan cocok dengan data sumber untuk seluruh skenario UAT yang disetujui.
- HTTPS, rate limit, audit log, dan penanganan replay aktif di production.
- Dokumentasi integrasi dapat digunakan untuk memasang faskes baru tanpa membaca source code.
- Tidak ada perubahan apa pun di folder `/hmadmin`.

## 16. Risiko dan Mitigasi

| Risiko | Mitigasi |
|---|---|
| Definisi kunjungan berbeda antar-faskes | Tetapkan definisi query dan timezone yang sama dalam kontrak v1 |
| Secret bocor | Secret unik per faskes, encrypted at rest, redaction log, rotasi dan revoke |
| Replay request | Validasi timestamp dan nonce sekali pakai |
| Payload diubah | HMAC request dan authentication tag AES-GCM |
| Request berlebihan membebani sistem informasi faskes | Batas rentang, rate limit, query timeout, dan cache agregat opsional |
| Database sistem informasi faskes sementara tidak tersedia | Error generik, request ID, timeout, audit log, dan health monitoring |
| Jam server dan konsumen berbeda | NTP pada kedua sistem dan toleransi clock skew terbatas |
| Query lambat | Audit index, `COUNT(DISTINCT no_rawat)`, pengujian EXPLAIN, maintenance terjadwal |
| API lama memiliki secret hardcoded | Buat modul keamanan baru dan jangan reuse implementation lama |
| Enkripsi dianggap pengganti TLS | HTTPS diwajibkan dan diverifikasi saat deployment |

## 17. Pengembangan Setelah MVP

Urutan kandidat pengembangan:

1. Breakdown kunjungan rawat jalan dan rawat inap.
2. Grafik kunjungan harian.
3. Total pasien unik tanpa mengirim identitas pasien.
4. Breakdown per poli, dokter, penjamin, atau status kunjungan setelah kajian privasi.
5. Cache agregasi pada sistem informasi faskes untuk rentang yang sering diminta.
6. Redis untuk rate limit, nonce, dan distributed token handling.
7. mTLS atau private network/VPN antar-server untuk lapisan keamanan tambahan.
8. Alert operasional saat error rate atau latency REST API melewati ambang batas.
9. Versi API berikutnya tanpa mengubah kontrak `/api/v1`.

## 18. Keputusan Awal yang Direkomendasikan

- Gunakan folder `application/controllers/api/v1`.
- Gunakan `RestController` yang sudah menjadi dependency proyek.
- Sediakan pull API untuk aplikasi backend eksternal yang diotorisasi.
- Gunakan HTTPS + HMAC-SHA256 + nonce/timestamp + opaque Bearer token + AES-256-GCM.
- Gunakan credential unik per instalasi faskes.
- Hitung `COUNT(DISTINCT no_rawat)` dari `reg_periksa` dan keluarkan status `Batal`.
- Batasi rentang MVP maksimum 31 hari.
- Jangan mengirim identitas pasien untuk kebutuhan monitoring agregat.
- Jangan memodifikasi atau memasang apa pun di `/hmadmin`.
