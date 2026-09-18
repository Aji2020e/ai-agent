<?php

use App\Libraries\Auth\AccessPolicy;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Kebijakan akses: memastikan "gagal-tertutup" benar-benar tertutup.
 */
final class AccessPolicyTest extends CIUnitTestCase
{
    private function client(array $over = []): array
    {
        return array_merge([
            'id'      => 7,
            'name'    => 'Portal Mahasiswa',
            'modules' => 'mahasiswa',
            'skills'  => 'student_profile,general',
        ], $over);
    }

    private function policyRow(array $over = []): array
    {
        return array_merge([
            'scope'            => 'self',
            'subject_required' => 1,
            'subject_types'    => 'mahasiswa',
            'unit_type'        => null,
            'unit_ids'         => null,
            'role_scope'       => null,
            'field_policy'     => null,
            'row_limit'        => 50,
            'query_budget'     => 8,
            'tools_allowed'    => 'db_lookup',
            'on_violation'     => 'deny_and_log',
        ], $over);
    }

    // ------------------------------------------------- gagal-tertutup

    public function testKlienTanpaBarisKebijakanTidakMendapatApaPun(): void
    {
        $p = AccessPolicy::fromClient($this->client(), null, '202110110', 'mahasiswa');

        $this->assertSame([], $p->modules, 'Klien tanpa policy tidak boleh punya modul');
        $this->assertFalse($p->allowsModule('mahasiswa'));
        $this->assertFalse($p->allowsTool('db_lookup'));
        $this->assertTrue($p->isEnforcing(), 'Tanpa policy harus menegakkan (menolak)');
        $this->assertSame(0, $p->rowLimit);
        $this->assertSame(0, $p->queryBudget);
    }

    /**
     * PERUBAHAN SENGAJA dari perilaku lama: '*' tidak lagi berarti "semua".
     * Klien lama yang masih memakai wildcard tidak boleh diam-diam dapat akses penuh.
     */
    public function testWildcardTidakLagiBerartiSemua(): void
    {
        $p = AccessPolicy::fromClient(
            $this->client(['modules' => '*']),
            $this->policyRow(),
            '202110110',
            'mahasiswa'
        );

        $this->assertSame([], $p->modules);
        $this->assertFalse($p->allowsModule('mahasiswa'));
        $this->assertFalse($p->allowsModule('dosen'));
    }

    /**
     * scope dan modules adalah dua sumbu berbeda. scope='all' menghapus batasan
     * BARIS, bukan batasan MODUL. Sebelumnya scope='all' mem-bypass daftar modul
     * dan itu memperluas akses klien lama saat migrasi.
     */
    public function testScopeAllTidakMemBypassDaftarModul(): void
    {
        $p = AccessPolicy::fromClient(
            $this->client(['modules' => 'mahasiswa']),
            $this->policyRow(['scope' => 'all']),
            null,
            null
        );

        $this->assertTrue($p->allowsModule('mahasiswa'), 'modul yang terdaftar tetap boleh');
        $this->assertFalse($p->allowsModule('dosen'), 'scope all TIDAK membuka modul lain');
        $this->assertFalse($p->requiresSubject());
    }

    public function testDaftarModulKosongBerartiTidakAdaModul(): void
    {
        $p = AccessPolicy::fromClient(
            $this->client(['modules' => '']),
            $this->policyRow(['scope' => 'all']),
            null,
            null
        );

        $this->assertFalse($p->allowsModule('mahasiswa'));
        $this->assertFalse($p->bypassModules);
    }

    // ------------------------------------------------- normalisasi

    public function testScopeTakDikenalJatuhKePalingKetat(): void
    {
        $p = AccessPolicy::fromClient(
            $this->client(),
            $this->policyRow(['scope' => 'superadmin']),
            '202110110',
            'mahasiswa'
        );

        $this->assertSame(AccessPolicy::SCOPE_SELF, $p->scope);
        $this->assertTrue($p->requiresSubject());
    }

    public function testOnViolationTakDikenalJatuhKeLogOnly(): void
    {
        // Default rollout adalah log_only — lebih aman salah ke arah tidak memutus.
        $p = AccessPolicy::fromClient(
            $this->client(),
            $this->policyRow(['on_violation' => 'garbage']),
            '1',
            'mahasiswa'
        );

        $this->assertSame('log_only', $p->onViolation);
        $this->assertFalse($p->isEnforcing());
    }

    public function testCsvDibersihkanDariSpasiDanKosong(): void
    {
        $p = AccessPolicy::fromClient(
            $this->client(['modules' => ' mahasiswa , , dosen ']),
            $this->policyRow(['tools_allowed' => 'db_lookup,, web_search ']),
            '1',
            'mahasiswa'
        );

        $this->assertSame(['mahasiswa', 'dosen'], $p->modules);
        $this->assertSame(['db_lookup', 'web_search'], $p->toolsAllowed);
    }

    // ------------------------------------------------- subject

