<?php

namespace App\Libraries\Skills;

class DosenRpsSkill extends BaseSkill
{
    public function getName(): string
    {
        return 'dosen_rps';
    }

    public function getDescription(): string
    {
        return 'Asisten pembuatan Rencana Pembelajaran Semester (RPS).';
    }

    public function execute(array $params): SkillResult
    {
        $guide = $this->loadGuide('system_prompt');

        return new SkillResult(
            true,
            'Asisten RPS siap membantu.',
            ['guide' => $guide],
        );
    }
}
