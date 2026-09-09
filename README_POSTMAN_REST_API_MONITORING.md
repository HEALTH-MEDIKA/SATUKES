# Pengujian REST API Monitoring dengan Postman

## File impor

Impor kedua file berikut ke Postman:

1. `postman/FASKES_MONITORING_API_V1.postman_collection.json`
2. `postman/FASKES_MONITORING_API_V1.postman_environment.json`

Collection tidak memakai package NPM. HKDF-SHA256, HMAC-SHA256, dan AES-256-GCM diproses menggunakan Web Crypto (`crypto.subtle`) bawaan Postman. Gunakan Postman Desktop versi terbaru jika runtime lama belum menyediakan Web Crypto.

Jika sebelumnya sudah mengimpor versi yang memakai `node-forge`, hapus collection lama lalu impor ulang file collection. Pastikan nama yang tampil adalah **Faskes Monitoring API v1 - Web Crypto**. Environment lama tetap dapat digunakan sehingga `cons_id` dan `secret_key` tidak perlu diisi ulang.

## Persiapan

1. Buka `/admin/monitoring-api` sebagai super-admin.
2. Buat API Client dan salin `Cons ID` serta `Secret Key` yang hanya ditampilkan sekali.
3. Di Postman, pilih environment **Sistem Informasi Faskes Monitoring API - Local**.
4. Isi `base_url`, `cons_id`, dan `secret_key` pada **Current value** environment.
5. Jangan menambahkan garis miring di akhir `base_url`. Contoh lokal: `http://localhost/sistem-faskes`.

`date_from` dan `date_to` boleh dikosongkan. Collection otomatis memakai tujuh hari terakhir. Jika diisi, gunakan format `YYYY-MM-DD` dengan rentang maksimum 31 hari dan tidak melewati tanggal server.

## Urutan pengujian

Jalankan request sesuai urutan:

1. **Health Check** — memastikan API siap.
2. **Dapatkan Access Token** — membuat HMAC, mendekripsi response, lalu menyimpan `access_token` secara otomatis.
3. **Ringkasan Kunjungan** — mengenkripsi filter tanggal, mengirim Bearer token, lalu mendekripsi hasil.

Response HTTP dari endpoint terlindungi memang berbentuk envelope `iv`, `tag`, dan `ciphertext`. Hasil plaintext dapat dilihat pada tab **Visualize**, Postman Console, atau variable environment `last_decrypted_response`.

## Keamanan

- Jangan export environment yang sudah berisi `secret_key` atau `access_token` untuk dibagikan.
- Jangan menyimpan kredensial asli sebagai Initial value pada workspace bersama.
- Gunakan HTTPS untuk server production.
- Jika secret dirotasi melalui web admin, perbarui `secret_key` pada Postman dan jalankan ulang request token.
