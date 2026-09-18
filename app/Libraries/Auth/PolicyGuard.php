<?php

namespace App\Libraries\Auth;

use App\Models\ApiModuleModel;
use App\Models\AuthViolationModel;
use RuntimeException;

/**
 * SATU-SATUNYA tempat keputusan otorisasi data diambil.
 *
 * Dipanggil oleh AcademicDb::select() untuk setiap query akademik, sehingga
 * tidak ada jalur data yang bisa menyelinap — termasuk tool yang dipanggil AI.
 *
 * Dua mode (kolom `client_policies.on_violation`):
 *  - log_only     : catat pelanggaran tapi izinkan lewat. Untuk rollout —
 *                   melihat apa yang AKAN ditolak sebelum benar-benar menolak.
 *  - deny_and_log : catat dan tolak. Penegakan sesungguhnya.
 */
class PolicyGuard
{
    private static ?AccessPolicy $policy = null;
    private static int $queryCount = 0;

    /** @var array<string, array> cache konfigurasi modul per request */
    private static array $moduleCache = [];

    /** Peta fallback bila modul tidak mendefinisikan kolom identitas sendiri. */
    private const DEFAULT_ID_COLUMN = [
        'mahasiswa' => 'npm',
        'dosen'     => 'nidn',
        'staff'     => 'nik',
    ];

    /** Peta fallback kolom satuan (unit) per modul + tipe unit. */
    private const DEFAULT_UNIT_COLUMN = [
        'mahasiswa' => ['prodi' => 'kd_jur', 'fakultas' => 'kd_fakultas', 'jurusan' => 'kd_jur'],
        'dosen'     => ['prodi' => 'kd_jur', 'fakultas' => 'kd_fakultas', 'jurusan' => 'kd_jur'],
        'staff'     => ['bagian' => 'id_bagian', 'prodi' => 'kd_jur'],
    ];

    // ------------------------------------------------------------------ state

    public static function setPolicy(?AccessPolicy $policy): void
    {
        self::$policy    = $policy;
        self::$queryCount = 0;
        self::$moduleCache = [];
    }

    public static function policy(): ?AccessPolicy
    {
        return self::$policy;
    }

    public static function subjectId(): ?string
    {
        return self::$policy?->subjectId;
    }

    /** Reset penuh — dipakai di test dan di akhir request. */
    public static function reset(): void
    {
        self::$policy      = null;
        self::$queryCount  = 0;
        self::$moduleCache = [];
    }

    /** Apakah sedang ada request API eksternal yang aktif? */
    public static function active(): bool
    {
        return self::$policy !== null;
    }

    // ------------------------------------------------------------- penegakan

    /**
     * Saring kolom + paksa batasan scope pada sebuah query.
     *
     * @param string $module slug modul (mis. 'mahasiswa')
     * @param string $table  nama tabel DB akademik
     * @param array  $cols   kolom yang diminta
     * @param array  $where  kondisi yang diminta pemanggil
     * @param int    $limit  batas baris yang diminta
     *
     * @return array{cols: array, where: array, limit: int}
     *
     * @throws RuntimeException bila akses ditolak dan policy bersifat menegakkan
     */
    public static function enforce(string $module, string $table, array $cols, array $where, int $limit): array
    {
        $policy = self::$policy;

        // Tidak ada kebijakan sama sekali = tidak ada data.
        if ($policy === null) {
            self::reject('no_policy', $module, $table, null, 'deny_and_log');
        }

        // ---- 1. Modul harus diizinkan ----
        if (! $policy->allowsModule($module)) {
            self::reject('module_denied', $module, $table, null, $policy->onViolation);
        }

        // ---- 2. Anggaran query per request ----
        self::$queryCount++;
        if ($policy->queryBudget > 0 && self::$queryCount > $policy->queryBudget) {
            self::reject('budget_exceeded', $module, $table, (string) self::$queryCount, $policy->onViolation);
        }

        // ---- 3. Paksa cakupan baris (inti anti-IDOR) ----
        $where = self::applyScope($policy, $module, $table, $where);

        // ---- 4. Saring kolom ----
        $cols = self::applyFields($policy, $module, $cols);

        // ---- 5. Batasi jumlah baris ----
        $cap = $policy->rowLimit > 0 ? $policy->rowLimit : 50;

        return [
            'cols'  => $cols,
            'where' => $where,
            'limit' => max(1, min($limit, $cap)),
        ];
    }

