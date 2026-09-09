<?php defined('BASEPATH') OR exit('No direct script access allowed');

class FaskesApiClient
{
    private function uuid()
    {
        $d = random_bytes(16); $d[6] = chr((ord($d[6]) & 0x0f) | 0x40); $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }

    private function keys($secret, $consId)
    {
        return array(
            'signing' => hash_hkdf('sha256', $secret, 32, 'his-monitoring-api-signing-v1', $consId),
            'encryption' => hash_hkdf('sha256', $secret, 32, 'his-monitoring-api-encryption-v1', $consId),
        );
    }

    private function envelope($payload, $key, $aad)
    {
        $iv = random_bytes(12); $tag = '';
        $ciphertext = openssl_encrypt(json_encode($payload, JSON_UNESCAPED_SLASHES), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $aad, 16);
        return array('version'=>'1', 'alg'=>'A256GCM', 'iv'=>base64_encode($iv), 'tag'=>base64_encode($tag), 'ciphertext'=>base64_encode($ciphertext));
    }

    private function decryptEnvelope(array $envelope, $key, $aad)
    {
        foreach (array('version','alg','iv','tag','ciphertext') as $field) if (!isset($envelope[$field])) throw new RuntimeException('Envelope response API tidak lengkap.');
        $plain = openssl_decrypt(base64_decode($envelope['ciphertext'], TRUE), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, base64_decode($envelope['iv'], TRUE), base64_decode($envelope['tag'], TRUE), $aad);
        if ($plain === FALSE) throw new RuntimeException('Autentikasi/dekripsi response API gagal.');
        $decoded = json_decode($plain, TRUE);
        if (!is_array($decoded)) throw new RuntimeException('Response API bukan JSON valid.');
        return $decoded;
    }

    private function request($branch, $path, $secret, $payload, $token = NULL)
    {
        $timestamp = (string) time(); $nonce = $this->uuid(); $requestId = $this->uuid();
        $keys = $this->keys($secret, $branch->cons_id);
        $aad = implode('|', array('1', $branch->cons_id, $timestamp, $nonce, $requestId));
        $bodyData = $payload === NULL ? new stdClass() : $this->envelope($payload, $keys['encryption'], $aad);
        $body = json_encode($bodyData, JSON_UNESCAPED_SLASHES);
        $canonical = implode("\n", array('POST', $path, $branch->cons_id, $timestamp, $nonce, hash('sha256', $body)));
        $headers = array('Content-Type: application/json', 'Accept: application/json', 'X-Cons-Id: '.$branch->cons_id, 'X-Timestamp: '.$timestamp, 'X-Nonce: '.$nonce, 'X-Request-Id: '.$requestId, 'X-Signature: '.base64_encode(hash_hmac('sha256', $canonical, $keys['signing'], TRUE)));
        if ($token) $headers[] = 'Authorization: Bearer '.$token;
        $ch = curl_init(rtrim($branch->base_url, '/').$path);
        curl_setopt_array($ch, array(CURLOPT_POST=>TRUE, CURLOPT_POSTFIELDS=>$body, CURLOPT_HTTPHEADER=>$headers, CURLOPT_RETURNTRANSFER=>TRUE, CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>TRUE, CURLOPT_SSL_VERIFYHOST=>2));
        $response = curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $error = curl_error($ch); curl_close($ch);
        if ($response === FALSE) throw new RuntimeException('Koneksi API gagal: '.$error);
        $decoded = json_decode($response, TRUE);
        if (!is_array($decoded)) throw new RuntimeException('Response API tidak valid (HTTP '.$status.').');
        if ($status < 200 || $status >= 300) {
            $code = isset($decoded['error']['code']) ? $decoded['error']['code'] : 'HTTP_'.$status;
            throw new RuntimeException('API menolak request: '.$code.'.');
        }
        return $this->decryptEnvelope($decoded, $keys['encryption'], $aad);
    }

    public function health($branch)
    {
        $ch = curl_init(rtrim($branch->base_url, '/').'/api/v1/health');
        curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER=>TRUE, CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>10, CURLOPT_SSL_VERIFYPEER=>TRUE, CURLOPT_SSL_VERIFYHOST=>2));
        $response = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $error = curl_error($ch); curl_close($ch);
        if ($response === FALSE || $status !== 200) throw new RuntimeException($error ?: 'Health check HTTP '.$status);
        $data = json_decode($response, TRUE);
        if (!isset($data['status']) || !$data['status']) throw new RuntimeException('Health check faskes tidak sehat.');
        return $data;
    }

    public function visits($branch, $secret, $from, $to)
    {
        $auth = $this->request($branch, '/api/v1/auth/token', $secret, NULL);
        if (empty($auth['access_token'])) throw new RuntimeException('API tidak mengembalikan access token.');
        return $this->request($branch, '/api/v1/monitoring/kunjungan/summary', $secret, array('date_from'=>$from, 'date_to'=>$to), $auth['access_token']);
    }
}
