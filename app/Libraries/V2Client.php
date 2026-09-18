<?php

namespace App\Libraries;

use RuntimeException;
use Throwable;

/**
 * Klien fungsi akademik siap pakai di smart-sistem-v2
 * (GET /api-akademik/{profil,akm,krs,dosen} + header X-Agent-Key).
 *
 * Agen memanggil FUNGSI ini, bukan SQL langsung — sehingga tidak perlu
 * menebak tabel/kolom legacy. Berlaku untuk semua model/provider karena
 * dieksekusi di server dan hasilnya disuntik ke prompt apa adanya.
 */
class V2Client
{
    public static function configured(): bool
    {
        try {
            $s = new \App\Models\SettingModel();

            return trim((string) $s->getGlobal('v2_base', '')) !== '';
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array data (isi "data" dari respons v2) */
    public static function get(string $func, array $params = []): array
    {
        $s    = new \App\Models\SettingModel();
        $base = rtrim(trim((string) $s->getGlobal('v2_base', '')), '/');
        $key  = (string) $s->getSecret('v2_key', '');

        if ($base === '' || $key === '') {
            throw new RuntimeException('Fungsi akademik v2 belum dikonfigurasi (admin → API & Integrasi → v2_base/v2_key).');
        }

        if (! preg_match('/^[a-z_]+$/', $func)) {
            throw new RuntimeException('Fungsi v2 tidak dikenal.');
        }

        try {
            $res = \Config\Services::curlrequest()->get(
                $base . '/' . $func . ($params === [] ? '' : '?' . http_build_query($params)),
                ['headers' => ['X-Agent-Key' => $key, 'Accept' => 'application/json'], 'timeout' => 60, 'http_errors' => false]
            );
            $code = $res->getStatusCode();
            $data = json_decode($res->getBody(), true);
        } catch (Throwable $e) {
            throw new RuntimeException('Tidak dapat menghubungi fungsi akademik v2: ' . $e->getMessage());
        }

        if ($code === 404 && isset($data['error'])) {
            throw new RuntimeException($data['error']);
        }
        if ($code >= 400 || empty($data['success'])) {
            throw new RuntimeException($data['error'] ?? ('Fungsi v2 "' . $func . '" gagal (HTTP ' . $code . ').'));
        }

        return is_array($data['data'] ?? null) ? $data['data'] : [];
    }
}
