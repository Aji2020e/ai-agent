<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\Skills\SkillRouter;

class TestAcademicSkill extends BaseCommand
{
    protected $group       = 'App';
    protected $name        = 'app:test-academic-skill';
    protected $description = 'Test academic skill with a sample NPM';

    public function run(array $params)
    {
        $npm = $params[0] ?? 'S.F202100014';

        $router = new SkillRouter();
        $result = $router->execute('AcademicSkill', [
            'intent'     => 'biodata_mahasiswa',
            'identifier' => $npm,
            'user_type'  => 'student',
        ]);

        if ($result->success) {
            CLI::write('Skill result: SUCCESS', 'green');
            CLI::write(json_encode($result->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            CLI::write('Skill result: FAILED', 'red');
            CLI::write($result->error);
        }
    }
}
