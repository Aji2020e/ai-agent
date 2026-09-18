<?php

namespace App\Filters;

use App\Libraries\Installer;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Alihkan SEMUA request web ke /install bila database belum siap.
 * Dikecualikan untuk route install* (lihat Config\Filters).
 */
class InstallCheckFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (is_cli() || ENVIRONMENT === 'testing') {
            return;
        }

        if (! Installer::isInstalled()) {
            return redirect()->to(site_url('install'));
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
