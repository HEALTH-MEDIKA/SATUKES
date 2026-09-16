# Katalog API Monitoring Kunjungan v1

## Ringkasan

Katalog ini menyediakan delapan statistik kunjungan tanpa mengirim data identitas pasien. Semua endpoint menggunakan method `POST`, scope `visits:read`, signature HMAC, Bearer token, serta request dan response terenkripsi AES-256-GCM sesuai `README_REST_API_MONITORING_KLINIK.md`.

Base path:

```text
/api/v1/monitoring/kunjungan
```

## Filter Request

Plaintext sebelum dienkripsi sama untuk seluruh endpoint:

```json
{
  "date_from": "2026-09-01",
  "date_to": "2026-09-10"
}
```

Tanggal memakai kolom `reg_periksa.tgl_registrasi`, kecuali katalog obat yang memakai `resep_obat.tgl_peresepan`. Rentang bersifat inklusif, format wajib `YYYY-MM-DD`, tidak boleh melewati tanggal server, dan maksimum default 31 hari.

## Daftar Endpoint

| Endpoint | Isi `metrics` |
|---|---|
| `/dirujuk` | `total_referred_patients` |
| `/pasien-baru-lama` | `total_patients`, `new_patients`, `returning_patients` |
| `/cara-bayar` | `total_patients`, `payment_methods[]` |
| `/diagnosa-terbanyak` | `limit`, `diagnoses[]` |
| `/obat-terbanyak` | `limit`, `medicines[]` |
| `/rawat-jalan` | `total_outpatient_visits` |
| `/rawat-inap` | `total_inpatient_visits` |
| `/gawat-darurat` | `total_emergency_patients` |

Seluruh agregasi kunjungan menghitung `COUNT(DISTINCT no_rawat)`. Data dengan `reg_periksa.stts = 'Batal'` tidak dihitung, kecuali filter dirujuk yang secara khusus hanya mengambil `stts = 'Dirujuk'`.

## Struktur Response

Plaintext response setelah envelope didekripsi:

```json
{
  "catalog": {
    "code": "new-returning-patients",
    "name": "Pasien Baru dan Lama"
  },
  "clinic": {
    "code": "KLINIK-A",
    "name": "Klinik A",
    "timezone": "Asia/Jakarta"
  },
  "period": {
    "date_from": "2026-09-01",
    "date_to": "2026-09-10"
  },
  "metrics": {
    "total_patients": 125,
    "new_patients": 40,
    "returning_patients": 85
  },
  "generated_at": "2026-09-10T15:30:00+07:00"
}
```

## Definisi Metrik

### Pasien Dirujuk

`POST /api/v1/monitoring/kunjungan/dirujuk`

Menghitung registrasi dengan `reg_periksa.stts = 'Dirujuk'`.

### Pasien Baru dan Lama

`POST /api/v1/monitoring/kunjungan/pasien-baru-lama`

Mengelompokkan registrasi berdasarkan `reg_periksa.status_poli = 'Baru'` dan `reg_periksa.status_poli = 'Lama'`. `total_patients` merupakan penjumlahan kedua kelompok tersebut.

### Pasien Berdasarkan Cara Bayar

`POST /api/v1/monitoring/kunjungan/cara-bayar`

Menggabungkan `reg_periksa.kd_pj` dengan `penjab.kd_pj`, lalu mengembalikan setiap `payment_code`, `payment_name`, dan `total_patients`. Hasil diurutkan dari jumlah pasien terbesar.

Contoh isi `metrics`:

```json
{
  "total_patients": 125,
  "payment_methods": [
    {
      "payment_code": "BPJ",
      "payment_name": "BPJS Kesehatan",
      "total_patients": 90
    }
  ]
}
```

### 10 Diagnosa Terbanyak

`POST /api/v1/monitoring/kunjungan/diagnosa-terbanyak`

Menggabungkan `diagnosa_pasien` dengan `reg_periksa` dan `penyakit`. Satu diagnosis dihitung satu kali per `no_rawat`, kemudian diurutkan berdasarkan `total_patients` terbesar dan dibatasi 10 baris.

```json
{
  "limit": 10,
  "diagnoses": [
    {
      "diagnosis_code": "J06.9",
      "diagnosis_name": "Acute upper respiratory infection, unspecified",
      "total_patients": 32,
      "rank": 1
    }
  ]
}
```

### 10 Obat Terbanyak

`POST /api/v1/monitoring/kunjungan/obat-terbanyak`

Menggabungkan `resep_dokter`, `resep_obat`, `databarang`, dan `reg_periksa`. Peringkat ditentukan dari `SUM(resep_dokter.jml)` terbesar. `total_prescriptions` menunjukkan jumlah resep berbeda yang memuat obat tersebut.

```json
{
  "limit": 10,
  "medicines": [
    {
      "medicine_code": "OBT001",
      "medicine_name": "Paracetamol 500 mg",
      "total_quantity": 240,
      "total_prescriptions": 48,
      "rank": 1
    }
  ]
}
```

### Kunjungan Rawat Jalan

`POST /api/v1/monitoring/kunjungan/rawat-jalan`

Menghitung registrasi dengan `kd_poli NOT IN ('IGD', 'IGDK')` dan `status_lanjut <> 'Ranap'`.

### Kunjungan Rawat Inap

`POST /api/v1/monitoring/kunjungan/rawat-inap`

Menghitung registrasi dengan `status_lanjut = 'Ranap'` berdasarkan tanggal registrasi awal pasien.

### Pasien Gawat Darurat

`POST /api/v1/monitoring/kunjungan/gawat-darurat`

Menghitung registrasi dengan `kd_poli IN ('IGD', 'IGDK')`, termasuk pasien IGD yang kemudian dilanjutkan ke rawat inap.

## Postman

Impor file berikut:

1. `readme/postman/HIS_MONITORING_API_V1.postman_collection.json`
2. `readme/postman/HIS_MONITORING_API_V1.postman_environment.json`

Isi `base_url`, `cons_id`, dan `secret_key`, jalankan **Dapatkan Access Token**, lalu panggil endpoint pada folder **Katalog Monitoring**. Hasil plaintext tersedia pada tab Visualize dan variable `last_decrypted_response`.
