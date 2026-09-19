<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\ApiClientModel;
use App\Models\ApiKeyModel;

class TestApiKeys extends BaseCommand
{
    protected $group       = 'App';
    protected $name        = 'app:test-api-keys';
    protected $description = 'Test multi API key creation and lookup';

    public function run(array $params)
    {
        $clientModel = new ApiClientModel();
        $keyModel    = new ApiKeyModel();

        // Buat client
        $client = $clientModel->createClient('test-client-' . time(), 'mahasiswa', 'academic');
        CLI::write("Client created: {$client['id']}");

        // Tambah key kedua
        $key2 = $keyModel->createKey((int) $client['id'], ['expires_at' => null, 'ip_allowlist' => '127.0.0.1']);
        CLI::write("Second key created: prefix " . substr($key2['key'], 0, 8));

        // Lookup first key
        $found1 = $keyModel->findByKey($client['key']);
        if ($found1 && $found1['client_name'] === 'test-client-' . time()) {
            CLI::write("First key lookup: OK");
        } else {
            CLI::write("First key lookup: FAILED", 'red');
        }

        // Lookup second key
        $found2 = $keyModel->findByKey($key2['key']);
        if ($found2 && $found2['client_id'] === (string) $client['id']) {
            CLI::write("Second key lookup: OK");
        } else {
            CLI::write("Second key lookup: FAILED", 'red');
        }

        // Cleanup
        $clientModel->delete($client['id']);
        CLI::write("Cleanup done.");
    }
}
