<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateApiModulesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'slug' => ['type' => 'VARCHAR', 'constraint' => 50],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'description' => ['type' => 'TEXT', 'null' => true],
            'id_param' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'id'],
            'id_label' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'ID'],
            'query_config' => ['type' => 'LONGTEXT', 'null' => true],
            'examples' => ['type' => 'TEXT', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug');
        $this->forge->createTable('api_modules');
    }

    public function down()
    {
        $this->forge->dropTable('api_modules');
    }
}
