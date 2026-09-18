<?php

namespace App\Libraries\Skills;

use App\Libraries\Tools\ToolRegistry;
use App\Models\SkillGuideModel;
use App\Models\SkillModuleModel;

abstract class BaseSkill implements SkillInterface
{
    protected ToolRegistry $tools;
    protected SkillGuideModel $guideModel;
    protected SkillModuleModel $moduleModel;
    protected ?int $skillId = null;

    public function __construct()
    {
        $this->tools = new ToolRegistry();
        $this->guideModel = new SkillGuideModel();
        $this->moduleModel = new SkillModuleModel();

        $skillModel = new \App\Models\SkillModel();
        $skill = $skillModel->findByName($this->getName());

        if ($skill) {
            $this->skillId = (int) $skill['id'];
        }
    }

    public function getDescription(): string
    {
        return 'Skill assistant.';
    }

    protected function loadGuide(string $section = 'system_prompt'): string
    {
        if ($this->skillId === null) {
            return '';
        }

        return $this->guideModel->getSystemPrompt($this->skillId) ?? '';
    }

    protected function getModules(): array
    {
        if ($this->skillId === null) {
            return [];
        }

        return $this->moduleModel->getModulesBySkill($this->skillId);
    }

    protected function runTool(string $name, array $params = []): mixed
    {
        $result = $this->tools->run($name, $params);
        return $result->toContextString();
    }

    protected function getUserIdentifier(array $context): ?string
    {
        return $context['identifier'] ?? $context['npm'] ?? null;
    }
}
