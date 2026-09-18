<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\Skills\SkillRouter;

class TestModularSkills extends BaseCommand
{
    protected $group       = 'App';
    protected $name        = 'app:test-modular-skills';
    protected $description = 'Test modular skill system';

    public function run(array $params)
    {
        $inputs = [
            'Sebutkan nama dan email saya.',
            'ipk berapa',
            'berapa nilai saya semester lalu?',
            'buatkan outline skripsi tentang AI',
            'buatkan fungsi sorting array di PHP',
            'halo, apa kabar?',
        ];

        $router = new SkillRouter();
        $context = [
            'user_type'  => 'student',
            'identifier' => 'S.F202100014',
        ];

        foreach ($inputs as $input) {
            $route = $router->route($input, $context);
            CLI::write("Input: {$input}");
            CLI::write("  → Skill: {$route['skill']}, Intent: {$route['intent']}");

            $result = $router->execute($route['skill'], [
                'intent'     => $route['intent'],
                'identifier' => $context['identifier'],
                'user_type'  => $context['user_type'],
            ]);

            CLI::write("  → Success: " . ($result->success ? 'yes' : 'no'));
            if (! $result->success && $result->error) {
                CLI::write("  → Error: {$result->error}", 'red');
            } else {
                CLI::write("  → Message: {$result->message}");
            }
            CLI::newLine();
        }
    }
}
