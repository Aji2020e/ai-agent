<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class RoleSkillsSeeder extends Seeder
{
    public function run()
    {
        // Pastikan skill dosen/staff ada di tabel skills
        $existing = $this->db->table('skills')->select('name')->get()->getResultArray();
        $existing = array_column($existing, 'name');

        $skills = [
            [
                'name'         => 'staff_profile',
                'display_name' => 'Profil Staff',
                'description'  => 'Mengambil biodata pegawai/staff.',
                'handler_class'=> 'App\\Libraries\\Skills\\StaffProfileSkill',
                'icon'         => 'bi-person-workspace',
                'is_active'    => 1,
                'order_index'  => 10,
            ],
            [
                'name'         => 'dosen_profile',
                'display_name' => 'Profil Dosen',
                'description'  => 'Mengambil biodata dosen berdasarkan NIK.',
                'handler_class'=> 'App\\Libraries\\Skills\\DosenProfileSkill',
                'icon'         => 'bi-person-video2',
                'is_active'    => 1,
                'order_index'  => 11,
            ],
            [
                'name'         => 'dosen_kelas',
                'display_name' => 'Kelas Dosen',
                'description'  => 'Melihat kelas dan mata kuliah yang diampu dosen.',
                'handler_class'=> 'App\\Libraries\\Skills\\DosenKelasSkill',
                'icon'         => 'bi-easel',
                'is_active'    => 1,
                'order_index'  => 12,
            ],
            [
                'name'         => 'dosen_rps',
                'display_name' => 'Asisten RPS',
                'description'  => 'Membantu dosen menyusun Rencana Pembelajaran Semester.',
                'handler_class'=> 'App\\Libraries\\Skills\\DosenRpsSkill',
                'icon'         => 'bi-journal-bookmark',
                'is_active'    => 1,
                'order_index'  => 13,
            ],
            [
                'name'         => 'writing_artikel',
                'display_name' => 'Asisten Artikel Ilmiah',
                'description'  => 'Membantu penulisan artikel ilmiah dan publikasi.',
                'handler_class'=> 'App\\Libraries\\Skills\\WritingArtikelSkill',
                'icon'         => 'bi-journal-richtext',
                'is_active'    => 1,
                'order_index'  => 14,
            ],
        ];

        foreach ($skills as $skill) {
            if (! in_array($skill['name'], $existing, true)) {
                $this->db->table('skills')->insert($skill);
            }
        }

        // Seed role_skills
        $this->db->table('role_skills')->truncate();

        $mappings = [
            'student' => ['student_profile', 'academic', 'writing'],
            'dosen'   => ['dosen_profile', 'dosen_kelas', 'dosen_rps', 'writing_artikel'],
            'staff'   => ['staff_profile', 'general'],
        ];

        foreach ($mappings as $role => $skills) {
            foreach ($skills as $skill) {
                $this->db->table('role_skills')->insert([
                    'role'       => $role,
                    'skill_name' => $skill,
                ]);
            }
        }

        // Seed panduan default untuk skill dosen
        $this->seedGuide('dosen_rps', 'Panduan Asisten RPS', "Kamu adalah asisten akademik yang membantu dosen menyusun Rencana Pembelajaran Semester (RPS). " .
            "Tanyakan informasi yang diperlukan secara bertahap: nama mata kuliah, kode, SKS, capaian pembelajaran, topik per pertemuan, metode pembelajaran, dan assessment. " .
            "Berikan struktur RPS yang rapi dan dapat langsung digunakan.");

        $this->seedGuide('writing_artikel', 'Panduan Artikel Ilmiah', "Kamu adalah asisten penulisan artikel ilmiah. " .
            "Bantu dosen/staf menyusun artikel dengan struktur yang baik: abstrak, pendahuluan, metode, hasil, diskusi, kesimpulan, dan referensi. " .
            "Berikan situsasi yang tepat dan jelaskan dengan bahasa akademik yang jelas.");
    }

    private function seedGuide(string $skillName, string $title, string $content): void
    {
        $skill = $this->db->table('skills')->where('name', $skillName)->get()->getRowArray();

        if (! $skill) {
            return;
        }

        $exists = $this->db->table('skill_guides')
                           ->where('skill_id', $skill['id'])
                           ->where('section', 'system_prompt')
                           ->countAllResults() > 0;

        if ($exists) {
            return;
        }

        $this->db->table('skill_guides')->insert([
            'skill_id'    => $skill['id'],
            'title'       => $title,
            'content'     => $content,
            'section'     => 'system_prompt',
            'order_index' => 0,
            'is_active'   => 1,
        ]);
    }
}
