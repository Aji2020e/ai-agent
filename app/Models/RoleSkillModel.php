<?php

namespace App\Models;

use CodeIgniter\Model;

class RoleSkillModel extends Model
{
    protected $table            = 'role_skills';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['role', 'skill_name'];
    protected $useTimestamps = false;

    public function getAllowedSkills(string $role): array
    {
        $rows = $this->where('role', $role)->findAll();

        return array_column($rows, 'skill_name');
    }

    public function isAllowed(string $role, string $skillName): bool
    {
        if ($role === 'admin') {
            return true;
        }

        return $this->where('role', $role)
                    ->where('skill_name', $skillName)
                    ->countAllResults() > 0;
    }

    public function syncSkills(string $role, array $skills): void
    {
        $this->where('role', $role)->delete();

        $data = [];
        foreach (array_unique($skills) as $skill) {
            $data[] = [
                'role'       => $role,
                'skill_name' => $skill,
            ];
        }

        if (! empty($data)) {
            $this->insertBatch($data);
        }
    }
}
