<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AdminFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        if (! $session->get('logged_in')) {
            return redirect()->to(site_url('login'))->with('error', 'Silakan login terlebih dahulu.');
        }

        if ($session->get('user_role') !== 'admin') {
            return redirect()->to(site_url('dashboard'))->with('error', 'Akses ditolak. Hanya admin yang dapat mengakses halaman ini.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
