<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Jejak audit pelanggaran otorisasi.
 *
 * Tabel ini bukan pelengkap — ia satu-satunya cara mendeteksi percobaan
 * enumerasi data (mis. portal mahasiswa yang mencoba NIM orang lain secara
 * berurutan). Setiap penolakan dicatat di sini.
 */
class CreateAuthViolationsTable extends Migration
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
            'client_id' => [
                'type'     => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null'     => true,
            ],
            'ip' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => true,
            ],

            // Jenis pelanggaran. Lihat PolicyGuard::violate() untuk daftarnya.
            'violation' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'module' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],

            // Nilai yang DICOBA (mis. NIM orang lain). Dipotong 100 char.
            'attempted' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            // Nilai yang SEHARUSNYA diizinkan (mis. NIM subject sendiri).
            'allowed' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],

            'endpoint' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'subject_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['client_id', 'created_at']);
        $this->forge->addKey('violation');
        $this->forge->addKey('created_at');
        $this->forge->createTable('auth_violations');
    }

    public function down()
    {
        $this->forge->dropTable('auth_violations');
    }
}
