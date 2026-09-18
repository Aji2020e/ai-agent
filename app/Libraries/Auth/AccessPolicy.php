<?php

namespace App\Libraries\Auth;

/**
 * Kebijakan akses yang sudah diputuskan untuk SATU request. Immutable.
 *
 * Tiga konsep yang dipisahkan dengan tegas:
 *  - PRINCIPAL : aplikasi pemanggil (baris `api_clients`)
 *  - POLICY    : aturan main (baris `client_policies`) — ditetapkan ADMIN,
 *                TIDAK PERNAH dikirim oleh pemanggil
 *  - SUBJECT   : orang yang datanya dibuka (NIM/NIK/NIDN)
 *
 * Nilai '*' pada kolom modules/skills TIDAK lagi berarti "semua". Wildcard
 * hanya boleh lewat scope='all' yang eksplisit. Ini perubahan sengaja dari
 * perilaku lama yang gagal-terbuka.
 */
final class AccessPolicy
{
    public const SCOPE_SELF = 'self';
    public const SCOPE_UNIT = 'unit';
    public const SCOPE_ROLE = 'role';
    public const SCOPE_ALL  = 'all';

    /**
     * @param string[]    $modules      slug modul yang diizinkan
     * @param string[]    $subjectTypes tipe subjek yang diterima
     * @param string[]    $toolsAllowed nama tool AI yang diizinkan
     * @param array       $fieldPolicy  [modul => ['allow'=>string[], 'deny'=>string[]]]
     * @param array|null  $unitScope    ['type'=>string, 'ids'=>string[]]
     */
    private function __construct(
        public readonly int $clientId,
        public readonly string $clientName,
        public readonly string $scope,
        public readonly ?string $subjectId,
        public readonly ?string $subjectType,
        public readonly array $modules,
        public readonly array $subjectTypes,
        public readonly array $fieldPolicy,
        public readonly int $rowLimit,
        public readonly int $queryBudget,
        public readonly array $toolsAllowed,
        public readonly ?array $unitScope,
        public readonly string $roleScope,
        public readonly string $onViolation,
        public readonly bool $bypassModules = false,
    ) {
    }

    /**
     * Bangun dari baris `api_clients` + `client_policies`.
     *
     * @param array      $client      baris api_clients
     * @param array|null $policyRow   baris client_policies; null = belum terdaftar
     * @param string|null $subjectId  subjek terverifikasi (dari body bertanda tangan)
     */
    public static function fromClient(
        array $client,
        ?array $policyRow,
        ?string $subjectId = null,
        ?string $subjectType = null
    ): self {
        // Klien tanpa kebijakan: tolak semua. Fail-closed.
        if ($policyRow === null) {
            return self::denyAll((int) ($client['id'] ?? 0), (string) ($client['name'] ?? ''));
        }

        $fieldPolicy = json_decode((string) ($policyRow['field_policy'] ?? ''), true);
        if (! is_array($fieldPolicy)) {
            $fieldPolicy = [];
        }

        $unitIds = self::csv((string) ($policyRow['unit_ids'] ?? ''));
        $unitType = trim((string) ($policyRow['unit_type'] ?? ''));

        return new self(
            (int) ($client['id'] ?? 0),
            (string) ($client['name'] ?? ''),
            self::normalizeScope((string) ($policyRow['scope'] ?? self::SCOPE_SELF)),
            ($subjectId !== null && trim($subjectId) !== '') ? trim($subjectId) : null,
            ($subjectType !== null && trim($subjectType) !== '')
                ? strtolower(trim($subjectType))
                : null,
            self::csv((string) ($client['modules'] ?? '')),
            self::csv((string) ($policyRow['subject_types'] ?? '')),
            $fieldPolicy,
            max(0, (int) ($policyRow['row_limit'] ?? 50)),
            max(0, (int) ($policyRow['query_budget'] ?? 8)),
            self::csv((string) ($policyRow['tools_allowed'] ?? '')),
            ($unitIds !== [] && $unitType !== '') ? ['type' => $unitType, 'ids' => $unitIds] : null,
            strtolower(trim((string) ($policyRow['role_scope'] ?? ''))),
            in_array((string) ($policyRow['on_violation'] ?? ''), ['deny_and_log', 'log_only'], true)
                ? (string) $policyRow['on_violation']
                : 'log_only',
            false,
        );
    }

    /** Kebijakan kosong total — tidak ada modul, tidak ada tool, tidak ada data. */
    public static function denyAll(int $clientId, string $clientName): self
    {
        return new self(
            $clientId,
            $clientName,
            self::SCOPE_SELF,
            null,
            null,
            [],
            [],
            [],
            0,
            0,
            [],
            null,
            '',
            'deny_and_log',
            false,
        );
    }

