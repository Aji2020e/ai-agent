<?php

namespace App\Models;

use CodeIgniter\Model;

class ApiKeyModel extends Model
{
    protected $table            = 'api_keys';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'client_id', 'api_key_hash', 'key_prefix', 'expires_at',
        'ip_allowlist', 'is_active', 'last_used',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /** Buat key baru untuk client. Return ['id'=>..., 'key'=> plaintext]. */
    public function createKey(int $clientId, ?array $overrides = null): array
    {
        $key = bin2hex(random_bytes(32));

        $data = [
            'client_id'    => $clientId,
            'api_key_hash' => hash('sha256', $key),
            'key_prefix'   => substr($key, 0, 8),
            'is_active'    => 1,
        ];

        if (! empty($overrides['expires_at'])) {
            $data['expires_at'] = $overrides['expires_at'];
        }
        if (isset($overrides['ip_allowlist'])) {
            $data['ip_allowlist'] = $overrides['ip_allowlist'];
        }

        $id = $this->insert($data);

        return ['id' => $id, 'key' => $key];
    }

    /** Cari key berdasarkan plaintext, gabung dengan data klien. */
    public function findByKey(string $key): ?array
    {
        if ($key === '') {
            return null;
        }

        $row = $this->select('api_keys.id as key_id, api_keys.client_id, api_keys.api_key_hash, api_keys.key_prefix, api_keys.expires_at, api_keys.ip_allowlist, api_keys.is_active, api_keys.last_used, api_keys.created_at, api_keys.updated_at, api_clients.id as id, api_clients.name as client_name, api_clients.modules, api_clients.skills, api_clients.model, api_clients.require_hmac, api_clients.hmac_secret')
                    ->join('api_clients', 'api_clients.id = api_keys.client_id')
                    ->where('api_keys.api_key_hash', hash('sha256', $key))
                    ->where('api_keys.is_active', 1)
                    ->where('api_clients.is_active', 1)
                    ->first();

        if ($row === null) {
            return null;
        }

        // Normalisasi nama kolong agar filter & controller tidak perlu banyak ubah
        $row['client_id_val'] = $row['client_id'];

        return $row;
    }

    public function forClient(int $clientId): array
    {
        return $this->where('client_id', $clientId)
                    ->orderBy('id', 'DESC')
                    ->findAll();
    }

    public function touch(int $id): void
    {
        $this->update($id, ['last_used' => date('Y-m-d H:i:s')]);
    }

    public function toggle(int $id): void
    {
        $row = $this->find($id);
        if ($row) {
            $this->update($id, ['is_active' => $row['is_active'] ? 0 : 1]);
        }
    }
}
