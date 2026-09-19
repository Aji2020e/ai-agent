<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateApiKeysTable extends Migration
{
    public function up()
    {
        // Tabel baru untuk menyimpan banyak API key per klien
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'client_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'api_key_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
            ],
            'key_prefix' => [
                'type'       => 'VARCHAR',
                'constraint' => 12,
                'null'       => true,
            ],
            'expires_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'ip_allowlist' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'last_used' => [
                'type' => 'DATETIME',
                'null' => true,
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
        $this->forge->addKey('client_id');
        $this->forge->addUniqueKey('api_key_hash');
        $this->forge->addForeignKey('client_id', 'api_clients', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('api_keys');

        // Migrasi key yang sudah ada di api_clients ke api_keys
        $clients = $this->db->table('api_clients')->get()->getResultArray();

        foreach ($clients as $client) {
            if (empty($client['api_key_hash'])) {
                continue;
            }

            $this->db->table('api_keys')->insert([
                'client_id'    => $client['id'],
                'api_key_hash' => $client['api_key_hash'],
                'key_prefix'   => $client['key_prefix'] ?? null,
                'expires_at'   => $client['expires_at'] ?? null,
                'ip_allowlist' => $client['ip_allowlist'] ?? null,
                'is_active'    => $client['is_active'] ?? 1,
                'last_used'    => $client['last_used'] ?? null,
                'created_at'   => $client['created_at'] ?? null,
                'updated_at'   => $client['updated_at'] ?? null,
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropTable('api_keys');
    }
}
