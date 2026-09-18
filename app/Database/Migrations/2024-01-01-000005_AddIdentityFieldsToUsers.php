<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIdentityFieldsToUsers extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'user_type' => [
                'type'       => 'ENUM',
                'constraint' => ['staff', 'student', 'guest'],
                'default'    => 'guest',
                'after'      => 'role',
            ],
            'identifier' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'after'      => 'user_type',
            ],
            'profile_data' => [
                'type'       => 'JSON',
                'null'       => true,
                'after'      => 'identifier',
            ],
        ]);

        $this->forge->addKey('identifier');
    }

    public function down()
    {
        $this->forge->dropColumn('users', ['user_type', 'identifier', 'profile_data']);
    }
}
