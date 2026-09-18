<?php

namespace App\Commands;

use App\Models\ApiClientModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

// SEMENTARA. Hapus setelah dipakai.
class TempPortal extends BaseCommand
{
    protected $group       = 'App';
    protected $name        = 'temp:portal';
    protected $description = 'Buat klien PORTAL-SMART (sementara).';

    public function run(array $params)
    {
        $m = new ApiClientModel();
        $m->where('name', 'PORTAL-SMART')->delete();
        $made = $m->createClient('PORTAL-SMART', '*');
        file_put_contents('C:\\Users\\MYBOOK~1\\AppData\\Local\\Temp\\opencode\\portalkey.txt', $made['key']);
        CLI::write('PORTAL-SMART dibuat');
    }
}
