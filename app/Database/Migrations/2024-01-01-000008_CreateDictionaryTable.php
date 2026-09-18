<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDictionaryTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'module' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'umum'],
            'topik' => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => '*'],
            'tabel' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'kolom' => ['type' => 'TEXT', 'null' => true],
            'deskripsi' => ['type' => 'TEXT', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('module');
        $this->forge->createTable('api_dictionary');

        $this->seedDefaults();
    }

    public function down()
    {
        $this->forge->dropTable('api_dictionary');
    }

    private function seedDefaults(): void
    {
        $now = date('Y-m-d H:i:s');
        $rows = [
            // Aturan global semua modul akademik
            ['mahasiswa', '*', null, null, 'ATURAN WAJIB: gunakan HANYA tabel/kolom pada kamus ini. Database punya 800+ tabel mirip (KRS, KRS_sementara, Backup_NILAI, dsb) — JANGAN pakai selain yang terdaftar. NPM format bertitik, mis. 17.11.0212. Nilai huruf A-E; E = tidak lulus; kosong = nilai belum keluar. Bila data tidak ada, katakan terus terang, jangan mengarang.'],
            ['dosen', '*', null, null, 'ATURAN WAJIB: gunakan HANYA tabel/kolom pada kamus ini. NIK bisa berawalan spasi — abaikan spasi tepi. Bila data tidak ada, katakan terus terang, jangan mengarang.'],
            ['staff', '*', null, null, 'ATURAN WAJIB: gunakan HANYA tabel/kolom pada kamus ini. Kolom pass/foto TIDAK PERNAH diberikan kepadamu — jangan pernah memintanya. Bila data tidak ada, katakan terus terang.'],
            // Mahasiswa
            ['mahasiswa', 'profil, biodata, mahasiswa', 'mhs', 'NPM=nomor induk (bertitik), NAMA, KD_JUR=kode prodi, THA=tahun angkatan, STATUS_MHS, smstr=semester berjalan, EMAIL', 'Profil resmi mahasiswa aktif. Tabel MSMHS adalah arsip lama — JANGAN dipakai.'],
            ['mahasiswa', 'nilai, transkrip, KRS, lulus, mengulang', 'KRS', 'NPM, KODE=kode matkul, SEMESTER, THN_AJARAN, NILAI(huruf)', 'Riwayat pengambilan MK + nilai = transkrip. JANGAN pakai tabel NILAI / Backup_NILAI / KRS_sementara (format lama/arsip).'],
            ['mahasiswa', 'IPS, IPK, SKS, indeks prestasi', 'mhs_nilai_status', 'npm, ips, ipk, sks_sem, sks_kum', 'Ringkasan IPS/IPK per periode. Bisa kosong untuk mahasiswa tertentu — sebutkan bila kosong.'],
            ['mahasiswa', 'prasyarat, syarat, ambil, semester depan, anjuran', 'MK_PRASYARAT', 'KODE=mk yang dituju, PRASY=kode mk prasyarat', 'Rantai prasyarat untuk anjuran urutan ambil MK.'],
            // Dosen
            ['dosen', 'profil, biodata, dosen', 'DOSEN', 'KODE, NIK (bisa ber-spasi), NAMA', 'Profil resmi dosen. Bisa dicari by NIK atau KODE.'],
            // Staff
            ['staff', 'profil, biodata, karyawan, tendik', 'PSDM_KARYAWAN', 'nik, nama, jabatan, id_bagian, status, email, nohp, tgl_masuk, pendidikan_terakhir', 'Profil staff. Tabel ini punya kolom pass & foto — TIDAK tersedia untukmu, jangan sebut-sebut isinya.'],
        ];

        foreach ($rows as [$module, $topik, $tabel, $kolom, $deskripsi]) {
            $this->db->table('api_dictionary')->insert([
                'module' => $module, 'topik' => $topik, 'tabel' => $tabel,
                'kolom' => $kolom, 'deskripsi' => $deskripsi,
                'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }
}
