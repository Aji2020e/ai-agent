<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\ApiAuth;
use App\Models\ApiClientModel;
use App\Models\ApiLogModel;

/**
 * Basis endpoint integrasi akademik. Auth via ApiKeyFilter (X-API-Key).
 */
class BaseApi extends BaseController
{
    protected function client(): ?array
    {
        return ApiAuth::client();
    }

    /**
     * Ambil parameter: nama pertama yang ada nilainya.
     * $viaGet=true hanya untuk field non-sensitif (role/module/filter) —
     * ID & pertanyaan HANYA via POST/JSON agar tak nyangkut di access log.
     * Kontrak portal: {id|npm|nim|nik, role|module, reason|pertanyaan}.
     */
    protected function param(string ...$names): ?string
    {
        return $this->paramVia($names, true);
    }

    protected function paramVia(array $names, bool $viaGet): ?string
    {
        foreach ($names as $name) {
            $v = $this->request->getPost($name) ?? $this->jsonVar($name);
            if ($v === null && $viaGet) {
                $v = $this->request->getGet($name);
            }
            if ($v !== null && trim((string) $v) !== '') {
                return trim((string) $v);
            }
        }

        return null;
    }

    /** getJsonVar yang aman (JSON rusak → null, bukan 500). */
    protected function jsonVar(string $name)
    {
        try {
            return $this->request->getJsonVar($name);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function idParam(): ?string
    {
        return $this->paramVia(['id', 'npm', 'nim', 'nik', 'nidn', 'nopegawai', 'no_pegawai'], false);
    }

    protected function reasonParam(): ?string
    {
        return $this->paramVia(['reason', 'pertanyaan', 'question', 'alasan'], false);
    }

    protected function roleParam(): string
    {
        return strtolower(trim((string) ($this->param('role', 'peran') ?? '')));
    }

    /**
     * PERBAIKAN H2 — peran yang DIPERCAYA.
     *
     * Sebelumnya `roleParam()` dipakai langsung, artinya pemanggil cukup
     * mengirim `"role":"dosen"` untuk memperoleh peta modul dosen.
     *
     * Urutan sumber sekarang:
     *  1. Kebijakan scope 'all'  → boleh memakai `role` dari request
     *     (klien super-admin yang memang dipercaya menyatakan peran).
     *  2. subject_type kebijakan → peran mengikuti tipe subjek terverifikasi.
     *  3. role_scope kebijakan   → peran yang ditetapkan admin untuk klien ini.
     *  4. '' (kosong)            → tidak ada peran, bukan 'admin'.
     */
    protected function resolvedRole(): string
    {
        $policy = \App\Libraries\Auth\PolicyGuard::policy();

        if ($policy === null) {
            // Konteks internal (bukan request API): hormati parameter seperti dulu.
            return \App\Libraries\Academic\ModuleRegistry::normalizeRole($this->roleParam());
        }

        if ($policy->scope === \App\Libraries\Auth\AccessPolicy::SCOPE_ALL) {
            $fromRequest = $this->roleParam();

            return $fromRequest === ''
                ? ''
                : \App\Libraries\Academic\ModuleRegistry::normalizeRole($fromRequest);
        }

        if ($policy->subjectType !== null && $policy->subjectType !== '') {
            return \App\Libraries\Academic\ModuleRegistry::normalizeRole($policy->subjectType);
        }

        if ($policy->roleScope !== '') {
            return \App\Libraries\Academic\ModuleRegistry::normalizeRole($policy->roleScope);
        }

        return '';
    }

    /**
     * Subject terverifikasi untuk request ini (NIM/NIK/NIDN).
     * Sumbernya body bertanda tangan HMAC — bukan query string, dan bukan
     * teks pertanyaan. `null` bila kebijakan tidak mengikat subjek.
     */
    protected function verifiedSubject(): ?string
    {
        return \App\Libraries\Auth\PolicyGuard::subjectId();
    }

    protected function moduleParam(): string
    {
        return strtolower(trim((string) ($this->param('module', 'modul') ?? '')));
    }

    protected function requireModule(string $module): ?array
    {
        $client = $this->client();

        if ($client === null) {
            return null;
        }

        return (new ApiClientModel())->allowsModule($client, $module) ? $client : null;
    }

    protected function forbidden(string $module)
    {
        return $this->response->setStatusCode(403)->setJSON([
            'success'      => false,
            'data_refused' => true,
            'error'        => "Klien tidak diizinkan membuka data modul '{$module}'. Obrolan umum tetap bisa via endpoint chat.",
            'fallback'     => 'chat',
        ]);
    }

    protected function log(?int $clientId, string $module, string $endpoint, int $tokens = 0, string $status = 'ok'): void
    {
        try {
            (new ApiLogModel())->log($clientId, $module, $endpoint, $tokens, $status);
        } catch (\Throwable) {
        }
    }

    /** Jalankan analis modul dengan validasi akses + logging terpusat. */
    protected function runModule(string $module, string $assistantClass, array $params, array $extra = [], bool $asArray = false)
    {
        $client = $this->requireModule($module);

        if ($client === null) {
            return $asArray
                ? ['success' => false, 'module' => $module, 'error' => "Klien tidak diizinkan membuka data modul '{$module}'."]
                : $this->forbidden($module);
        }

        try {
            $result = $assistantClass::analyze($params);
        } catch (\RuntimeException $e) {
            $this->log((int) $client['id'], $module, $module . '/analyze', 0, 'error');

            return $asArray
                ? ['success' => false, 'module' => $module, 'error' => $e->getMessage()]
                : $this->response->setStatusCode(422)->setJSON(['success' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->log((int) $client['id'], $module, $module . '/analyze', 0, 'error');

            return $asArray
                ? ['success' => false, 'module' => $module, 'error' => 'Kesalahan internal.']
                : $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => 'Kesalahan internal.']);
        }

        $this->log((int) $client['id'], $module, $module . '/analyze', (int) ($result['tokens'] ?? 0));

        if ($asArray) {
            return array_merge(['success' => true, 'module' => $module], $result, $extra);
        }

        return $this->response->setJSON(array_merge([
            'success'  => true,
            'module'   => $module,
            'data'     => $result['data'],
            'analysis' => $result['analysis'],
            'tokens'   => $result['tokens'] ?? 0,
            'jejak'    => $result['jejak'] ?? [],
            'evidence' => $result['evidence'] ?? null,
            'confidence' => $result['confidence'] ?? 'sedang',
        ], $extra));
    }
}
