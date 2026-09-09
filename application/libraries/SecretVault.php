<?php defined('BASEPATH') OR exit('No direct script access allowed');

class SecretVault
{
    private $key;

    public function __construct()
    {
        $CI =& get_instance();
        $CI->config->load('satukes');
        $master = (string) $CI->config->item('satukes_master_key');
        if (strlen($master) < 32) throw new RuntimeException('FASKES_SECRET_MASTER_KEY minimal 32 karakter wajib dikonfigurasi.');
        $this->key = hash('sha256', $master, TRUE);
    }

    public function encrypt($plaintext)
    {
        $iv = random_bytes(12); $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag, 'satukes-secret-v1', 16);
        if ($ciphertext === FALSE) throw new RuntimeException('Gagal mengenkripsi secret.');
        return base64_encode($iv.$tag.$ciphertext);
    }

    public function decrypt($encoded)
    {
        $raw = base64_decode($encoded, TRUE);
        if ($raw === FALSE || strlen($raw) < 29) throw new RuntimeException('Secret faskes tidak valid.');
        $result = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), 'satukes-secret-v1');
        if ($result === FALSE) throw new RuntimeException('Secret faskes tidak dapat didekripsi. Periksa master key.');
        return $result;
    }
}