    /**
     * Apakah sebuah tool AI boleh dipakai pada request ini?
     * Bila tidak, tool bahkan tidak diregistrasi — AI tidak tahu ia ada.
     *
     * Tanpa kebijakan aktif berarti konteks internal (web admin), bukan request
     * API eksternal — tool diizinkan agar tidak memutus alur lama. Setiap klien
     * API SELALU punya kebijakan (tanpa baris policy = denyAll), jadi cabang
     * ini tidak pernah menjadi jalan pintas bagi pemanggil eksternal.
     */
    public static function canUseTool(string $tool): bool
    {
        $policy = self::$policy;

        if ($policy === null) {
            return true;
        }

        if ($policy->allowsTool($tool)) {
            return true;
        }

        self::violate('tool_denied', '-', $tool);

        return false;
    }

    /**
     * Versi melempar-exception dari canUseTool(), untuk dipanggil di dalam tool.
     *
     * @throws RuntimeException
     */
    public static function assertTool(string $tool): void
    {
        if (! self::canUseTool($tool)) {
            throw new RuntimeException("Tool '{$tool}' tidak diizinkan untuk klien ini.");
        }
    }

    /**
     * Verifikasi bahwa sebuah identifier cocok dengan subject kebijakan.
     * Dipanggil oleh tool & asisten yang menerima identifier dari luar.
     *
     * @throws RuntimeException bila tidak cocok dan policy menegakkan
     */
    public static function assertSubject(string $module, ?string $attemptedId): void
    {
        $policy = self::$policy;

        if ($policy === null || $policy->scope !== AccessPolicy::SCOPE_SELF) {
            return;
        }

        if ($policy->subjectId === null) {
            self::reject('subject_missing', $module, '', $attemptedId, $policy->onViolation);
        }

        if ($attemptedId !== null && ! self::sameId($attemptedId, $policy->subjectId)) {
            self::reject('idor', $module, '', $attemptedId, $policy->onViolation, $policy->subjectId);
        }
    }

    /** Identifier yang WAJIB dipakai, menimpa apa pun dari pemanggil/teks AI. */
    public static function forcedSubject(string $module, ?string $requestedId): ?string
    {
        $policy = self::$policy;

        if ($policy === null) {
            return $requestedId;
        }

        if ($policy->scope !== AccessPolicy::SCOPE_SELF) {
            return $requestedId;
        }

        // Scope self: subject tidak bisa dinegosiasi.
        if ($requestedId !== null && ! self::sameId($requestedId, (string) $policy->subjectId)) {
            self::reject('idor', $module, '', $requestedId, $policy->onViolation, $policy->subjectId);
        }

        return $policy->subjectId;
    }

    // -------------------------------------------------------- cakupan baris

    /**
     * Suntikkan batasan scope ke WHERE.
     *
     * @throws RuntimeException
     */
    private static function applyScope(AccessPolicy $policy, string $module, string $table, array $where): array
    {
        switch ($policy->scope) {
            case AccessPolicy::SCOPE_ALL:
                return $where;

            case AccessPolicy::SCOPE_SELF:
                return self::applySelf($policy, $module, $where);

            case AccessPolicy::SCOPE_UNIT:
                return self::applyUnit($policy, $module, $where);

            case AccessPolicy::SCOPE_ROLE:
                return self::applyRole($policy, $module, $where);

            default:
                self::reject('scope_unknown', $module, $table, $policy->scope, 'deny_and_log');

                return $where;
        }
    }

    /**
     * Scope `self`: paksa WHERE <kolom-id> = subject.
     * Inilah perbaikan IDOR — nilai dari pemanggil ditimpa, bukan dipercaya.
     */
    private static function applySelf(AccessPolicy $policy, string $module, array $where): array
    {
        if ($policy->subjectId === null || $policy->subjectId === '') {
            self::reject('subject_missing', $module, '', null, $policy->onViolation);

            return $where;
        }

        $idCol = self::idColumn($module);
        if ($idCol === null) {
            // Tidak tahu kolom identitasnya → tidak bisa menjamin pembatasan.
            self::reject('unit_unsupported', $module, '', $idCol, 'deny_and_log');

            return $where;
        }

        // Deteksi percobaan meminta subject lain lewat WHERE yang dikirim pemanggil.
        foreach ($where as $k => $v) {
            if (! is_string($k)) {
                continue;
            }
            if (strtolower($k) === strtolower($idCol) && ! self::sameId((string) $v, $policy->subjectId)) {
                self::reject('idor', $module, '', (string) $v, $policy->onViolation, $policy->subjectId);
            }
        }

        // Timpa dengan subject terverifikasi.
        $where[$idCol] = $policy->subjectId;

        return $where;
    }

