<?php

namespace App\Models;

use CodeIgniter\Model;

class SkillGuideModel extends Model
{
    protected $table            = 'skill_guides';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'skill_id', 'title', 'content', 'section', 'order_index', 'is_active',
    ];
    protected $useTimestamps = true;

    public function getGuidesBySkill(int $skillId, ?string $section = null): array
    {
        $builder = $this->where('skill_id', $skillId)
                        ->where('is_active', 1)
                        ->orderBy('order_index', 'ASC');

        if ($section !== null) {
            $builder->where('section', $section);
        }

        return $builder->findAll();
    }

    public function getSystemPrompt(int $skillId): string
    {
        $guides = $this->getGuidesBySkill($skillId, 'system_prompt');

        return implode("\n\n", array_column($guides, 'content'));
    }
}
