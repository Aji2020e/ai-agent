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
        'name', 'modules', 'skills', 'model',
        'require_hmac', 'hmac_secret', 'is_active',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /** Buat klien baru dan key pertama-nya. Return ['id'=>..., 'key'=> plaintext]. */
    public function createClient(string $name, string $modules = '*', string $skills = '*'): array
    {
        $id = $this->insert([
            'name'    => $name,
            'modules' => $modules,
            'skills'  => $skills,
        ]);

        $made = (new ApiKeyModel())->createKey((int) $id);

        return ['id' => $id, 'key_id' => $made['id'], 'key' => $made['key']];
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
