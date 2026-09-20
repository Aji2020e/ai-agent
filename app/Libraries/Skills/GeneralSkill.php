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
            // Jangan tarik guide template statis untuk mode general.
            // Tujuan mode ini: respons fleksibel mengikuti input user.
            [],
        );
    }
}
