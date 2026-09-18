<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DefaultUserSeeder extends Seeder
{
    public function run()
    {
        $this->db->table('users')->insert([
            'username'   => 'admin',
            'email'      => 'admin@aiassistant.local',
            'password'   => password_hash('admin123', PASSWORD_BCRYPT),
            'full_name'  => 'Administrator',
            'role'       => 'admin',
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
