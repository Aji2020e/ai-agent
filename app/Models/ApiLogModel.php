<?php

namespace App\Models;

use CodeIgniter\Model;

class ApiLogModel extends Model
{
    protected $table            = 'api_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    // PERBAIKAN BUG LAMA: 'created_at' diisi di log() tapi tidak terdaftar di
    // sini, sehingga $protectFields membuangnya dan SELURUH baris api_logs
    // selama ini bertimestamp NULL.
    protected $allowedFields    = [
        'client_id', 'module', 'endpoint', 'tokens_used', 'status', 'created_at',
    ];
    protected $useTimestamps = false;
    protected $createdField  = 'created_at';

    public function log(?int $clientId, string $module, string $endpoint, int $tokens = 0, string $status = 'ok'): void
    {
        $this->insert([
            'client_id'   => $clientId,
            'module'      => $module,
            'endpoint'    => $endpoint,
            'tokens_used' => $tokens,
            'status'      => $status,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function recent(int $limit = 50): array
    {
        return $this->select('api_logs.*, api_clients.name as client_name')
                    ->join('api_clients', 'api_clients.id = api_logs.client_id', 'left')
                    ->orderBy('api_logs.id', 'DESC')
                    ->limit($limit)
                    ->findAll();
    }
}
