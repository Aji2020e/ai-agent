<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRoleSkillsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'role' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'skill_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('role');
        $this->forge->addKey('skill_name');
        $this->forge->addUniqueKey(['role', 'skill_name']);
        $this->forge->createTable('role_skills');
    }

    public function down()
    {
        $this->forge->dropTable('role_skills');
    }
}
