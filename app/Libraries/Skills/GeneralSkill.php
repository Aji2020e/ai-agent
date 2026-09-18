<?php

namespace App\Libraries\Skills;

class GeneralSkill extends BaseSkill
{
    public function getName(): string
    {
        return 'general';
    }

    public function getDescription(): string
    {
        return 'Obrolan umum dan pertanyaan cepat.';
    }

    public function execute(array $params): SkillResult
    {
        return new SkillResult(
            true,
            'General chat mode.',
            ['guide' => $this->loadGuide('system_prompt')],
        );
    }
}
