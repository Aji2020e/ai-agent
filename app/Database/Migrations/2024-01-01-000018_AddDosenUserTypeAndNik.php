<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDosenUserTypeAndNik extends Migration
{
    public function up()
    {
        // Tambahkan 'dosen' ke ENUM user_type dan kolom NIK
        $sql = "ALTER TABLE `users`
                MODIFY COLUMN `user_type` ENUM('staff','student','guest','dosen') DEFAULT 'guest',
                ADD COLUMN `nik` VARCHAR(50) NULL AFTER `identifier`";

        $this->db->query($sql);
    }

    public function down()
    {
        // Hapus kolom nik dan kembalikan ENUM ke nilai semula
        $sql = "ALTER TABLE `users`
                DROP COLUMN `nik`,
                MODIFY COLUMN `user_type` ENUM('staff','student','guest') DEFAULT 'guest'";

        $this->db->query($sql);
    }
}
