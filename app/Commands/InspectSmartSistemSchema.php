<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

class InspectSmartSistemSchema extends BaseCommand
{
    protected $group       = 'App';
    protected $name        = 'app:inspect-smart-schema';
    protected $description = 'Inspect mhs and department schema';

    public function run(array $params)
    {
        $db = Database::connect('smartSistemV2');

        try {
            CLI::write('--- mhs columns ---', 'green');
            $cols = $db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'mhs' ORDER BY ORDINAL_POSITION")->getResultArray();
            foreach ($cols as $col) {
                CLI::write($col['COLUMN_NAME']);
            }

            CLI::write('--- department columns ---', 'green');
            $cols = $db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'department' ORDER BY ORDINAL_POSITION")->getResultArray();
            foreach ($cols as $col) {
                CLI::write($col['COLUMN_NAME']);
            }
        } catch (\Throwable $e) {
            CLI::write('ERROR: ' . $e->getMessage(), 'red');
        }
    }
}
