<?php

use App\Libraries\Auth\AccessPolicy;
use App\Libraries\Auth\PolicyGuard;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * PolicyGuard adalah satu-satunya tempat keputusan otorisasi data diambil.
 * Test ini memverifikasi empat lubang yang ditutup:
 *   H1 IDOR · H2 peran dari request · H3 gagal-terbuka · H4 tool AI liar
 */
final class PolicyGuardTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PolicyGuard::reset();
    }

    protected function tearDown(): void
    {
        PolicyGuard::reset();
        parent::tearDown();
    }

    /** Kebijakan portal mahasiswa: scope self, menegakkan. */
    private function portalMahasiswa(string $subject = '202110110'): AccessPolicy
    {
        return AccessPolicy::fromClient(
            ['id' => 3, 'name' => 'Portal Mahasiswa', 'modules' => 'mahasiswa'],
            [
                'scope'            => 'self',
                'subject_required' => 1,
                'subject_types'    => 'mahasiswa',
                'unit_type'        => null,
                'unit_ids'         => null,
                'role_scope'       => null,
                'field_policy'     => json_encode(['mahasiswa' => [
                    'allow' => ['npm', 'nama', 'kd_jur', 'semester', 'sks_kumulatif', 'kode_mk', 'nilai'],
                    'deny'  => ['no_hp', 'alamat'],
                ]]),
                'row_limit'        => 50,
                'query_budget'     => 3,
                'tools_allowed'    => 'db_lookup',
                'on_violation'     => 'deny_and_log',
            ],
            $subject,
            'mahasiswa'
        );
    }

    // ================================================================ H1 · IDOR

    /**
     * Inti perbaikan: subject terverifikasi DISUNTIKKAN ke WHERE, sehingga
     * query tidak pernah bisa menyentuh baris orang lain.
     */
    public function testScopeSelfMenyuntikkanSubjectKeWhere(): void
    {
        PolicyGuard::setPolicy($this->portalMahasiswa('202110110'));

        // Pemanggil tidak menyebut NIM sama sekali
        $out = PolicyGuard::enforce('mahasiswa', 'mhs', ['npm', 'nama'], [], 10);

        $this->assertSame('202110110', $out['where']['npm'], 'subject harus disuntikkan');
    }

    /**
     * Subject juga disuntikkan bila pemanggil menyaring hal lain (semester dsb.)
     * — filter tambahan boleh, tapi identitas tetap dikunci.
     */
    public function testScopeSelfMempertahankanFilterLainTapiMengunciIdentitas(): void
    {
        PolicyGuard::setPolicy($this->portalMahasiswa('202110110'));

        $out = PolicyGuard::enforce(
            'mahasiswa',
            'nilai',
            ['npm', 'nilai'],
            ['semester' => '20251'],
            10
        );

        $this->assertSame('202110110', $out['where']['npm']);
        $this->assertSame('20251', $out['where']['semester'], 'filter sah tidak boleh hilang');
    }

    public function testPercobaanIdorDitolakSaatMenegakkan(): void
    {
        PolicyGuard::setPolicy($this->portalMahasiswa('202110110'));

        $this->expectException(RuntimeException::class);
        // Pesan harus generik — tidak boleh membocorkan alasan rinci
        $this->expectExceptionMessage('Data tersebut tidak tersedia dalam wewenang aplikasi ini.');

        PolicyGuard::enforce('mahasiswa', 'mhs', ['npm'], ['npm' => '202199999'], 10);
    }

    public function testModeLogOnlyMencatatTapiTidakMemutus(): void
    {
        $p = AccessPolicy::fromClient(
            ['id' => 4, 'name' => 'Klien Rollout', 'modules' => 'mahasiswa'],
            [
                'scope' => 'self', 'subject_required' => 1, 'subject_types' => 'mahasiswa',
                'unit_type' => null, 'unit_ids' => null, 'role_scope' => null,
                'field_policy' => null, 'row_limit' => 50, 'query_budget' => 8,
                'tools_allowed' => 'db_lookup', 'on_violation' => 'log_only',
            ],
            '202110110',
            'mahasiswa'
        );
        PolicyGuard::setPolicy($p);

        // Tidak melempar — ini yang membuat rollout aman
        $out = PolicyGuard::enforce('mahasiswa', 'mhs', ['npm', 'nama'], ['npm' => '202199999'], 10);

        $this->assertSame('202110110', $out['where']['npm'], 'meski log_only, subject tetap dipaksa');
    }

    public static function selfSubjectMatchesExactlyProvider(): array
    {
        return [
            'identik'            => ['202110110', '202110110'],
            'berpadding spasi'   => ['  202110110  ', '202110110'],
            'dengan pemisah'     => ['2021-10-110', '202110110'],
        ];
    }

    #[DataProvider('selfSubjectMatchesExactlyProvider')]
    public function testSubjectSamaDalamVariasiFormatTidakDianggapIdor(string $attempt, string $subject): void
    {
        PolicyGuard::setPolicy($this->portalMahasiswa($subject));

        // Data akademik legacy sering berpadding — jangan sampai pemilik sah ditolak
        $out = PolicyGuard::enforce('mahasiswa', 'mhs', ['npm'], ['npm' => $attempt], 10);

        $this->assertSame($subject, $out['where']['npm']);
    }

    public function testScopeSelfTanpaSubjectDitolak(): void
    {
        $p = AccessPolicy::fromClient(
            ['id' => 5, 'name' => 'Portal Tanpa Subject', 'modules' => 'mahasiswa'],
            [
                'scope' => 'self', 'subject_required' => 1, 'subject_types' => 'mahasiswa',
                'unit_type' => null, 'unit_ids' => null, 'role_scope' => null,
                'field_policy' => null, 'row_limit' => 50, 'query_budget' => 8,
                'tools_allowed' => '', 'on_violation' => 'deny_and_log',
            ],
            null,          // ← tidak ada subject
            null
        );
        PolicyGuard::setPolicy($p);

        $this->expectException(RuntimeException::class);
        PolicyGuard::enforce('mahasiswa', 'mhs', ['npm'], [], 10);
    }

    // ================================================================ modul

    public function testModulDiLuarKebijakanDitolak(): void
    {
        PolicyGuard::setPolicy($this->portalMahasiswa());

        $this->expectException(RuntimeException::class);
        PolicyGuard::enforce('dosen', 'dosen_table', ['nidn'], [], 10);
    }

    public function testTanpaKebijakanSamaSekaliTidakAdaData(): void
    {
        // PolicyGuard::policy() === null
        $this->assertNull(PolicyGuard::policy());

        $this->expectException(RuntimeException::class);
        PolicyGuard::enforce('mahasiswa', 'mhs', ['npm'], [], 10);
    }

    public function testAnggaranQueryPerRequestDitegakkan(): void
    {
        PolicyGuard::setPolicy($this->portalMahasiswa());   // query_budget = 3

        PolicyGuard::enforce('mahasiswa', 'mhs', ['npm'], [], 10);   // 1
        PolicyGuard::enforce('mahasiswa', 'nilai', ['npm'], [], 10);  // 2
        PolicyGuard::enforce('mahasiswa', 'ips', ['npm'], [], 10);    // 3

        $this->expectException(RuntimeException::class);
        PolicyGuard::enforce('mahasiswa', 'krs', ['npm'], [], 10);    // 4 → ditolak
    }

    public function testBatasBarisDiturunkanKeKebijakan(): void
    {
        PolicyGuard::setPolicy($this->portalMahasiswa());   // row_limit = 50

        $out = PolicyGuard::enforce('mahasiswa', 'mhs', ['npm'], [], 5000);

        $this->assertSame(50, $out['limit'], 'limit pemanggil tidak boleh melewati kebijakan');
    }

    // ================================================================ kolom

    public function testKolomDilarangDibuang(): void
    {
        PolicyGuard::setPolicy($this->portalMahasiswa());

        $out = PolicyGuard::enforce(
            'mahasiswa',
            'mhs',
            ['npm', 'nama', 'no_hp', 'alamat', 'pass'],
            [],
            10
        );

        $this->assertContains('npm', $out['cols']);
        $this->assertContains('nama', $out['cols']);
        $this->assertNotContains('no_hp', $out['cols'], 'denylist kebijakan');
        $this->assertNotContains('alamat', $out['cols'], 'denylist kebijakan');
        $this->assertNotContains('pass', $out['cols'], 'lantai absolut sistem');
    }

    public function testKolomDiLuarAllowlistDibuang(): void
    {
        PolicyGuard::setPolicy($this->portalMahasiswa());

        $out = PolicyGuard::enforce(
            'mahasiswa',
            'mhs',
            ['npm', 'nama', 'penghasilan_ortu', 'nama_ibu', 'agama'],
            [],
            10
        );

        $this->assertSame(['npm', 'nama'], array_values($out['cols']));
    }

    public function testKolomIdentitasSelaluDisertakan(): void
    {
        PolicyGuard::setPolicy($this->portalMahasiswa());

        // Pemanggil tidak meminta npm, tapi guard menambahkannya agar
        // hasil bisa dipetakan ke subject.
        $out = PolicyGuard::enforce('mahasiswa', 'mhs', ['nama'], [], 10);

        $this->assertSame('npm', $out['cols'][0]);
    }

    public function testSemuaKolomDitolakMembuatQueryGagal(): void
    {
        PolicyGuard::setPolicy($this->portalMahasiswa());

        $this->expectException(RuntimeException::class);
        PolicyGuard::enforce('mahasiswa', 'mhs', ['pass', 'foto', 'no_hp'], [], 10);
    }

    // ================================================================ H4 · tool

    public function testToolDiLuarKebijakanDitolak(): void
    {
        PolicyGuard::setPolicy($this->portalMahasiswa());   // tools: db_lookup saja

        $this->assertTrue(PolicyGuard::canUseTool('db_lookup'));
        $this->assertFalse(PolicyGuard::canUseTool('file_reader'), 'H4b: baca file harus mati utk klien luar');
        $this->assertFalse(PolicyGuard::canUseTool('web_search'));
    }

    public function testAssertToolMelempar(): void
    {
        PolicyGuard::setPolicy($this->portalMahasiswa());

        $this->expectException(RuntimeException::class);
        PolicyGuard::assertTool('file_reader');
    }

    /**
     * H4a: identifier yang dipakai tool harus berasal dari kebijakan,
     * bukan dari parameter yang bisa diisi AI.
     */
    public function testForcedSubjectMenimpaIdentifierDariAi(): void
    {
        PolicyGuard::setPolicy($this->portalMahasiswa('202110110'));

        // AI/pemanggil meminta NIM orang lain
        $this->expectException(RuntimeException::class);
        PolicyGuard::forcedSubject('mahasiswa', '202199999');
    }

    public function testForcedSubjectMengembalikanSubjectSah(): void
    {
        PolicyGuard::setPolicy($this->portalMahasiswa('202110110'));

        $this->assertSame('202110110', PolicyGuard::forcedSubject('mahasiswa', '202110110'));
        $this->assertSame('202110110', PolicyGuard::forcedSubject('mahasiswa', null));
    }

    public function testForcedSubjectTidakMengikatUntukScopeUnit(): void
    {
        // Klien admin bagian boleh menyebut ID siapa pun dalam unitnya;
        // pembatasannya diterapkan lewat WHERE unit, bukan lewat subject.
        $p = AccessPolicy::fromClient(
            ['id' => 6, 'name' => 'Admin BAAK FT', 'modules' => 'mahasiswa'],
            [
                'scope' => 'unit', 'subject_required' => 0, 'subject_types' => '',
                'unit_type' => 'fakultas', 'unit_ids' => 'FT', 'role_scope' => null,
                'field_policy' => null, 'row_limit' => 500, 'query_budget' => 20,
                'tools_allowed' => 'db_lookup', 'on_violation' => 'deny_and_log',
            ],
            null,
            null
        );
        PolicyGuard::setPolicy($p);

        $this->assertSame('202199999', PolicyGuard::forcedSubject('mahasiswa', '202199999'));
    }

    // ================================================================ unit scope

    public function testScopeUnitMenyuntikkanFilterUnit(): void
    {
        $p = AccessPolicy::fromClient(
            ['id' => 6, 'name' => 'Admin BAAK FT', 'modules' => 'mahasiswa'],
            [
                'scope' => 'unit', 'subject_required' => 0, 'subject_types' => '',
                'unit_type' => 'fakultas', 'unit_ids' => 'FT', 'role_scope' => null,
                'field_policy' => null, 'row_limit' => 500, 'query_budget' => 20,
                'tools_allowed' => '', 'on_violation' => 'deny_and_log',
            ],
            null,
            null
        );
        PolicyGuard::setPolicy($p);

        // Pemanggil mencoba meminta fakultas lain — harus ditimpa
        $out = PolicyGuard::enforce('mahasiswa', 'mhs', ['npm'], ['kd_fakultas' => 'FE'], 100);

        $this->assertSame(['FT'], $out['where']['kd_fakultas'], 'unit pemanggil harus ditimpa unit kebijakan');
    }

    public function testScopeUnitTanpaUnitIdsDitolak(): void
    {
        // Konfigurasi cacat tidak boleh berubah menjadi "tanpa filter"
        $p = AccessPolicy::fromClient(
            ['id' => 8, 'name' => 'Klien Cacat', 'modules' => 'mahasiswa'],
            [
                'scope' => 'unit', 'subject_required' => 0, 'subject_types' => '',
                'unit_type' => 'fakultas', 'unit_ids' => '', 'role_scope' => null,
                'field_policy' => null, 'row_limit' => 50, 'query_budget' => 8,
                'tools_allowed' => '', 'on_violation' => 'deny_and_log',
            ],
            null,
            null
        );
        PolicyGuard::setPolicy($p);

        $this->expectException(RuntimeException::class);
        PolicyGuard::enforce('mahasiswa', 'mhs', ['npm'], [], 10);
    }

    // ================================================================ utilitas

    public function testSameIdMenanganiDataLegacy(): void
    {
        $this->assertTrue(PolicyGuard::sameId('202110110', '202110110'));
        $this->assertTrue(PolicyGuard::sameId('  202110110  ', '202110110'), 'padding spasi');
        $this->assertTrue(PolicyGuard::sameId('2021-10-110', '202110110'), 'pemisah');
        $this->assertFalse(PolicyGuard::sameId('202110110', '202199999'));
        $this->assertFalse(PolicyGuard::sameId('', '202110110'), 'kosong tidak boleh cocok');
        $this->assertFalse(PolicyGuard::sameId('', ''), 'kosong vs kosong tidak boleh cocok');
    }

    public function testResetMembersihkanStatus(): void
    {
        PolicyGuard::setPolicy($this->portalMahasiswa());
        $this->assertTrue(PolicyGuard::active());

        PolicyGuard::reset();

        $this->assertFalse(PolicyGuard::active());
        $this->assertNull(PolicyGuard::policy());
        $this->assertNull(PolicyGuard::subjectId());
    }
}