    /** Scope `unit`: batasi ke unit milik klien, bukan unit yang diminta pemanggil. */
    private static function applyUnit(AccessPolicy $policy, string $module, array $where): array
    {
        $unit = $policy->unitScope;

        if ($unit === null || empty($unit['ids'])) {
            self::reject('unit_scope_empty', $module, '', null, $policy->onViolation);

            return $where;
        }

        $col = self::unitColumn($module, (string) $unit['type']);
        if ($col === null) {
            self::reject('unit_unsupported', $module, '', (string) $unit['type'], $policy->onViolation);

            return $where;
        }

        // Timpa nilai apa pun yang dikirim pemanggil.
        $where[$col] = array_values($unit['ids']);

        return $where;
    }

    /** Scope `role`: batasi ke populasi dengan peran tertentu. */
    private static function applyRole(AccessPolicy $policy, string $module, array $where): array
    {
        $role = $policy->roleScope;

        if ($role === '') {
            self::reject('role_unknown', $module, '', null, $policy->onViolation);

            return $where;
        }

        // Kolom pembeda dosen/staff pada tabel kepegawaian.
        $constraints = [
            'dosen'     => ['is_dosen' => 1],
            'staff'     => ['is_dosen' => 0],
            'tendik'    => ['is_dosen' => 0],
            'mahasiswa' => [],
        ];

        $key = strtolower(trim($role));

        foreach ($constraints[$key] ?? [] as $col => $val) {
            $where[$col] = $val;
        }

        return $where;
    }

    // --------------------------------------------------------- saring kolom

    /**
     * Buang kolom yang dilarang atau tidak diizinkan.
     * Nama kolom yang ditolak TIDAK disebut di pesan error — menyebutnya
     * membocorkan skema tabel ke pemanggil.
     *
     * @param array $cols
     * @return array
     */
    private static function applyFields(AccessPolicy $policy, string $module, array $cols): array
    {
        $allow   = $policy->allowedFields($module);
        $denied  = $policy->effectiveDeniedFields($module);
        $out     = [];
        $blocked = [];

        foreach ($cols as $c) {
            $lc = strtolower(trim((string) $c));
            if ($lc === '') {
                continue;
            }

            if (in_array($lc, $denied, true)) {
                $blocked[] = $lc;
                self::violate('field_denied', $module, $lc);

                continue;
            }

            if ($allow !== null && ! in_array($lc, $allow, true)) {
                $blocked[] = $lc;
                self::violate('field_not_allowed', $module, $lc);

                continue;
            }

            $out[] = $c;
        }

        // Bila SEMUA kolom yang diminta ditolak, jangan diam-diam mengubah
        // arti query menjadi "ambil kolom identitas saja" — tolak tegas.
        if ($out === []) {
            self::reject(
                'field_denied',
                $module,
                '',
                implode(',', array_slice($blocked, 0, 5)),
                'deny_and_log'
            );

            // Bila mode log_only, kembalikan kolom asli agar tidak memutus request.
            return $cols;
        }

        // Kolom identitas selalu disertakan agar hasil bisa dipetakan ke subject.
        $idCol = self::idColumn($module);
        if ($idCol !== null && ! in_array(strtolower($idCol), $denied, true)) {
            $hasId = false;
            foreach ($out as $c) {
                if (strtolower((string) $c) === strtolower($idCol)) {
                    $hasId = true;
                    break;
                }
            }
            if (! $hasId) {
                array_unshift($out, $idCol);
            }
        }

        return $out;
    }

    // ------------------------------------------------------- konfigurasi modul

    /**
     * Kolom identitas untuk sebuah modul.
     * Urutan sumber: query_config.profile.id_col → api_modules.id_param → fallback.
     */
    public static function idColumn(string $module): ?string
    {
        $cfg = self::moduleConfig($module);

        $fromCfg = $cfg['profile']['id_col'] ?? null;
        if (is_string($fromCfg) && self::isIdentifier($fromCfg)) {
            return $fromCfg;
        }

        $fromParam = $cfg['__row']['id_param'] ?? null;
        if (is_string($fromParam) && $fromParam !== '' && self::isIdentifier($fromParam)) {
            return $fromParam;
        }

        return self::DEFAULT_ID_COLUMN[strtolower($module)] ?? null;
    }

