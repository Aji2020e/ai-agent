<?php

namespace App\Libraries\Skills;

use App\Models\DosenModel;

class DosenProfileSkill extends BaseSkill
{
    public function getName(): string
    {
        return 'dosen_profile';
    }

    public function getDescription(): string
    {
        return 'Mengambil profil dosen.';
    }

    public function execute(array $params): SkillResult
    {
        $nik = $this->getUserIdentifier($params);

        if (empty($nik)) {
            return new SkillResult(
                false,
                'Mohon maaf, saya memerlukan NIK untuk mencari profil dosen.',
                [],
                [],
                'NIK tidak ditemukan.',
                'Silakan sebutkan NIK dosen Anda.',
            );
        }

        $model = new DosenModel();
        $data  = $model->getProfilDosen($nik);

        if (! $data) {
            return new SkillResult(
                false,
                "Data dosen dengan NIK {$nik} tidak ditemukan.",
                [],
                [],
                'Profil dosen tidak ditemukan.',
                'Pastikan NIK sudah benar.',
            );
        }

        return new SkillResult(
            true,
            "Berikut adalah profil dosen {$data['NAMA']}.",
            $data,
            ['sumber' => 'Sistem Akademik (smart-sistem-v2)'],
        );
    }
}
