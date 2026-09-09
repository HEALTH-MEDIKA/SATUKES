<?php defined('BASEPATH') OR exit('No direct script access allowed');

$config['satukes_name'] = 'SATUKES';
$config['satukes_timezone'] = 'Asia/Jakarta';
$config['satukes_sync_days'] = 7;
$config['satukes_api_timeout'] = 15;
$config['satukes_max_date_range_days'] = 31;
// CLINIC_SECRET_MASTER_KEY tetap didukung agar instalasi lama tidak terputus.
$config['satukes_master_key'] = getenv('FASKES_SECRET_MASTER_KEY') ?: (getenv('CLINIC_SECRET_MASTER_KEY') ?: (getenv('APP_KEY') ?: 'change-this-development-key-before-production'));
