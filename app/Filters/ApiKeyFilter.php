<?php

namespace App\Filters;

use App\Libraries\ApiAuth;
use App\Models\ApiClientModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Auth machine-to-machine berlapis:
 *  1. API key (X-API-Key / Authorization: Bearer), anti brute-force per IP
 *  2. Expiry + IP allowlist per klien
 *  3. Rate limit per key (120/menit)
 *  4. HMAC opsional (anti replay bila require_hmac=1)
 */
class ApiKeyFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $ip        = $request->getIPAddress();
        $throttler = service('throttler');

        $key = (string) ($request->getHeaderLine('X-API-Key') ?: '');

        if ($key === '' && str_starts_with($request->getHeaderLine('Authorization'), 'Bearer ')) {
            $key = substr($request->getHeaderLine('Authorization'), 7);
        }

        $model  = new ApiClientModel();
        $client = $model->findByKey(trim($key));

        if ($client === null || empty($client['is_active'])) {
            // Perisai brute-force: maksimal 30 kegagalan/menit per IP
            if (! $throttler->check('api401_' . $ip, 30, 60)) {
                return $this->deny(429, 'Terlalu banyak percobaan. Coba lagi nanti.', (int) $throttler->getTokenTime());
            }

            return $this->deny(401, 'API key tidak valid atau dinonaktifkan.');
        }

        // Expiry
        if (! empty($client['expires_at']) && strtotime($client['expires_at']) < time()) {
            return $this->deny(403, 'API key kedaluwarsa.');
        }

        // IP allowlist (kosong = semua IP boleh)
        if (! $this->ipAllowed($ip, (string) ($client['ip_allowlist'] ?? ''))) {
            return $this->deny(403, 'IP tidak diizinkan untuk key ini.');
        }

        // Rate limit per key
        if (! $throttler->check('apikey_' . $client['id'], 120, 60)) {
            return $this->deny(429, 'Batas 120 request/menit terlampaui.', (int) $throttler->getTokenTime());
        }

        // HMAC bila diwajibkan: X-Timestamp + X-Signature = HMAC_SHA256(secret, ts + '.' + rawBody)
        if (! empty($client['require_hmac'])) {
            $err = $this->verifyHmac($request, (string) $client['hmac_secret']);
            if ($err !== null) {
                return $this->deny(401, $err);
            }
        }

        $model->update($client['id'], ['last_used' => date('Y-m-d H:i:s')]);
        ApiAuth::setClient($client);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }

    private function deny(int $code, string $msg, int $retryAfter = 0)
    {
        $res = service('response')->setStatusCode($code)->setJSON(['success' => false, 'error' => $msg]);
        if ($retryAfter > 0) {
            $res->setHeader('Retry-After', (string) $retryAfter);
        }

        return $res;
    }

    private function ipAllowed(string $ip, string $list): bool
    {
        $list = trim($list);

        if ($list === '') {
            return true;
        }

        foreach (preg_split('/[\s,]+/', $list) as $rule) {
            $rule = trim($rule);
            if ($rule === '') {
                continue;
            }
            // CIDR sederhana (IPv4)
            if (str_contains($rule, '/') && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                [$net, $bits] = explode('/', $rule, 2) + [null, null];
                $bits = (int) $bits;
                if ($bits >= 0 && $bits <= 32 && filter_var($net, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $mask = $bits === 0 ? 0 : (~0 << (32 - $bits));
                    if ((ip2long($ip) & $mask) === (ip2long($net) & $mask)) {
                        return true;
                    }
                    continue;
                }
            }
            if ($ip === $rule) {
                return true;
            }
        }

        return false;
    }

    private function verifyHmac(RequestInterface $request, string $secret): ?string
    {
        if ($secret === '') {
            return 'HMAC wajib tetapi secret belum diset.';
        }

        $ts  = $request->getHeaderLine('X-Timestamp');
        $sig = $request->getHeaderLine('X-Signature');

        if ($ts === '' || $sig === '') {
            return 'Header X-Timestamp dan X-Signature wajib.';
        }

        if (! ctype_digit($ts) || abs(time() - (int) $ts) > 300) {
            return 'Timestamp kedaluwarsa (toleransi ±5 menit).';
        }

        // $request->getBody() tidak ada di interface lama? gunakan php://input via getBody pada IncomingRequest
        $raw = method_exists($request, 'getBody') ? (string) $request->getBody() : file_get_contents('php://input');
        $exp = hash_hmac('sha256', $ts . '.' . $raw, $secret);

        if (! hash_equals($exp, strtolower(trim($sig)))) {
            return 'Signature tidak valid.';
        }

        // Anti replay: signature hanya berlaku sekali dalam 10 menit
        $cache = service('cache');
        $ck    = 'hmac_' . $exp;
        if ($cache->get($ck) !== null) {
            return 'Signature sudah dipakai (replay ditolak).';
        }
        $cache->save($ck, 1, 600);

        return null;
    }
}