    /** Kolom satuan (fakultas/prodi/bagian) untuk sebuah modul. */
    public static function unitColumn(string $module, string $unitType): ?string
    {
        $unitType = strtolower(trim($unitType));
        if ($unitType === '') {
            return null;
        }

        $cfg  = self::moduleConfig($module);
        $key  = 'unit_col_' . $unitType;

        // 1. Definisi eksplisit di query_config: {"profile":{"unit_col_fakultas":"kd_fakultas"}}
        $fromCfg = $cfg['profile'][$key] ?? null;
        if (is_string($fromCfg) && self::isIdentifier($fromCfg)) {
            return $fromCfg;
        }

        // 2. Fallback bawaan
        return self::DEFAULT_UNIT_COLUMN[strtolower($module)][$unitType] ?? null;
    }

    /** Baca & cache konfigurasi modul dari `api_modules`. */
    private static function moduleConfig(string $module): array
    {
        $module = strtolower(trim($module));

        if (array_key_exists($module, self::$moduleCache)) {
            return self::$moduleCache[$module];
        }

        $cfg = [];
        try {
            $model = new ApiModuleModel();
            $row   = $model->findBySlug($module);
            if ($row !== null) {
                $cfg            = $model->queryConfig($row);
                $cfg['__row']   = $row;
            }
        } catch (\Throwable) {
            // Tabel belum ada (sebelum migrasi) — jangan memutus request.
            $cfg = [];
        }

        return self::$moduleCache[$module] = $cfg;
    }

    private static function isIdentifier(string $s): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_]+$/', $s);
    }

    // ------------------------------------------------------------ utilitas

    /**
     * Bandingkan identifier secara longgar: data akademik legacy sering
     * berpadding spasi atau berisi pemisah. Bandingkan digit saja.
     */
    public static function sameId(string $a, string $b): bool
    {
        $digits = static fn (string $s): string => (string) preg_replace('/\D/', '', $s);

        $na = $digits($a);
        $nb = $digits($b);

        // Bila salah satu tidak punya digit, bandingkan string ternormalisasi.
        if ($na === '' || $nb === '') {
            $norm = static fn (string $s): string => strtolower(preg_replace('/\s+/', '', $s) ?? '');

            return $norm($a) !== '' && $norm($a) === $norm($b);
        }

        return $na === $nb;
    }

    /**
     * Tolak: catat pelanggaran, lalu lempar exception HANYA bila menegakkan.
     *
     * @throws RuntimeException
     */
    private static function reject(
        string $kind,
        string $module,
        string $table,
        ?string $attempted,
        string $mode,
        ?string $allowed = null
    ): void {
        self::violate($kind, $module, $attempted ?? '', $allowed, $table);

        if ($mode !== 'deny_and_log') {
            return;   // log_only: biarkan lewat, sudah tercatat
        }

        // Pesan sengaja generik — jangan bocorkan alasan rinci ke pemanggil,
        // dan jangan bedakan "tidak ditemukan" vs "tidak diizinkan"
        // (perbedaan itu memungkinkan enumerasi).
        throw new RuntimeException('Data tersebut tidak tersedia dalam wewenang aplikasi ini.');
    }

    /** Catat pelanggaran. Tidak boleh pernah menggagalkan request utama. */
    private static function violate(
        string $kind,
        string $module,
        string $attempted = '',
        ?string $allowed = null,
        string $table = ''
    ): void {
        try {
            $ip       = '';
            $endpoint = '';

            if (function_exists('service')) {
                try {
                    $ip = (string) service('request')->getIPAddress();
                } catch (\Throwable) {
                }
                try {
                    $endpoint = (string) (service('router')->controllerName() ?? '')
                        . '::' . (string) (service('router')->methodName() ?? '');
                } catch (\Throwable) {
                }
            }

            (new AuthViolationModel())->record([
                'client_id'  => self::$policy?->clientId,
                'ip'         => $ip !== '' ? $ip : null,
                'violation'  => $kind,
                'module'     => $module !== '' ? $module : null,
                'attempted'  => $attempted !== '' ? $attempted : ($table !== '' ? $table : null),
                'allowed'    => $allowed,
                'endpoint'   => $endpoint !== '' ? $endpoint : null,
                'subject_id' => self::$policy?->subjectId,
            ]);
        } catch (\Throwable) {
            // Audit gagal (mis. tabel belum dimigrasi) — abaikan, jangan putus.
        }
    }
}
