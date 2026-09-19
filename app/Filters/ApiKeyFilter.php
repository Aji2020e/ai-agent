<?php

namespace App\Filters;

use App\Libraries\ApiAuth;
use App\Libraries\Auth\PolicyGuard;
use App\Models\ApiKeyModel;
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

        $model  = new ApiKeyModel();
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
        if (! $throttler->check('apikey_' . $client['key_id'], 120, 60)) {
            return $this->deny(429, 'Batas 120 request/menit terlampaui.', (int) $throttler->getTokenTime());
        }

        // HMAC opsional (anti replay)
        if (! empty($client['require_hmac'])) {
            $err = $this->verifyHmac($request, (string) $client['hmac_secret']);
            if ($err !== null) {
                return $this->deny(401, $err);
            }
        }

        $model->touch((int) $client['key_id']);
        ApiAuth::setClient($client);

        // Pasang kebijakan otorisasi. Bila subject tidak sah, kembalikan
        // respons penolakan agar request berhenti di sini.
        return $this->applyPolicy($request, $client);
    }

    /**
     * Bangun & pasang AccessPolicy untuk request ini.
     *
     * Subject HANYA dibaca dari body (yang ikut ditandatangani HMAC), tidak
     * pernah dari query string — alasan sama dengan BaseApi::param(): agar ID
     * tidak nyangkut di access log, dan agar tidak bisa diubah di tengah jalan.
     */
    private function applyPolicy(RequestInterface $request, array $client): ?ResponseInterface
    {
        $policyRow = (new \App\Models\ClientPolicyModel())->forClient((int) $client['id']);

        [$subjectId, $subjectType] = $this->extractSubject($request);

        // Klien tanpa baris kebijakan → denyAll. Fail-closed.
        $policy = \App\Libraries\Auth\AccessPolicy::fromClient(
            $client,
            $policyRow,
            $subjectId,
            $subjectType
        );

        // Subject wajib untuk scope self
        if ($policy->requiresSubject() && ! empty($policyRow['subject_required']) && $policy->subjectId === null) {
            PolicyGuard::setPolicy($policy);
            $this->recordViolation($client, 'subject_missing', 'subject');

            return $this->deny(422, "Parameter 'subject' wajib untuk klien ini. "
                . 'Kirim {"subject":{"type":"...","id":"..."}} di body.');
        }

        // Tipe subject harus yang diizinkan kebijakan
        if ($subjectType !== null && ! $policy->allowsSubjectType($subjectType)) {
            PolicyGuard::setPolicy($policy);
            $this->recordViolation($client, 'subject_type', $subjectType);

            return $this->deny(403, 'Tipe subject tidak diizinkan untuk klien ini.');
        }

        PolicyGuard::setPolicy($policy);

        return null;
    }

    /**
     * Ambil subject dari body JSON. Return [id|null, type|null].
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function extractSubject(RequestInterface $request): array
    {
        $raw = '';
        try {
            $raw = method_exists($request, 'getBody') ? (string) $request->getBody() : '';
        } catch (\Throwable) {
            $raw = '';
        }

        if ($raw === '') {
            return [null, null];
        }

        $json = json_decode($raw, true);
        if (! is_array($json)) {
            return [null, null];
        }

        $sub = $json['subject'] ?? null;

        // Bentuk panjang: {"subject":{"type":"mahasiswa","id":"202110110"}}
        if (is_array($sub)) {
            $id   = $sub['id'] ?? $sub['nid'] ?? $sub['npm'] ?? $sub['nim'] ?? $sub['nik'] ?? null;
            $type = $sub['type'] ?? $sub['tipe'] ?? $sub['role'] ?? null;

            return [self::cleanId($id), self::cleanType($type)];
        }

        // Bentuk pendek: {"subject":"202110110"} — tipe dari kebijakan klien
        if (is_string($sub) || is_int($sub)) {
            return [self::cleanId($sub), null];
        }

        // Bentuk paling sederhana: {"subject_id":"...","subject_type":"..."}
        $id   = $json['subject_id'] ?? $json['id'] ?? null;
        $type = $json['subject_type'] ?? null;

        if ($id === null && $type === null) {
            return [null, null];
        }

        return [self::cleanId($id), self::cleanType($type)];
    }

    private static function cleanId(mixed $v): ?string
    {
        if ($v === null || is_array($v) || is_object($v)) {
            return null;
        }
        $s = trim((string) $v);

        // Batas panjang wajar untuk NIM/NIK/NIDN
        return ($s === '' || mb_strlen($s) > 40) ? null : $s;
    }

    private static function cleanType(mixed $v): ?string
    {
        if ($v === null || is_array($v) || is_object($v)) {
            return null;
        }
        $s = strtolower(trim((string) $v));

        return ($s === '' || mb_strlen($s) > 30 || ! preg_match('/^[a-z_]+$/', $s)) ? null : $s;
    }

    private function recordViolation(array $client, string $kind, string $detail): void
    {
        try {
            (new \App\Models\AuthViolationModel())->record([
                'client_id' => (int) ($client['id'] ?? 0),
                'ip'        => service('request')->getIPAddress(),
                'violation' => $kind,
                'module'    => '-',
                'attempted' => mb_substr($detail, 0, 100),
                'endpoint'  => service('router')->controllerName() . '::' . service('router')->methodName(),
            ]);
        } catch (\Throwable) {
            // Audit tidak boleh memutus request
        }
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
