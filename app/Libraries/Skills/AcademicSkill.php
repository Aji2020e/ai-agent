<?php

namespace App\Libraries\Skills;

use App\Models\AcademicModel;

class AcademicSkill extends BaseSkill
{
    public function getName(): string
    {
        return 'academic';
    }

    public function getDescription(): string
    {
        return 'Mengambil data akademik mahasiswa seperti KRS, nilai, SKS, dan transkrip.';
    }

    public function execute(array $params): SkillResult
    {
        $npm    = $this->getUserIdentifier($params);
        $intent = $params['intent'] ?? 'general';

        if (empty($npm)) {
            return new SkillResult(
                false,
                'Mohon maaf, saya memerlukan NPM untuk mencari data akademik Anda.',
                [],
                [],
                'NPM tidak ditemukan.',
                'Silakan sebutkan NPM Anda terlebih dahulu.',
            );
        }

        // Build context with guide + tools
        $guide    = $this->loadGuide('system_prompt');
        $dbResult = $this->runTool('db_lookup', [
            'intent'     => match($intent) {
                'krs'     => 'krs',
                'nilai'   => 'nilai',
                'sks'     => 'sks',
                default   => 'biodata',
            },
            'identifier' => $npm,
        ]);

        $contextData = [
            'guide'    => $guide,
            'db_data'  => $dbResult,
            'intent'   => $intent,
        ];

        return new SkillResult(
            true,
            'Data akademik berhasil diambil.',
            $contextData,
            ['sumber' => 'Sistem Akademik (smart-sistem-v2)'],
        );
    }
}
