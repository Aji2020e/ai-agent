<?php

namespace App\Controllers;

use App\Models\UserModel;

class Profile extends BaseController
{
    public function index()
    {
        $userModel = new UserModel();
        $user = $userModel->find(session()->get('user_id'));

        return view('profile/index', [
            'title' => 'Profil Saya',
            'user'  => $user,
        ]);
    }

    public function update()
    {
        $userId = session()->get('user_id');
        $rules = [
            'full_name' => 'required|min_length[3]|max_length[100]',
            'email'     => "required|valid_email|is_unique[users.email,id,{$userId}]",
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $userModel = new UserModel();
        $userModel->update($userId, [
            'full_name' => $this->request->getPost('full_name'),
            'email'     => $this->request->getPost('email'),
        ]);

        session()->set([
            'full_name' => $this->request->getPost('full_name'),
            'email'     => $this->request->getPost('email'),
        ]);

        return redirect()->back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function changePassword()
    {
        $rules = [
            'current_password' => 'required',
            'new_password'     => 'required|min_length[6]',
            'confirm_password' => 'required|matches[new_password]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $userId    = session()->get('user_id');
        $userModel = new UserModel();
        $user      = $userModel->find($userId);

        if (! password_verify($this->request->getPost('current_password'), $user['password'])) {
            return redirect()->back()->with('error', 'Password saat ini salah.');
        }

        $userModel->update($userId, [
            'password' => password_hash($this->request->getPost('new_password'), PASSWORD_BCRYPT),
        ]);

        return redirect()->back()->with('success', 'Password berhasil diubah.');
    }
}
