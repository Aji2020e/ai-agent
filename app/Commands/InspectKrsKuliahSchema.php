<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

class InspectKrsKuliahSchema extends BaseCommand
{
    protected $group       = 'App';
    protected $name        = 'app:inspect-krs-schema';
    protected $description = 'Inspect krs and kuliah schema';

    public function run(array $params)
    {
        $db = Database::connect('smartSistemV2');

        try {
            CLI::write('--- krs columns ---', 'green');
            $cols = $db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'krs' ORDER BY ORDINAL_POSITION")->getResultArray();
            foreach ($cols as $col) {
                CLI::write($col['COLUMN_NAME']);
            }

            CLI::write('--- kuliah columns ---', 'green');
            $cols = $db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'kuliah' ORDER BY ORDINAL_POSITION")->getResultArray();
            foreach ($cols as $col) {
                CLI::write($col['COLUMN_NAME']);
            }
        } catch (\Throwable $e) {
            CLI::write('ERROR: ' . $e->getMessage(), 'red');
        }
    }
}
