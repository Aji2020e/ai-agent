<?php

namespace App\Libraries\Skills;

class CodingSkill extends BaseSkill
{
    public function getName(): string
    {
        return 'coding';
    }

    public function getDescription(): string
    {
        return 'Membantu programming, debugging, review kode, dan arsitektur software.';
    }

    public function execute(array $params): SkillResult
    {
        $guide = $this->loadGuide('system_prompt');
        $modules = $this->getModules();

        $context = [
            'guide'   => $guide,
            'modules' => $modules,
        ];

        // Contoh: jika perlu web search untuk docs/library terbaru
        if (! empty($params['search_query'])) {
            $context['web_results'] = $this->runTool('web_search', ['query' => $params['search_query']]);
        }

        return new SkillResult(
            true,
            'Coding assistant siap membantu.',
            $context,
        );
    }
}
