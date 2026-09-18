<?php

namespace App\Commands;

use App\Libraries\Installer;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * One-shot installer untuk deploy baru (bungkus CLI dari Installer library).
 *
 *   php spark app:install
 *   php spark app:install --auto   (tanpa konfirmasi, untuk Docker/CI)
 */
class Install extends BaseCommand
{
    protected $group       = 'App';
    protected $name        = 'app:install';
    protected $description = 'Siapkan database: buat DB, migrate, seed admin bila kosong.';
    protected $usage       = 'app:install [options]';
    protected $options     = [
        '--auto' => 'Jalankan tanpa konfirmasi interaktif.',
    ];

    public function run(array $params)
    {
        $auto   = array_key_exists('auto', $params);
        $dbName = Installer::dbName();

        if ($dbName === '') {
            CLI::error('Nama database kosong. Isi database.default.database di .env / environment.');

            return EXIT_ERROR;
        }

        CLI::write('== AI Assistant installer ==', 'yellow');
        CLI::write('Database: ' . $dbName);

        if (! $auto && CLI::prompt('Lanjutkan instalasi?', ['y', 'n'], 'required') === 'n') {
            CLI::write('Dibatalkan.');

            return EXIT_USER_INPUT;
        }

        $result = Installer::run();

        foreach ($result['steps'] as $step) {
            $icon = $step['ok'] ? '✓' : '✗';
            $line = "{$icon} {$step['label']}";
            if ($step['detail'] !== '') {
                $line .= ' — ' . $step['detail'];
            }
            $step['ok'] ? CLI::write($line, 'green') : CLI::error($line);
        }

        if (! $result['success']) {
            return EXIT_DATABASE;
        }

        CLI::write('Instalasi selesai.', 'green');

        return EXIT_SUCCESS;
    }
}
