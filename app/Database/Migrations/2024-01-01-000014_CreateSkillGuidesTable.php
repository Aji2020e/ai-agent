<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSkillGuidesTable extends Migration
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
            'skill_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'content' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'section' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'default'    => 'system_prompt',
            ],
            'order_index' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('skill_id');
        $this->forge->addForeignKey('skill_id', 'skills', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('skill_guides');
    }

    public function down()
    {
        $this->forge->dropTable('skill_guides');
    }
}
