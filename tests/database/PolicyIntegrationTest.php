<?php

use App\Libraries\Auth\AccessPolicy;
use App\Libraries\Auth\PolicyGuard;
use App\Models\ApiClientModel;
use App\Models\AuthViolationModel;
use App\Models\ClientPolicyModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Uji integrasi lapisan otorisasi dengan database sungguhan.
 *
 * Memverifikasi rantai penuh: baris api_clients + client_policies →
 * AccessPolicy → PolicyGuard → pencatatan auth_violations.
 */
final class PolicyIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';
    protected $migrate   = true;
    protected $seed      = '';

    protected function setUp(): void
    {
        parent::setUp();
        PolicyGuard::reset();
        ClientPolicyModel::flushCache();
    }

    protected function tearDown(): void
    {
        PolicyGuard::reset();
        ClientPolicyModel::flushCache();
        parent::tearDown();
    }

    /** Buat klien API nyata beserta API key-nya. */
    private function makeClient(string $name, string $modules = 'mahasiswa'): array
    {
        return (new ApiClientModel())->createClient($name, $modules, 'general');
    }

    private function makePolicy(int $clientId, array $over = []): void
    {
        (new ClientPolicyModel())->savePolicy($clientId, array_merge([
            'scope'            => 'self',
            'subject_required' => 1,
            'subject_types'    => 'mahasiswa',
            'unit_type'        => null,
            'unit_ids'         => null,
            'role_scope'       => null,
            'field_policy'     => null,
            'row_limit'        => 50,
            'query_budget'     => 5,
            'tools_allowed'    => 'db_lookup',
            'on_violation'     => 'deny_and_log',
            'notes'            => 'uji',
        ], $over));

        ClientPolicyModel::flushCache();
    }

    // ------------------------------------------------------- rantai penuh

    public function testKebijakanTerbacaDariDatabase(): void
    {
        $made = $this->makeClient('Portal Mahasiswa Uji');
        $this->makePolicy((int) $made['id']);

        $row    = (new ClientPolicyModel())->forClient((int) $made['id']);
        $client = (new ApiClientModel())->find((int) $made['id']);

        $this->assertNotNull($row, 'baris kebijakan harus tersimpan');
        $this->assertSame('self', $row['scope']);

        $policy = AccessPolicy::fromClient($client, $row, '202110110', 'mahasiswa');

        $this->assertSame('202110110', $policy->subjectId);
        $this->assertTrue($policy->allowsModule('mahasiswa'));
        $this->assertFalse($policy->allowsModule('dosen'));
        $this->assertTrue($policy->allowsTool('db_lookup'));
        $this->assertFalse($policy->allowsTool('file_reader'));
    }

    /**
     * Klien yang ada di api_clients tapi belum punya baris kebijakan
     * tidak boleh mendapat akses apa pun.
     */
    public function testKlienTanpaKebijakanDitolak(): void
    {
        $made   = $this->makeClient('Klien Belum Didaftarkan');
        $client = (new ApiClientModel())->find((int) $made['id']);

        $policy = AccessPolicy::fromClient($client, null, '202110110', 'mahasiswa');
        PolicyGuard::setPolicy($policy);

        $this->assertFalse($policy->allowsModule('mahasiswa'));
        $this->expectException(RuntimeException::class);
        PolicyGuard::enforce('mahasiswa', 'mhs', ['npm'], [], 10);
    }

    // ------------------------------------------------------- audit trail

    public function testPelanggaranTercatatKeDatabase(): void
    {
        $made = $this->makeClient('Portal Uji Audit');
        $this->makePolicy((int) $made['id']);

        $client = (new ApiClientModel())->find((int) $made['id']);
        $row    = (new ClientPolicyModel())->forClient((int) $made['id']);

        PolicyGuard::setPolicy(AccessPolicy::fromClient($client, $row, '202110110', 'mahasiswa'));

        try {
            // Percobaan mengakses data mahasiswa lain
            PolicyGuard::enforce('mahasiswa', 'mhs', ['npm'], ['npm' => '202199999'], 10);
            $this->fail('Seharusnya ditolak');
        } catch (RuntimeException) {
            // diharapkan
        }

        $v = (new AuthViolationModel())
            ->where('client_id', (int) $made['id'])
            ->where('violation', 'idor')
            ->findAll();

        $this->assertNotEmpty($v, 'percobaan IDOR harus tercatat di auth_violations');
        $this->assertSame('202199999', $v[0]['attempted']);
        $this->assertSame('202110110', $v[0]['allowed']);
        $this->assertSame('202110110', $v[0]['subject_id']);
    }

    public function testPelanggaranKolomTerlarangTercatat(): void
    {
        $made = $this->makeClient('Portal Uji Kolom');
        $this->makePolicy((int) $made['id'], [
            'field_policy' => json_encode(['mahasiswa' => ['deny' => ['no_hp']]]),
        ]);

        $client = (new ApiClientModel())->find((int) $made['id']);
        $row    = (new ClientPolicyModel())->forClient((int) $made['id']);
        PolicyGuard::setPolicy(AccessPolicy::fromClient($client, $row, '202110110', 'mahasiswa'));

        $out = PolicyGuard::enforce('mahasiswa', 'mhs', ['npm', 'no_hp'], [], 10);

        $this->assertNotContains('no_hp', $out['cols']);

        $v = (new AuthViolationModel())
            ->where('client_id', (int) $made['id'])
            ->where('violation', 'field_denied')
            ->findAll();

        $this->assertNotEmpty($v);
        $this->assertSame('no_hp', $v[0]['attempted']);
    }

    public function testTallyMengelompokkanPelanggaran(): void
    {
        $made = $this->makeClient('Portal Uji Tally');
        $this->makePolicy((int) $made['id']);

        $client = (new ApiClientModel())->find((int) $made['id']);
        $row    = (new ClientPolicyModel())->forClient((int) $made['id']);
        PolicyGuard::setPolicy(AccessPolicy::fromClient($client, $row, '202110110', 'mahasiswa'));

        for ($i = 0; $i < 3; $i++) {
            try {
                PolicyGuard::enforce('mahasiswa', 'mhs', ['npm'], ['npm' => '2021999' . $i], 10);
            } catch (RuntimeException) {
                // diharapkan
            }
        }

        $tally = (new AuthViolationModel())->tally(60, 'idor');

        $this->assertNotEmpty($tally);
        $found = false;
        foreach ($tally as $t) {
            if ((int) $t['client_id'] === (int) $made['id']) {
                $this->assertSame(3, (int) $t['n'], 'tiga percobaan harus terhitung');
                $this->assertSame('Portal Uji Tally', $t['client_name']);
                $found = true;
            }
        }
        $this->assertTrue($found, 'klien uji harus muncul di tally');
    }

    // ------------------------------------------------------- cache

    public function testCacheKebijakanDibuangSetelahUpdate(): void
    {
        $made = $this->makeClient('Portal Uji Cache');
        $this->makePolicy((int) $made['id'], ['row_limit' => 10]);

        $m = new ClientPolicyModel();
        $this->assertSame(10, (int) $m->forClient((int) $made['id'])['row_limit']);

        // Ubah lewat savePolicy (yang seharusnya membuang cache)
        $this->makePolicy((int) $made['id'], ['row_limit' => 77]);

        $this->assertSame(77, (int) (new ClientPolicyModel())->forClient((int) $made['id'])['row_limit'],
            'cache harus dibuang agar nilai baru terbaca');
    }

    public function testKebijakanTerhapusBilaKlienDihapus(): void
    {
        $made = $this->makeClient('Portal Uji Cascade');
        $this->makePolicy((int) $made['id']);

        $this->assertNotNull((new ClientPolicyModel())->forClient((int) $made['id']));

        (new ApiClientModel())->delete((int) $made['id']);
        ClientPolicyModel::flushCache();

        $this->assertNull((new ClientPolicyModel())->forClient((int) $made['id']),
            'ON DELETE CASCADE harus ikut menghapus kebijakan');
    }
}
