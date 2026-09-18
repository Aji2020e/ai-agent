<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ApiClientSecurity extends Migration
{
    public function up()
    {
        $this->forge->addColumn('api_clients', [
            'ip_allowlist' => ['type' => 'TEXT', 'null' => true, 'after' => 'key_prefix'],
            'expires_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'ip_allowlist'],
            'hmac_secret' => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true, 'after' => 'expires_at'],
            'require_hmac' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'hmac_secret'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('api_clients', ['require_hmac', 'hmac_secret', 'expires_at', 'ip_allowlist']);
    }
}
