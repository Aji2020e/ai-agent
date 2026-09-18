<?php

namespace App\Models;

use CodeIgniter\Model;

class ApiClientModel extends Model
{
    protected $table            = 'api_clients';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'name', 'modules', 'skills', 'model', 'api_key_hash', 'key_prefix', 'ip_allowlist',
        'expires_at', 'hmac_secret', 'require_hmac', 'is_active', 'last_used',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /** Buat klien baru. Return ['id'=>..., 'key'=> plaintext (tampil sekali!)]. */
    public function createClient(string $name, string $modules = '*', string $skills = '*'): array
    {
        $key = bin2hex(random_bytes(32));

        $id = $this->insert([
            'name'         => $name,
            'modules'      => $modules,
            'skills'       => $skills,
            'api_key_hash' => hash('sha256', $key),
            'key_prefix'   => substr($key, 0, 8),
            'is_active'    => 1,
        ]);

        return ['id' => $id, 'key' => $key];
    }

    public function findByKey(string $key): ?array
    {
        if ($key === '') {
            return null;
        }

        return $this->where('api_key_hash', hash('sha256', $key))->first();
    }

    public function allowsModule(array $client, string $module): bool
    {
        if (($client['modules'] ?? '') === '*') {
            return true;
        }

        return in_array($module, array_map('trim', explode(',', (string) $client['modules'])), true);
    }

    public function allowsSkill(array $client, string $skill): bool
    {
        if (($client['skills'] ?? '') === '*') {
            return true;
        }

        return in_array($skill, array_map('trim', explode(',', (string) $client['skills'])), true);
    }
}
