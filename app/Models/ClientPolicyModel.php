<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Kebijakan otorisasi per klien API (tabel `client_policies`).
 */
class ClientPolicyModel extends Model
{
    protected $table            = 'client_policies';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'client_id', 'scope', 'subject_required', 'subject_types',
        'unit_type', 'unit_ids', 'role_scope', 'field_policy',
        'row_limit', 'query_budget', 'tools_allowed', 'on_violation', 'notes',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /** Cache per-request agar tabel ini tidak di-query berulang. */
    private static array $cache = [];

    /**
     * Kebijakan untuk satu klien. `null` bila belum terdaftar
     * — pemanggil WAJIB menafsirkannya sebagai "tolak semua".
     */
    public function forClient(int $clientId): ?array
    {
        if (array_key_exists($clientId, self::$cache)) {
            return self::$cache[$clientId];
        }

        $row = $this->where('client_id', $clientId)->first();

        return self::$cache[$clientId] = ($row === null ? null : $row);
    }

    /** Kebijakan seluruh klien, diindeks menurut client_id (untuk layar admin). */
    public function allByClient(): array
    {
        $out = [];
        foreach ($this->findAll() as $row) {
            $out[(int) $row['client_id']] = $row;
        }

        return $out;
    }

    /** Buang cache — wajib dipanggil setelah insert/update/delete. */
    public static function flushCache(): void
    {
        self::$cache = [];
    }

    public function savePolicy(int $clientId, array $data): bool
    {
        $data['client_id'] = $clientId;
        unset($data['id'], $data['created_at'], $data['updated_at']);

        $existing = $this->where('client_id', $clientId)->first();
        $ok       = $existing === null
            ? (bool) $this->insert($data)
            : (bool) $this->update($existing['id'], $data);

        self::flushCache();

        return $ok;
    }
}
