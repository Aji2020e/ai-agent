<?php

namespace App\Models;

use CodeIgniter\Model;

class ApiModuleModel extends Model
{
    protected $table            = 'api_modules';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'slug', 'name', 'description', 'id_param', 'id_label',
        'query_config', 'examples', 'is_active',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function active(): array
    {
        return $this->where('is_active', 1)->orderBy('id', 'ASC')->findAll();
    }

    public function findBySlug(string $slug): ?array
    {
        if (! preg_match('/^[a-z0-9_]+$/', $slug)) {
            return null;
        }

        return $this->where('slug', $slug)->first();
    }

    /** @return string[] */
    public function examples(array $module): array
    {
        $ex = json_decode($module['examples'] ?? '', true);

        return is_array($ex) ? array_values(array_filter(array_map('trim', $ex))) : [];
    }

    /** @return array{profile:array,related:array[]} */
    public function queryConfig(array $module): array
    {
        $cfg = json_decode($module['query_config'] ?? '', true);

        return is_array($cfg) ? $cfg : [];
    }
}
