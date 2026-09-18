<?php

namespace App\Models;

use CodeIgniter\Model;
use CodeIgniter\Database\Config;

class DosenModel extends Model
{
    protected $DBGroup = 'smartSistemV2';
    protected $returnType = 'array';

    public function getProfilDosen(string $nik): ?array
    {
        $db = Config::connect('smartSistemV2');

        $sql = "SELECT KODE, NIK, NAMA, TPTLAHIR, TglLahir, JENKEL, alamat, AlmKantor,
                       telepon, email, NIDN, KODE_JUR, NAMA_SINGKAT,
                       gelar_depan, gelar_belakang, pendidikan_terakhir, NPWP
                FROM DOSEN
                WHERE NIK = ?";

        return $db->query($sql, [$nik])->getRowArray() ?: null;
    }

    public function getKelasYangDiampu(string $nik, string $thnAjaran, int $semester): array
    {
        $db = Config::connect('smartSistemV2');

        $sql = "SELECT DISTINCT kt.KODE, ku.MKL as mata_kuliah, kt.KELAS as kelas,
                        ta.THN_AKADEMIK as thn_ajaran, ta.SEMESTER as semester, ku.SKS as sks
                 FROM KULIAHTP kt
                 JOIN kuliah ku ON kt.KODE = ku.KODE
                 JOIN tahun_akademik ta ON kt.TAHUN_AKTIF = ta.ID_TAHUN
                 WHERE kt.NIK = ? AND ta.THN_AKADEMIK = ? AND ta.SEMESTER = ?";

        return $db->query($sql, [$nik, $thnAjaran, $semester])->getResultArray();
    }

    public function getMahasiswaPerKelas(string $kode, string $kelas, string $thnAjaran, int $semester): array
    {
        $db = Config::connect('smartSistemV2');

        $sql = "SELECT m.NPM, m.NAMA, m.email, k.KELAS, k.nilai
                FROM krs k
                JOIN mhs m ON k.npm = m.npm
                WHERE k.kode = ? AND k.KELAS = ? AND k.thn_ajaran = ? AND k.semester = ?
                ORDER BY m.NAMA ASC";

        return $db->query($sql, [$kode, $kelas, $thnAjaran, $semester])->getResultArray();
    }
}
