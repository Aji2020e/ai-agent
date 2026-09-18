<?php

namespace App\Libraries\Skills;

use App\Models\DosenModel;

class DosenKelasSkill extends BaseSkill
{
    public function getName(): string
    {
        return 'dosen_kelas';
    }

    public function getDescription(): string
    {
        return 'Mengambil daftar kelas yang diampu dosen dan mahasiswa per kelas.';
    }

    public function execute(array $params): SkillResult
    {
        $nik = $this->getUserIdentifier($params);

        if (empty($nik)) {
            return new SkillResult(
                false,
                'Mohon maaf, saya memerlukan NIK untuk melihat kelas yang diampu.',
                [],
                [],
                'NIK tidak ditemukan.',
                'Silakan sebutkan NIK dosen Anda.',
            );
        }

        $thnAjaran = $params['thn_ajaran'] ?? null;
        $semester  = $params['semester'] ?? null;

        // Default ke tahun ajaran dan semester aktif saat ini
        if (! $thnAjaran || $semester === null) {
            $now       = new \DateTime();
            $year      = (int) $now->format('Y');
            $month     = (int) $now->format('n');

            if ($month >= 9) {
                $thnAjaran = "{$year}/" . ($year + 1);
                $semester  = 1;
            } elseif ($month <= 2) {
                $thnAjaran = ($year - 1) . "/{$year}";
                $semester  = 1;
            } else {
                $thnAjaran = ($year - 1) . "/{$year}";
                $semester  = 2;
            }
        } else {
            $semester = (int) $semester;
        }

        $model = new DosenModel();
        $data  = $model->getKelasYangDiampu($nik, $thnAjaran, $semester);

        return new SkillResult(
            true,
            'Berikut adalah daftar kelas yang diampu.',
            $data,
            ['sumber' => 'Sistem Akademik (smart-sistem-v2)'],
        );
    }
}