    /**
     * Kebijakan untuk konteks NON-API (chat internal admin lewat web UI).
     * Tidak ada principal eksternal, jadi tidak ada pemaksaan subject —
     * tapi tetap dibatasi modul & tool agar AI internal tidak liar.
     */
    public static function internalWebSession(?string $subjectId = null, ?string $subjectType = null): self
    {
        return new self(
            0,
            'internal-web',
            $subjectId !== null ? self::SCOPE_SELF : self::SCOPE_ALL,
            $subjectId,
            $subjectType,
            [],
            [],
            [],
            200,
            25,
            // file_reader sengaja TIDAK ikut: membaca berkas berdasarkan path
            // dari teks pengguna adalah vektor H4b, bahkan di sesi internal.
            ['db_lookup', 'web_search'],
            null,
            '',
            'log_only',
            // Sesi web admin internal: bukan klien eksternal, jadi daftar modul
            // tidak dipakai sebagai pembatas. Pembatasnya adalah login admin.
            true,
        );
    }

    public function isInternal(): bool
    {
        return $this->clientId === 0 && $this->clientName === 'internal-web';
    }

    /** Apakah pelanggaran benar-benar ditolak (bukan sekadar dicatat)? */
    public function isEnforcing(): bool
    {
        return $this->onViolation === 'deny_and_log';
    }

    /**
     * Apakah sebuah modul boleh diakses.
     *
     * PENTING: `scope` dan daftar modul adalah dua sumbu yang BERBEDA.
     *  - `modules` menjawab: modul apa saja yang boleh disentuh
     *  - `scope`   menjawab: sejauh mana baris data di dalam modul itu
     *
     * Sebelumnya scope='all' mem-bypass daftar modul, yang justru MEMPERLUAS
     * akses klien lama saat migrasi. Sekarang keduanya harus dipenuhi.
     */
    public function allowsModule(string $slug): bool
    {
        return $this->bypassModules || in_array($slug, $this->modules, true);
    }

    public function allowsTool(string $tool): bool
    {
        return in_array($tool, $this->toolsAllowed, true);
    }

    public function allowsSubjectType(string $type): bool
    {
        $type = strtolower(trim($type));
        if ($type === '') {
            return false;
        }

        // Tidak ada batasan tipe berarti semua tipe yang dikenal diterima.
        return $this->subjectTypes === [] || in_array($type, $this->subjectTypes, true);
    }

    public function requiresSubject(): bool
    {
        return $this->scope === self::SCOPE_SELF;
    }

    /**
     * Kolom yang diizinkan untuk sebuah modul.
     * Return null = tidak ada allowlist eksplisit (pakai denylist saja).
     *
     * @return string[]|null
     */
    public function allowedFields(string $module): ?array
    {
        $p = $this->fieldPolicy[$module] ?? null;
        if (! is_array($p)) {
            return null;
        }

        $allow = $p['allow'] ?? null;
        if (! is_array($allow) || $allow === []) {
            return null;
        }

        return array_values(array_map(
            static fn ($c) => strtolower(trim((string) $c)),
            $allow
        ));
    }

    /** Kolom yang dilarang untuk sebuah modul (selalu lowercase). */
    public function deniedFields(string $module): array
    {
        $p = $this->fieldPolicy[$module] ?? null;
        if (! is_array($p)) {
            return [];
        }

        $deny = $p['deny'] ?? null;
        if (! is_array($deny)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($c) => strtolower(trim((string) $c)),
            $deny
        ), static fn ($c) => $c !== ''));
    }

    /** Gabungkan denylist kebijakan dengan denylist bawaan sistem. */
    public function effectiveDeniedFields(string $module): array
    {
        return array_values(array_unique(array_merge(
            $this->deniedFields($module),
            self::baselineDeniedFields()
        )));
    }

    /**
     * Kolom yang TIDAK PERNAH boleh keluar ke klien eksternal, apa pun
     * kebijakannya. Lantai absolut — admin tidak bisa membukanya lewat UI.
     *
     * @return string[]
     */
    public static function baselineDeniedFields(): array
    {
        return [
            'pass', 'password', 'passwd', 'kata_sandi', 'pin',
            'foto', 'photo', 'gambar', 'avatar',
            'nik_ktp', 'no_ktp', 'ktp',
            'penghasilan_ortu', 'penghasilan_orang_tua', 'gaji',
            'nama_ibu', 'nama_ibu_kandung',
            'agama',
            'token', 'secret', 'api_key',
        ];
    }

    /** Normalisasi scope; nilai tak dikenal jatuh ke paling ketat. */
    public static function normalizeScope(string $scope): string
    {
        $scope = strtolower(trim($scope));

        return in_array($scope, [self::SCOPE_SELF, self::SCOPE_UNIT, self::SCOPE_ROLE, self::SCOPE_ALL], true)
            ? $scope
            : self::SCOPE_SELF;
    }

    /**
     * Pecah CSV. Perhatikan: '*' sengaja TIDAK diperlakukan sebagai wildcard
     * di sini — ia menghasilkan daftar kosong, sehingga klien lama yang masih
     * memakai '*' tidak diam-diam mendapat akses penuh.
     *
     * @return string[]
     */
    private static function csv(string $s): array
    {
        $s = trim($s);
        if ($s === '' || $s === '*') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', preg_split('/[\s,]+/', $s) ?: []),
            static fn ($v) => $v !== ''
        ));
    }
}
