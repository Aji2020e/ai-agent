<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateApiTables extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'modules' => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => '*'], // '*' atau 'mahasiswa,dosen'
            'api_key_hash' => ['type' => 'VARCHAR', 'constraint' => 64],
            'key_prefix' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'last_used' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('api_key_hash');
        $this->forge->createTable('api_clients');

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'client_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'module' => ['type' => 'VARCHAR', 'constraint' => 50],
            'endpoint' => ['type' => 'VARCHAR', 'constraint' => 100],
            'tokens_used' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ok'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('client_id');
        $this->forge->addForeignKey('client_id', 'api_clients', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('api_logs');
    }

    public function down()
    {
        $this->forge->dropTable('api_logs');
        $this->forge->dropTable('api_clients');
    }
}
