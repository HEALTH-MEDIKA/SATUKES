# API Monitoring System Guide

## Overview

Sistem monitoring API ini dirancang untuk melacak dan memantau semua request dari aplikasi pihak ketiga ke API BPJS Anda. Sistem ini menyediakan logging komprehensif, dashboard monitoring, dan alerting untuk memastikan API berjalan dengan baik.

## Fitur Utama

### 1. Logging Komprehensif
- **Request Logging**: Mencatat semua request masuk dengan detail lengkap
- **Response Logging**: Mencatat response yang dikirim kembali
- **Error Logging**: Mencatat semua error dengan detail stack trace
- **Performance Monitoring**: Mencatat waktu eksekusi setiap request
- **User Tracking**: Mencatat username dan IP address pengguna

### 2. Dashboard Monitoring
- **Real-time Statistics**: Statistik request, error rate, dan response time
- **Recent Logs**: Log terbaru dengan detail lengkap
- **Error Monitoring**: Monitoring error yang terjadi
- **Performance Metrics**: Metrik performa API

### 3. Filtering dan Search
- Filter berdasarkan method (GET, POST, PUT, DELETE)
- Filter berdasarkan endpoint
- Filter berdasarkan username
- Filter berdasarkan tanggal
- Filter berdasarkan status code

### 4. Export dan Reporting
- Export log ke CSV
- Generate report statistik
- Backup log otomatis

## Instalasi

### 1. Database Setup

Jalankan SQL berikut untuk membuat tabel monitoring:

```sql
-- File: application/migrations/create_api_logs_table.sql
-- Jalankan query ini di database Anda
```

### 2. Konfigurasi

Edit file `application/config/api_monitoring.php` sesuai kebutuhan:

```php
$config['api_monitoring'] = array(
    'enable_logging' => TRUE,
    'database_logging' => TRUE,
    'log_retention_days' => 30,
    // ... konfigurasi lainnya
);
```

### 3. Load Helper

Tambahkan helper di `application/config/autoload.php`:

```php
$autoload['helper'] = array('api_monitoring');
```

## Penggunaan

### 1. Akses Dashboard

```
URL: http://your-domain/admin/api_monitor
```

### 2. Melihat Logs

```
URL: http://your-domain/admin/api_monitor/logs
```

### 3. Melihat Errors

```
URL: http://your-domain/admin/api_monitor/errors
```

### 4. Statistik

```
URL: http://your-domain/admin/api_monitor/statistics
```

## Struktur File

```
application/
├── controllers/
│   ├── api/bpjs/AntrolFktp.php (Updated with logging)
│   └── admin/ApiMonitor.php (New monitoring controller)
├── models/
│   └── api/ApiLog_model.php (New logging model)
├── views/
│   └── admin/api_monitor/
│       ├── dashboard.php (Dashboard view)
│       ├── logs.php (Logs view)
│       ├── errors.php (Errors view)
│       └── statistics.php (Statistics view)
├── config/
│   └── api_monitoring.php (Monitoring configuration)
├── helpers/
│   └── api_monitoring_helper.php (Monitoring utilities)
└── migrations/
    └── create_api_logs_table.sql (Database migration)
```

## Monitoring Endpoints

Sistem ini memonitor endpoint-endpoint berikut:

- `auth_get` - Authentication GET
- `auth_post` - Authentication POST
- `status_post` - Status antrian POST
- `status_get` - Status antrian GET
- `antrean_post` - Ambil antrian POST
- `sisapeserta_post` - Sisa peserta POST
- `sisapeserta_get` - Sisa peserta GET
- `batal_post` - Batal antrian POST
- `batal_put` - Batal antrian PUT
- `checkin_post` - Check-in POST
- `peserta_post` - Info peserta POST

## Log Format

### Request Log
```json
{
    "timestamp": "2024-01-15 10:30:45",
    "method": "POST",
    "endpoint": "antrean_post",
    "ip_address": "192.168.1.100",
    "user_agent": "PostmanRuntime/7.32.3",
    "request_data": "{\"nomorkartu\":\"1234567890123\",\"nik\":\"1234567890123456\"}",
    "response_data": "{\"response\":{\"nomorantrean\":\"A001\"},\"metadata\":{\"code\":200}}",
    "status_code": 200,
    "execution_time": 0.125,
    "username": "test_user",
    "log_type": "request"
}
```

### Error Log
```json
{
    "timestamp": "2024-01-15 10:30:45",
    "method": "POST",
    "endpoint": "antrean_post",
    "ip_address": "192.168.1.100",
    "user_agent": "PostmanRuntime/7.32.3",
    "error_message": "Nomor Kartu tidak sesuai",
    "request_data": "{\"nomorkartu\":\"123\",\"nik\":\"1234567890123456\"}",
    "username": "test_user",
    "log_type": "error"
}
```

## Alerting

Sistem dapat mengirim alert untuk kondisi berikut:

1. **High Error Rate**: Error rate > 10%
2. **Slow Response Time**: Response time > 5 detik
3. **Service Unavailable**: API tidak dapat diakses

### Konfigurasi Alert

Edit file `application/config/api_monitoring.php`:

```php
$config['alerts'] = array(
    'conditions' => array(
        'error_rate' => array(
            'threshold' => 0.1, // 10% error rate
            'time_window' => 3600, // 1 hour
            'action' => 'email'
        ),
        'response_time' => array(
            'threshold' => 5.0, // 5 seconds
            'time_window' => 300, // 5 minutes
            'action' => 'log'
        )
    )
);
```

## Maintenance

### 1. Cleanup Old Logs

Sistem otomatis membersihkan log lama berdasarkan konfigurasi `log_retention_days`.

### 2. Manual Cleanup

```
URL: http://your-domain/admin/api_monitor/cleanup
```

### 3. Export Logs

```
URL: http://your-domain/admin/api_monitor/export_logs
```

## Security

### 1. Sensitive Data Masking

Sistem otomatis memask data sensitif seperti password, token, dll.

### 2. Access Control

Dashboard monitoring hanya dapat diakses oleh admin yang sudah login.

### 3. IP Filtering

Dapat mengatur IP yang dikecualikan dari logging.

## Troubleshooting

### 1. Log Tidak Muncul

- Periksa konfigurasi `enable_logging` di `api_monitoring.php`
- Periksa permission folder `application/logs/`
- Periksa koneksi database

### 2. Dashboard Error

- Periksa apakah tabel `api_logs` sudah dibuat
- Periksa permission akses admin
- Periksa konfigurasi database

### 3. Performance Issues

- Aktifkan cleanup otomatis
- Kurangi retention period
- Optimasi query database

## API Endpoints untuk Monitoring

### Get Statistics
```
GET /admin/api_monitor/statistics
```

### Get Recent Logs
```
GET /admin/api_monitor/logs?limit=50&page=1
```

### Get Errors
```
GET /admin/api_monitor/errors?limit=50&page=1
```

### Export Logs
```
GET /admin/api_monitor/export_logs?format=csv
```

## Best Practices

1. **Regular Monitoring**: Periksa dashboard secara berkala
2. **Alert Setup**: Aktifkan alert untuk kondisi kritis
3. **Log Retention**: Atur retention period sesuai kebutuhan
4. **Backup**: Backup log secara berkala
5. **Performance**: Monitor response time dan error rate

## Support

Untuk bantuan teknis, hubungi tim development atau buat issue di repository project. 