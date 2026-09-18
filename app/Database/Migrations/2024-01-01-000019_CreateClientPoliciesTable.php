<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Kebijakan otorisasi per klien API.
 *
 * Satu baris per `api_clients`. Inilah "prosedur" yang didaftarkan admin untuk
 * tiap aplikasi pemanggil: seberapa jauh ia boleh menjangkau data, kolom apa
 * yang boleh keluar, dan tool AI mana yang boleh ia pakai.
 *
 * Prinsip desain: GAGAL-TERTUTUP. Klien tanpa baris di tabel ini tidak
 * mendapat akses data apa pun (lihat AccessPolicy::denyAll()).
 */
class CreateClientPoliciesTable extends Migration
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
            ],

            // ---- Cakupan data ----
            // self     : hanya data subject yang dideklarasikan (portal mahasiswa/dosen)
            // unit     : semua orang dalam unit/bagian/fakultas/prodi tertentu
            // role     : semua orang dengan peran tertentu
            // all      : tanpa batasan — HARUS eksplisit, tidak pernah jadi default
            'scope' => [
                'type'       => 'ENUM',
                'constraint' => ['self', 'unit', 'role', 'all'],
                'default'    => 'self',
            ],

            // ---- Subjek (orang yang datanya dibuka) ----
            'subject_required' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            // CSV tipe subjek yang diterima, mis. "mahasiswa" atau "dosen,staff"
            'subject_types' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'default'    => 'mahasiswa',
            ],

            // ---- Cakupan unit (dipakai saat scope = unit) ----
            // fakultas | prodi | bagian | jurusan
            'unit_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
            ],
            'unit_ids' => [
                'type' => 'TEXT',
                'null' => true,
            ],

            // ---- Cakupan peran (dipakai saat scope = role) ----
            'role_scope' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],

            // ---- Kebijakan kolom ----
            // JSON: {"mahasiswa":{"allow":["npm","nama"],"deny":["no_hp","alamat"]}}
            'field_policy' => [
                'type' => 'TEXT',
                'null' => true,
            ],

            // ---- Batas pemakaian ----
            'row_limit' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 50,
            ],
            'query_budget' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 8,
            ],

            // ---- Tool AI yang diizinkan ----
            // CSV nama tool, mis. "db_lookup". Kosong = tidak ada tool.
            'tools_allowed' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'default'    => '',
            ],

            // ---- Perilaku saat pelanggaran ----
            // deny_and_log : tolak + catat  (penegakan sesungguhnya)
            // log_only     : izinkan + catat (mode pemantauan saat rollout)
            'on_violation' => [
                'type'       => 'ENUM',
                'constraint' => ['deny_and_log', 'log_only'],
                'default'    => 'log_only',
            ],

            'notes' => [
                'type' => 'TEXT',
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
        $this->forge->addUniqueKey('client_id');
        $this->forge->addForeignKey('client_id', 'api_clients', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('client_policies');
    }

    public function down()
    {
        $this->forge->dropTable('client_policies');
    }
}