    public function testTipeSubjekDibatasiKebijakan(): void
    {
        $p = AccessPolicy::fromClient(
            $this->client(),
            $this->policyRow(['subject_types' => 'mahasiswa']),
            '202110110',
            'mahasiswa'
        );

        $this->assertTrue($p->allowsSubjectType('mahasiswa'));
        $this->assertTrue($p->allowsSubjectType('MAHASISWA'), 'harus case-insensitive');
        $this->assertFalse($p->allowsSubjectType('dosen'), 'portal mahasiswa tidak boleh mengaku dosen');
        $this->assertFalse($p->allowsSubjectType(''), 'tipe kosong tidak sah');
    }

    public function testTipeSubjekKosongBerartiSemuaTipeDiterima(): void
    {
        $p = AccessPolicy::fromClient(
            $this->client(),
            $this->policyRow(['subject_types' => '']),
            '1',
            'staff'
        );

        $this->assertTrue($p->allowsSubjectType('staff'));
        $this->assertTrue($p->allowsSubjectType('dosen'));
    }

    public function testSubjectDinormalkan(): void
    {
        $p = AccessPolicy::fromClient(
            $this->client(),
            $this->policyRow(),
            '  202110110  ',
            '  Mahasiswa '
        );

        $this->assertSame('202110110', $p->subjectId);
        $this->assertSame('mahasiswa', $p->subjectType);
    }

    public function testSubjectKosongJadiNull(): void
    {
        $p = AccessPolicy::fromClient($this->client(), $this->policyRow(), '   ', '');

        $this->assertNull($p->subjectId);
        $this->assertNull($p->subjectType);
    }

    // ------------------------------------------------- kolom

    public function testLantaiAbsolutSelaluDitolakApaPunKebijakannya(): void
    {
        // Admin secara eksplisit "mengizinkan" password — tetap harus ditolak.
        $p = AccessPolicy::fromClient(
            $this->client(),
            $this->policyRow(['field_policy' => json_encode([
                'mahasiswa' => ['allow' => ['npm', 'nama', 'pass', 'foto']],
            ])]),
            '1',
            'mahasiswa'
        );

        $denied = $p->effectiveDeniedFields('mahasiswa');

        $this->assertContains('pass', $denied);
        $this->assertContains('foto', $denied);
        $this->assertContains('penghasilan_ortu', $denied);

        // allowlist memang memuatnya, tapi penyaringan di PolicyGuard
        // menerapkan denylist LEBIH DULU, jadi tetap terbuang.
        $this->assertSame(['npm', 'nama', 'pass', 'foto'], $p->allowedFields('mahasiswa'));
    }

    public function testAllowlistKosongBerartiPakaiDenylistSaja(): void
    {
        $p = AccessPolicy::fromClient(
            $this->client(),
            $this->policyRow(['field_policy' => json_encode(['mahasiswa' => ['allow' => []]])]),
            '1',
            'mahasiswa'
        );

        $this->assertNull($p->allowedFields('mahasiswa'));
    }

    public function testFieldPolicyJsonRusakTidakMeledak(): void
    {
        $p = AccessPolicy::fromClient(
            $this->client(),
            $this->policyRow(['field_policy' => '{ini bukan json']),
            '1',
            'mahasiswa'
        );

        $this->assertSame([], $p->fieldPolicy);
        $this->assertNull($p->allowedFields('mahasiswa'));
        // denylist bawaan tetap berlaku meski JSON-nya rusak
        $this->assertContains('pass', $p->effectiveDeniedFields('mahasiswa'));
    }

    // ------------------------------------------------- unit scope

    public function testUnitScopeHanyaTerbentukBilaLengkap(): void
    {
        $lengkap = AccessPolicy::fromClient(
            $this->client(),
            $this->policyRow(['scope' => 'unit', 'unit_type' => 'fakultas', 'unit_ids' => 'FT,FMIPA']),
            null,
            null
        );
        $this->assertSame(['type' => 'fakultas', 'ids' => ['FT', 'FMIPA']], $lengkap->unitScope);

        // unit_ids ada tapi unit_type kosong → tidak boleh membentuk scope
        $cacat = AccessPolicy::fromClient(
            $this->client(),
            $this->policyRow(['scope' => 'unit', 'unit_type' => '', 'unit_ids' => 'FT']),
            null,
            null
        );
        $this->assertNull($cacat->unitScope, 'unit tanpa tipe harus null agar ditolak, bukan jadi tanpa filter');
    }

    // ------------------------------------------------- internal

    public function testKebijakanInternalDitandai(): void
    {
        $p = AccessPolicy::internalWebSession('202110110', 'mahasiswa');

        $this->assertTrue($p->isInternal());
        $this->assertSame(AccessPolicy::SCOPE_SELF, $p->scope);
        $this->assertSame(['db_lookup', 'web_search'], $p->toolsAllowed);
        $this->assertFalse($p->allowsTool('file_reader'), 'web internal pun tidak boleh baca file sembarang');
    }
}
