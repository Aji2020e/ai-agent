<?php

namespace App\Libraries\Skills;

class StudentProfileSkill extends BaseSkill
{
    public function getName(): string
    {
        return 'student_profile';
    }

    public function getDescription(): string
    {
        return 'Mengambil biodata dan profil mahasiswa dari sistem akademik.';
    }

    public function execute(array $params): SkillResult
    {
        $npm = $this->getUserIdentifier($params);

        if (empty($npm)) {
            return new SkillResult(
                false,
                'Mohon maaf, saya memerlukan NPM untuk mencari biodata Anda.',
                [],
                [],
                'NPM tidak ditemukan dalam konteks percakapan.',
                'Silakan sebutkan NPM Anda terlebih dahulu.',
            );
        }

        $guide    = $this->loadGuide('system_prompt');
        $dbResult = $this->runTool('db_lookup', ['intent' => 'biodata', 'identifier' => $npm]);

        return new SkillResult(
            true,
            'Biodata mahasiswa berhasil diambil.',
            ['guide' => $guide, 'db_data' => $dbResult],
            ['sumber' => 'Sistem Akademik (smart-sistem-v2)'],
        );
    }
}
