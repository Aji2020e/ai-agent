<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Config;

class InspectDosenTables extends BaseCommand
{
    protected $group       = 'App';
    protected $name        = 'app:inspect-dosen-tables';
    protected $description = 'Inspect smart-sistem-v2 tables related to dosen';

    public function run(array $params)
    {
        $db = Config::connect('smartSistemV2');

        $queries = [
            'tahun_akademik top 3' => 'SELECT TOP 3 * FROM tahun_akademik',
            'tahun_aktif top 3' => 'SELECT TOP 3 * FROM tahun_aktif',
            'KULIAHTP by NIK sample' => "SELECT TOP 5 kt.KODE, ku.MKL, kt.KELAS, kt.TAHUN_AKTIF
                                          FROM KULIAHTP kt
                                          JOIN kuliah ku ON kt.KODE = ku.KODE
                                          WHERE kt.NIK = '0610087202'",
        ];

        foreach ($queries as $label => $sql) {
            CLI::write("=== {$label} ===", 'green');
            try {
                $rows = $db->query($sql)->getResultArray();
                foreach ($rows as $row) {
                    CLI::write(json_encode($row, JSON_UNESCAPED_UNICODE));
                }
            } catch (\Throwable $e) {
                CLI::write('ERROR: ' . $e->getMessage(), 'red');
            }
            CLI::newLine();
        }
    }
}
