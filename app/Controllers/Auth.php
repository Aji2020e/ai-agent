<?php

namespace App\Controllers;

use App\Models\UserModel;

class Auth extends BaseController
{
    public function login()
    {
        if (session()->get('logged_in')) {
            return redirect()->to(site_url('dashboard'));
        }

        // Auto-login via cookie "ingat saya" (token hash di DB)
        $cookie = $this->request->getCookie('remember_me');
        if ($cookie) {
            $userModel = new UserModel();

            if ($userModel->hasRememberTokenColumn()) {
                $user = $userModel->where('remember_token', hash('sha256', $cookie))
                                  ->where('is_active', 1)
                                  ->first();

                if ($user) {
                    $this->startSession($userModel, $user);

                    return redirect()->to(site_url('dashboard'));
                }

                $this->response->deleteCookie('remember_me');
            }
        }

        return view('auth/login');
    }

    public function attemptLogin()
    {
        // Anti brute-force: 10x/menit per IP
        $throttler = service('throttler');
        $bucket    = 'login_' . $this->request->getIPAddress();
        if (! $throttler->check($bucket, 10, 60)) {
            return redirect()->back()->withInput()
                ->with('error', 'Terlalu banyak percobaan login. Coba lagi dalam ' . (int) $throttler->getTokenTime() . ' detik.');
        }

        $rules = [
            'login'    => 'required',
            'password' => 'required',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $login    = $this->request->getPost('login');
        $password = $this->request->getPost('password');
        $remember = $this->request->getPost('remember');

        $userModel = new UserModel();
        $user = $userModel->findByUsernameOrEmail($login);

        if (! $user || ! password_verify($password, $user['password'])) {
            return redirect()->back()->withInput()->with('error', 'Username/Email atau password salah.');
        }

        if (! $user['is_active']) {
            return redirect()->back()->withInput()->with('error', 'Akun Anda telah dinonaktifkan.');
        }

        $redirect = redirect()->to(site_url('dashboard'))
            ->with('success', 'Selamat datang, ' . esc($user['full_name']) . '!');

        if ($userModel->hasRememberTokenColumn()) {
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $userModel->update($user['id'], ['remember_token' => hash('sha256', $token)]);
                $redirect->setCookie('remember_me', $token, 30 * 86400);
            } else {
                $userModel->update($user['id'], ['remember_token' => null]);
            }
        }

        $this->startSession($userModel, $user);

        return $redirect;
    }

    public function logout()
    {
        $userId = session()->get('user_id');

        if ($userId) {
            try {
                $userModel = new UserModel();
                if ($userModel->hasRememberTokenColumn()) {
                    $userModel->update($userId, ['remember_token' => null]);
                }
            } catch (\Throwable) {
            }
        }

        session()->destroy();

        return redirect()->to(site_url('login'))->deleteCookie('remember_me');
    }

    private function startSession(UserModel $userModel, array $user): void
    {
        $userModel->update($user['id'], ['last_login' => date('Y-m-d H:i:s')]);

        $isDosen    = ($user['user_type'] ?? '') === 'dosen';
        $identifier = $isDosen ? ($user['nik'] ?? $user['identifier'] ?? null) : ($user['identifier'] ?? null);

        session()->set([
            'user_id'      => $user['id'],
            'username'     => $user['username'],
            'email'        => $user['email'],
            'full_name'    => $user['full_name'],
            'user_role'    => $user['role'],
            'user_type'    => $user['user_type'] ?? 'guest',
            'identifier'   => $identifier,
            'profile_data' => json_decode($user['profile_data'] ?? '{}', true),
            'avatar'       => $user['avatar'],
            'logged_in'    => true,
        ]);
    }
}
