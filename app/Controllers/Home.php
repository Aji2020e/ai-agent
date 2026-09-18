<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): \CodeIgniter\HTTP\RedirectResponse
    {
        if (session()->get('logged_in')) {
            return redirect()->to(site_url('dashboard'));
        }

        return redirect()->to(site_url('login'));
    }
}
