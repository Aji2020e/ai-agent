<?php

namespace App\Libraries\Skills;

class StaffProfileSkill extends BaseSkill
{
    public function getName(): string
    {
        return 'staff_profile';
    }

    public function getDescription(): string
    {
        return 'Mengambil biodata dan profil pegawai/staff.';
    }

    public function execute(array $params): SkillResult
    {
        $identifier = $this->getUserIdentifier($params);

        if (empty($identifier)) {
            return new SkillResult(
                false,
                'Mohon maaf, saya memerlukan NIP untuk mencari biodata Anda.',
                [],
                [],
                'NIP tidak ditemukan dalam konteks percakapan.',
                'Silakan sebutkan NIP Anda terlebih dahulu.',
            );
        }

        return new SkillResult(
            false,
            "Mohon maaf, data pegawai dengan NIP {$identifier} belum tersedia di sistem.",
            [],
            [],
            'Data kepegawaian belum diintegrasikan.',
            'Silakan hubungi bagian SDM untuk informasi lebih lanjut.',
        );
    }
}
