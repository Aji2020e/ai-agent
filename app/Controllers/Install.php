<?php

namespace App\Controllers;

use App\Libraries\Installer;

/**
 * Web installer: tampil otomatis saat DB belum siap (via InstallCheckFilter).
 */
class Install extends BaseController
{
    public function index()
    {
        if (Installer::isInstalled()) {
            return redirect()->to(site_url('login'));
        }

        return view('install/index', ['title' => 'Instalasi']);
    }

    public function run()
    {
        if (Installer::isInstalled()) {
            return $this->response->setJSON([
                'success' => true,
                'already' => true,
                'steps'   => [],
                'csrf'    => csrf_hash(),
            ]);
        }

        $result = Installer::run();

        return $this->response->setJSON($result + ['csrf' => csrf_hash()]);
    }
}
