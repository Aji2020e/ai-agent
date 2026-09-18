<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateApiDocsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'module' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'umum'],
            'title' => ['type' => 'VARCHAR', 'constraint' => 255],
            'content' => ['type' => 'LONGTEXT'],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('module');
        $this->forge->createTable('api_docs');
    }

    public function down()
    {
        $this->forge->dropTable('api_docs');
    }
}
