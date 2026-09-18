<?php

namespace App\Models;

use CodeIgniter\Model;

class SkillModuleModel extends Model
{
    protected $table            = 'skill_modules';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'skill_id', 'module_name', 'description', 'module_config', 'is_active',
    ];
    protected $useTimestamps = true;

    public function getModulesBySkill(int $skillId): array
    {
        return $this->where('skill_id', $skillId)
                    ->where('is_active', 1)
                    ->findAll();
    }
}
