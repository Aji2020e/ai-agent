<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateChatHistoryTable extends Migration
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
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'session_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'role' => [
                'type'       => 'ENUM',
                'constraint' => ['user', 'assistant', 'system'],
            ],
            'content' => [
                'type' => 'LONGTEXT',
            ],
            'tokens_used' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        $this->forge->addKey('session_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('chat_history');
    }

    public function down()
    {
        $this->forge->dropTable('chat_history');
    }
}
