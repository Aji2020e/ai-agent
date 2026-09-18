<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\Skills\SkillRouter;

class TestSkillRouter extends BaseCommand
{
    protected $group       = 'App';
    protected $name        = 'app:test-skill-router';
    protected $description = 'Test skill router with sample inputs';

    public function run(array $params)
    {
        $userType = ($params[0] ?? 'student');

        $inputs = match ($userType) {
            'student' => [
                'Sebutkan nama, jabatan dan email saya.',
                'ipk berapa',
                'berapa nilai saya semester lalu?',
                'buatkan fungsi sorting array di PHP',
            ],
            'dosen' => [
                'Sebutkan nama dan email saya.',
                'kelas yang saya ajar semester ini',
                'bantu buat RPS',
                'bantu tulis artikel ilmiah',
                'buatkan fungsi sorting array di PHP',
            ],
            'staff' => [
                'Sebutkan nama dan email saya.',
                'bantu tulis artikel ilmiah',
                'buatkan fungsi sorting array di PHP',
            ],
            default => [
                'Siapa saya?',
                'ipk berapa',
                'buatkan fungsi sorting',
            ],
        };

        $router = new SkillRouter();
        $context = [
            'user_type'  => $userType,
            'identifier' => $params[1] ?? 'S.F202100014',
            'role'       => $params[2] ?? 'user',
        ];

        CLI::write("Testing role: {$userType}");
        CLI::newLine();

        foreach ($inputs as $input) {
            $route = $router->route($input, $context);
            CLI::write("Input: {$input}");
            CLI::write("  → Skill: {$route['skill']}, Intent: {$route['intent']}");

            if (in_array($route['skill'], ['general', 'role_restricted', 'general_restricted'], true)) {
                // Tidak perlu execute fallback
            } else {
                $result = $router->execute($route['skill'], [
                    'intent'     => $route['intent'],
                    'identifier' => $context['identifier'],
                    'user_type'  => $context['user_type'],
                ]);
                CLI::write("  → Success: " . ($result->success ? 'yes' : 'no'));
                if (! $result->success && $result->error) {
                    CLI::write("  → Error: {$result->error}", 'red');
                }
            }
            CLI::newLine();
        }
    }
}
