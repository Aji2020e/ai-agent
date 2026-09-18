<?php

namespace App\Controllers;

use App\Models\UserModel;

class Admin extends BaseController
{
    public function users()
    {
        $userModel = new UserModel();
        $users = $userModel->orderBy('created_at', 'DESC')->findAll();

        return view('admin/users', [
            'title' => 'Manajemen User',
            'users' => $users,
        ]);
    }

    public function toggleUserStatus(int $id)
    {
        $userModel = new UserModel();
        $user = $userModel->find($id);

        if (! $user) {
            return redirect()->back()->with('error', 'User tidak ditemukan.');
        }

        if ($user['id'] === session()->get('user_id')) {
            return redirect()->back()->with('error', 'Tidak dapat mengubah status akun sendiri.');
        }

        $userModel->update($id, [
            'is_active' => $user['is_active'] ? 0 : 1,
        ]);

        return redirect()->back()->with('success', 'Status user berhasil diubah.');
    }

    public function deleteUser(int $id)
    {
        if ($id === session()->get('user_id')) {
            return redirect()->back()->with('error', 'Tidak dapat menghapus akun sendiri.');
        }

        $userModel = new UserModel();

        if (! $userModel->find($id)) {
            return redirect()->back()->with('error', 'User tidak ditemukan.');
        }

        $userModel->delete($id);

        return redirect()->back()->with('success', 'User berhasil dihapus.');
    }

    public function createUser()
    {
        $rules = [
            'username'  => 'required|min_length[3]|max_length[50]|is_unique[users.username]',
            'email'     => 'required|valid_email|is_unique[users.email]',
            'full_name' => 'required|min_length[3]|max_length[100]',
            'password'  => 'required|min_length[6]',
            'role'      => 'required|in_list[admin,user]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $userType = $this->request->getPost('user_type') ?: 'guest';
        $nik      = $this->request->getPost('nik') ?: null;
        $identifier = $this->request->getPost('identifier') ?: null;

        // Untuk dosen, gunakan NIK sebagai identifier agar skill bisa mengenali pengguna
        if ($userType === 'dosen') {
            $identifier = $nik;
        }

        (new UserModel())->insert([
            'username'   => $this->request->getPost('username'),
            'email'      => $this->request->getPost('email'),
            'full_name'  => $this->request->getPost('full_name'),
            'password'   => password_hash($this->request->getPost('password'), PASSWORD_BCRYPT),
            'role'       => $this->request->getPost('role'),
            'user_type'  => $userType,
            'identifier' => $identifier,
            'nik'        => $nik,
        ]);

        return redirect()->back()->with('success', 'User baru berhasil dibuat.');
    }
}
