<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSkillsToApiClients extends Migration
{
    public function up()
    {
        $this->forge->addColumn('api_clients', [
            'skills' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
                'comment'    => 'Comma-separated allowed skills, or * for all',
                'after'      => 'modules',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('api_clients', ['skills']);
    }
}
