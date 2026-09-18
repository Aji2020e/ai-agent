<?php

namespace App\Models;

use CodeIgniter\Model;

class SkillModel extends Model
{
    protected $table            = 'skills';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'name', 'display_name', 'description', 'handler_class', 'icon', 'is_active', 'order_index',
    ];
    protected $useTimestamps = true;

    public function getActiveSkills(): array
    {
        return $this->where('is_active', 1)
                    ->orderBy('order_index', 'ASC')
                    ->findAll();
    }

    public function findByName(string $name): ?array
    {
        return $this->where('name', $name)->first();
    }
}
