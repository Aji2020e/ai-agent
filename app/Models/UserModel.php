<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'username', 'email', 'password', 'full_name', 'role',
        'user_type', 'identifier', 'nik', 'profile_data',
        'avatar', 'remember_token', 'is_active', 'last_login',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'username'  => 'required|min_length[3]|max_length[50]|is_unique[users.username,id,{id}]',
        'email'     => 'required|valid_email|is_unique[users.email,id,{id}]',
        'password'  => 'required|min_length[6]',
        'full_name' => 'required|min_length[3]|max_length[100]',
    ];

    protected $validationMessages = [
        'username' => [
            'is_unique' => 'Username sudah digunakan.',
        ],
        'email' => [
            'is_unique' => 'Email sudah terdaftar.',
        ],
    ];

    public function findByUsernameOrEmail(string $login): ?array
    {
        return $this->where('username', $login)
                    ->orWhere('email', $login)
                    ->first();
    }

    public function hasRememberTokenColumn(): bool
    {
        try {
            $columns = $this->db->getFieldNames($this->table);

            return is_array($columns) && in_array('remember_token', $columns, true);
        } catch (\Throwable) {
            return false;
        }
    }

    public function countActiveUsers(): int
    {
        return $this->where('is_active', 1)->countAllResults();
    }
}
