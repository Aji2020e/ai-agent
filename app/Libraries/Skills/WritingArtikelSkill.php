<?php

namespace App\Libraries\Skills;

class WritingArtikelSkill extends BaseSkill
{
    public function getName(): string
    {
        return 'writing_artikel';
    }

    public function getDescription(): string
    {
        return 'Asisten penulisan artikel ilmiah.';
    }

    public function execute(array $params): SkillResult
    {
        $guide = $this->loadGuide('system_prompt');

        return new SkillResult(
            true,
            'Asisten artikel ilmiah siap membantu.',
            ['guide' => $guide],
        );
    }
}
