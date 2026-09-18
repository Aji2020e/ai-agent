<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

class TestSmartSistemConnection extends BaseCommand
{
    protected $group       = 'App';
    protected $name        = 'app:test-smart-sistem';
    protected $description = 'Test connection to smart-sistem-v2 SQL Server database';

    public function run(array $params)
    {
        try {
            $db = Database::connect('smartSistemV2');

            CLI::write('Connected to smart-sistem-v2 database.', 'green');

            $result = $db->query("SELECT TOP 5 npm, NAMA FROM mhs ORDER BY npm DESC")->getResultArray();

            if (empty($result)) {
                CLI::write('No students found.', 'yellow');
                return;
            }

            foreach ($result as $row) {
                CLI::write("- {$row['npm']}: {$row['NAMA']}");
            }
        } catch (\Throwable $e) {
            CLI::write('Connection failed: ' . $e->getMessage(), 'red');
        }
    }
}
