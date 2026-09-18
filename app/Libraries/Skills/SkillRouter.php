<?php

namespace App\Libraries\Skills;

use App\Models\RoleSkillModel;
use App\Libraries\ApiAuth;

class SkillRouter
{
    /** @var array<string, class-string<SkillInterface>> */
    protected array $skillMap = [
        'academic'        => AcademicSkill::class,
        'student_profile' => StudentProfileSkill::class,
        'staff_profile'   => StaffProfileSkill::class,
        'dosen_profile'   => DosenProfileSkill::class,
        'dosen_kelas'     => DosenKelasSkill::class,
        'dosen_rps'       => DosenRpsSkill::class,
        'writing'         => WritingSkill::class,
        'writing_artikel' => WritingArtikelSkill::class,
        'coding'          => CodingSkill::class,
        'general'         => GeneralSkill::class,
    ];

    protected RoleSkillModel $roleSkillModel;

    public function __construct()
    {
        $this->roleSkillModel = new RoleSkillModel();
    }

    public function route(string $userInput, array $context): array
    {
        $inputLower = mb_strtolower($userInput);
        $userType   = $context['user_type'] ?? 'guest';

        $route = $this->detectRoute($inputLower, $userType);

        // Batasi berdasarkan API client context (jika ada)
        $route = $this->applyClientSkillRestriction($route);

        // Batasi berdasarkan role user
        $route = $this->applyRoleSkillRestriction($route, $userType, $context['role'] ?? 'user');

        return $route;
    }

    protected function detectRoute(string $inputLower, string $userType): array
    {
        // Deteksi identitas dan pertanyaan profil
        if (
            str_contains($inputLower, 'nama saya') ||
            str_contains($inputLower, 'siapa saya') ||
            str_contains($inputLower, 'biodata saya') ||
            str_contains($inputLower, 'email saya') ||
            str_contains($inputLower, 'jabatan saya')
        ) {
            if ($userType === 'staff') {
                return ['skill' => 'staff_profile', 'intent' => 'biodata_pegawai'];
            }

            if ($userType === 'dosen') {
                return ['skill' => 'dosen_profile', 'intent' => 'biodata_dosen'];
            }

            return ['skill' => 'student_profile', 'intent' => 'biodata_mahasiswa'];
        }

        // Deteksi dosen: kelas, mahasiswa per kelas, RPS
        if ($userType === 'dosen') {
            if (
                str_contains($inputLower, 'kelas yang saya ajar') ||
                str_contains($inputLower, 'kelas saya') ||
                str_contains($inputLower, 'mata kuliah yang saya ajar')
            ) {
                return ['skill' => 'dosen_kelas', 'intent' => 'dosen_kelas'];
            }

            if (
                str_contains($inputLower, 'rps') ||
                str_contains($inputLower, 'rencana pembelajaran') ||
                str_contains($inputLower, 'silabus')
            ) {
                return ['skill' => 'dosen_rps', 'intent' => 'dosen_rps'];
            }
        }

        // Deteksi pertanyaan akademik (untuk mahasiswa)
        if (
            str_contains($inputLower, 'ipk') ||
            str_contains($inputLower, 'nilai') ||
            str_contains($inputLower, 'krs') ||
            str_contains($inputLower, 'transkrip') ||
            str_contains($inputLower, 'sks') ||
            str_contains($inputLower, 'semester') ||
            str_contains($inputLower, 'mata kuliah') ||
            str_contains($inputLower, 'masa studi') ||
            str_contains($inputLower, 'npm')
        ) {
            return ['skill' => 'academic', 'intent' => 'academic_lookup'];
        }

        // Deteksi penulisan akademik / skripsi
        if (
            str_contains($inputLower, 'skripsi') ||
            str_contains($inputLower, 'jurnal') ||
            str_contains($inputLower, 'sitasi') ||
            str_contains($inputLower, 'referensi') ||
            str_contains($inputLower, 'penulisan') ||
            str_contains($inputLower, 'format') ||
            str_contains($inputLower, 'bab') ||
            str_contains($inputLower, 'pendahuluan') ||
            str_contains($inputLower, 'kesimpulan')
        ) {
            return ['skill' => 'writing', 'intent' => 'writing_assistant'];
        }

        // Deteksi artikel ilmiah (dosen/staff)
        if (
            str_contains($inputLower, 'artikel ilmiah') ||
            str_contains($inputLower, 'penelitian') ||
            str_contains($inputLower, 'publikasi') ||
            str_contains($inputLower, 'jurnal ilmiah')
        ) {
            return ['skill' => 'writing_artikel', 'intent' => 'writing_artikel'];
        }

        // Deteksi coding
        if (
            str_contains($inputLower, 'kode') ||
            str_contains($inputLower, 'code') ||
            str_contains($inputLower, 'program') ||
            str_contains($inputLower, 'php') ||
            str_contains($inputLower, 'javascript') ||
            str_contains($inputLower, 'python') ||
            str_contains($inputLower, 'debugging') ||
            str_contains($inputLower, 'error') ||
            str_contains($inputLower, 'bug')
        ) {
            return ['skill' => 'coding', 'intent' => 'coding_assistant'];
        }

        // Fallback: general conversation
        return ['skill' => 'general', 'intent' => 'general'];
    }

    protected function applyClientSkillRestriction(array $route): array
    {
        $apiAuthClient = ApiAuth::client();

        if ($apiAuthClient === null) {
            return $route;
        }

        $skills = $apiAuthClient['skills'] ?? '*';

        if ($skills === '*') {
            return $route;
        }

        $allowed = array_map('trim', explode(',', (string) $skills));

        if (! in_array($route['skill'], $allowed, true)) {
            return ['skill' => 'general', 'intent' => 'general_restricted'];
        }

        return $route;
    }

    protected function applyRoleSkillRestriction(array $route, string $userType, string $userRole): array
    {
        if ($userRole === 'admin') {
            return $route;
        }

        if (! $this->roleSkillModel->isAllowed($userType, $route['skill'])) {
            return ['skill' => 'general', 'intent' => 'role_restricted'];
        }

        return $route;
    }

    public function execute(string $skillName, array $params): SkillResult
    {
        if (! isset($this->skillMap[$skillName])) {
            return new SkillResult(
                false,
                'Skill tidak ditemukan.',
                [],
                [],
                "Skill {$skillName} tidak tersedia.",
                'Coba tanya hal lain.',
            );
        }

        $class = $this->skillMap[$skillName];
        $skill = new $class();

        return $skill->execute($params);
    }
}
