<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Model AI per klien API (null/kosong = ikut default global ai_provider).
 * Mis. klien smart-sistemv2 memakai model OpenRouter tertentu.
 */
class AddModelToApiClients extends Migration
{
    public function up()
    {
        $this->forge->addColumn('api_clients', [
            'model' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'modules'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('api_clients', ['model']);
    }
}
