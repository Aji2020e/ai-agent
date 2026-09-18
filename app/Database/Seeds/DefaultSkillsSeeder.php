<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DefaultSkillsSeeder extends Seeder
{
    public function run()
    {
        $skills = [
            [
                'name'         => 'general',
                'display_name' => 'Obrolan Umum',
                'description'  => 'Skill default untuk obrolan umum dan pertanyaan cepat.',
                'handler_class'=> 'App\\Libraries\\Skills\\GeneralSkill',
                'icon'         => 'bi-chat-dots',
                'is_active'    => 1,
                'order_index'  => 0,
            ],
            [
                'name'         => 'academic',
                'display_name' => 'Data Akademik',
                'description'  => 'Mengambil data akademik mahasiswa dari sistem smart-sistem-v2.',
                'handler_class'=> 'App\\Libraries\\Skills\\AcademicSkill',
                'icon'         => 'bi-mortarboard',
                'is_active'    => 1,
                'order_index'  => 1,
            ],
            [
                'name'         => 'student_profile',
                'display_name' => 'Profil Mahasiswa',
                'description'  => 'Mengambil biodata mahasiswa berdasarkan NPM.',
                'handler_class'=> 'App\\Libraries\\Skills\\StudentProfileSkill',
                'icon'         => 'bi-person-badge',
                'is_active'    => 1,
                'order_index'  => 2,
            ],
            [
                'name'         => 'writing',
                'display_name' => 'Asisten Skripsi',
                'description'  => 'Membantu penulisan akademik, skripsi, jurnal, dan sitasi.',
                'handler_class'=> 'App\\Libraries\\Skills\\WritingSkill',
                'icon'         => 'bi-journal-text',
                'is_active'    => 1,
                'order_index'  => 3,
            ],
            [
                'name'         => 'coding',
                'display_name' => 'Coding Assistant',
                'description'  => 'Membantu programming, debugging, dan arsitektur software.',
                'handler_class'=> 'App\\Libraries\\Skills\\CodingSkill',
                'icon'         => 'bi-code-slash',
                'is_active'    => 1,
                'order_index'  => 4,
            ],
        ];

        $this->db->table('skills')->insertBatch($skills);

        // Seed guides for academic skill
        $academicSkill = $this->db->table('skills')->where('name', 'academic')->get()->getRowArray();
        if ($academicSkill) {
            $this->db->table('skill_guides')->insert([
                'skill_id'   => $academicSkill['id'],
                'title'      => 'Panduan Data Akademik',
                'content'    => "Kamu adalah asisten akademik yang membantu mahasiswa mengakses data akademik. " .
                                "Gunakan data yang tersedia dari sistem akademik untuk menjawab pertanyaan. " .
                                "Jika data tidak ditemukan, jelaskan dengan sopan dan berikan saran. " .
                                "Selalu sertakan sumber data jika relevan.",
                'section'    => 'system_prompt',
                'order_index'=> 0,
                'is_active'  => 1,
            ]);
        }

        $writingSkill = $this->db->table('skills')->where('name', 'writing')->get()->getRowArray();
        if ($writingSkill) {
            $this->db->table('skill_guides')->insert([
                'skill_id'   => $writingSkill['id'],
                'title'      => 'Panduan Penulisan Akademik',
                'content'    => "Kamu adalah asisten penulisan akademik. " .
                                "Bantu mahasiswa menyusun skripsi, jurnal, referensi, dan sitasi. " .
                                "Berikan struktur yang jelas dan contoh yang relevan.",
                'section'    => 'system_prompt',
                'order_index'=> 0,
                'is_active'  => 1,
            ]);
        }

        $codingSkill = $this->db->table('skills')->where('name', 'coding')->get()->getRowArray();
        if ($codingSkill) {
            $this->db->table('skill_guides')->insert([
                'skill_id'   => $codingSkill['id'],
                'title'      => 'Panduan Coding Assistant',
                'content'    => "Kamu adalah programmer senior. " .
                                "Bantu user dengan kode yang bersih, terdokumentasi, dan mudah dipahami. " .
                                "Jelaskan langkah-langkah solusi dan berikan contoh kode yang relevan.",
                'section'    => 'system_prompt',
                'order_index'=> 0,
                'is_active'  => 1,
            ]);
        }
    }
}
