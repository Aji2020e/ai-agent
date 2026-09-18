<?php

namespace App\Libraries\Skills;

class WritingSkill extends BaseSkill
{
    public function getName(): string
    {
        return 'writing';
    }

    public function getDescription(): string
    {
        return 'Membantu penulisan akademik, skripsi, jurnal, dan sitasi.';
    }

    public function execute(array $params): SkillResult
    {
        $guide = $this->loadGuide('system_prompt');
        $modules = $this->getModules();

        $context = [
            'guide'   => $guide,
            'modules' => $modules,
        ];

        // Contoh: jika user mention file, baca file
        if (! empty($params['file_path'])) {
            $context['file_content'] = $this->runTool('file_reader', ['file_path' => $params['file_path']]);
        }

        return new SkillResult(
            true,
            'Writing assistant siap membantu.',
            $context,
        );
    }
}
