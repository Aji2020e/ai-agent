<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tambahkan 'dosen' ke ENUM user_type dan kolom `nik`.
 *
 * PERBAIKAN 1: sebelumnya migrasi ini menulis SQL mentah dengan nama tabel
 * hardcoded (`users`), sehingga GAGAL bila `database.default.DBPrefix` diisi —
 * tabel sebenarnya bernama `<prefix>users`. Sekarang nama tabel diambil dari
 * prefix aktif.
 *
 * PERBAIKAN 2: up()/down() kini idempoten. CI4 meng-cache daftar nama kolom
 * per koneksi, sehingga pemeriksaan field bisa memakai data basi saat
 * migrasi dijalankan bolak-balik (mis. `migrate:refresh` atau saat test).
 * Karena itu cache direset sebelum membaca skema, dan setiap ALTER dibungkus
 * try/catch agar keadaan yang sudah benar tidak membuat migrasi gagal.
 */
class AddDosenUserTypeAndNik extends Migration
{
    public function up()
    {
        if (! $this->usersTableExists()) {
            return;
        }

        $table  = $this->tableName();
        $fields = $this->currentFields();

        // 1. Perluas ENUM user_type agar mencakup 'dosen'
        if (in_array('user_type', $fields, true)) {
            $this->safeQuery(
                "ALTER TABLE {$table} MODIFY COLUMN `user_type` "
                . "ENUM('staff','student','guest','dosen') DEFAULT 'guest'"
            );
        }

        // 2. Tambahkan kolom nik hanya bila belum ada
        if (! in_array('nik', $fields, true)) {
            $after = in_array('identifier', $fields, true) ? ' AFTER `identifier`' : '';
            $this->safeQuery("ALTER TABLE {$table} ADD COLUMN `nik` VARCHAR(50) NULL{$after}");
        }
    }

    public function down()
    {
        if (! $this->usersTableExists()) {
            return;
        }

        $table  = $this->tableName();
        $fields = $this->currentFields();

        if (in_array('nik', $fields, true)) {
            $this->safeQuery("ALTER TABLE {$table} DROP COLUMN `nik`");
        }

        if (in_array('user_type', $fields, true)) {
            $this->safeQuery(
                "ALTER TABLE {$table} MODIFY COLUMN `user_type` "
                . "ENUM('staff','student','guest') DEFAULT 'guest'"
            );
        }
    }

    /** Nama tabel users lengkap dengan prefix, ter-escape. */
    private function tableName(): string
    {
        return $this->db->protectIdentifiers($this->db->DBPrefix . 'users');
    }

    private function usersTableExists(): bool
    {
        try {
            $this->db->resetDataCache();

            return $this->db->tableExists($this->db->DBPrefix . 'users');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Daftar kolom terkini. Cache WAJIB direset: tanpa ini, migrasi yang
     * dijalankan bolak-balik dalam satu koneksi membaca daftar kolom basi.
     *
     * @return string[]
     */
    private function currentFields(): array
    {
        try {
            $this->db->resetDataCache();
            $fields = $this->db->getFieldNames($this->db->DBPrefix . 'users');

            return is_array($fields) ? $fields : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Jalankan ALTER tanpa menggagalkan migrasi bila keadaannya sudah sesuai.
     * Misalnya "Can't DROP COLUMN nik; check that exists" saat down() dipanggil
     * dua kali, atau "Duplicate column name" saat up() diulang.
     */
    private function safeQuery(string $sql): void
    {
        try {
            $this->db->query($sql);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();

            $benign = [
                "check that it exists",     // DROP kolom/tabel yang sudah tidak ada
                'Duplicate column name',    // ADD kolom yang sudah ada
                "Can't DROP",
                'Unknown column',
            ];

            foreach ($benign as $b) {
                if (stripos($msg, $b) !== false) {
                    $this->db->resetDataCache();

                    return;
                }
            }

            throw $e;   // kesalahan lain harus tetap terlihat
        }
    }
}
