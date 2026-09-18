<?php

namespace App\Models;

use CodeIgniter\Model;
use CodeIgniter\Database\Config;

class AcademicModel extends Model
{
    protected $DBGroup = 'smartSistemV2';
    protected $returnType = 'array';

    public function getBiodataMahasiswa(string $npm): ?array
    {
        $db = Config::connect('smartSistemV2');

        $sql = "SELECT m.npm, m.NAMA as nama, m.email, d.NAMA_DEPT as prodi, d.ENGLISH_NAME as prodi_en,
                       d.KD_DEPT as kd_jur, m.KODA as kode_prodi, m.THA, m.STATUS_MHS as status_mhs,
                       m.TEMLAHIR, m.TGLAHIR, m.judulTa, m.TAEnglish, m.alamatmhs, m.telpmhs,
                       m.nama_ortu, m.no_hp, m.pekerjaan_ortu
                FROM mhs m
                JOIN department d ON m.kd_jur = d.kd_dept
                WHERE m.npm = ?";

        $result = $db->query($sql, [$npm])->getRowArray();

        return $result ?: null;
    }

    public function getKrsMahasiswa(string $npm, string $thnAjaran, int $semester): array
    {
        $db = Config::connect('smartSistemV2');

        $sql = "SELECT k.thn_ajaran, k.semester, k.kode, ku.MKL as mata_kuliah, k.NILAI as nilai,
                       k.presensi, k.tugas, k.uts, k.uas, k.kelas, ku.SKS as sks
                FROM krs k
                JOIN kuliah ku ON k.kode = ku.kode
                WHERE k.npm = ? AND k.thn_ajaran = ? AND k.semester = ?";

        return $db->query($sql, [$npm, $thnAjaran, $semester])->getResultArray();
    }

    public function getNilaiMahasiswa(string $npm, ?string $thnAjaran = null, ?int $semester = null): array
    {
        $db = Config::connect('smartSistemV2');

        $sql = "SELECT k.thn_ajaran, k.semester, k.kode, ku.MKL as mata_kuliah, k.NILAI as nilai, ku.SKS as sks
                FROM krs k
                JOIN kuliah ku ON k.kode = ku.kode
                WHERE k.npm = ?";
        $params = [$npm];

        if ($thnAjaran !== null) {
            $sql .= ' AND k.thn_ajaran = ?';
            $params[] = $thnAjaran;
        }

        if ($semester !== null) {
            $sql .= ' AND k.semester = ?';
            $params[] = $semester;
        }

        $sql .= ' ORDER BY k.thn_ajaran DESC, k.semester DESC';

        return $db->query($sql, $params)->getResultArray();
    }

    public function getSksKumulatif(string $npm, string $kdJur): int
    {
        $db = Config::connect('smartSistemV2');
        $result = $db->query("SELECT dbo.SKSKum(?, ?) as sks", [$npm, $kdJur])->getRowArray();

        return (int) ($result['sks'] ?? 0);
    }

    public function getTahunAkademikAktif(): ?array
    {
        $db = Config::connect('smartSistemV2');

        $sql = "SELECT TOP 1 tha.ID_TAHUN, tha.THN_AKADEMIK, tha.SEMESTER
                FROM tahun_akademik tha
                JOIN tahun_aktif taktif ON tha.ID_TAHUN = taktif.id_tahun";

        return $db->query($sql)->getRowArray() ?: null;
    }

    public function getMasaStudi(string $npm): ?array
    {
        $db = Config::connect('smartSistemV2');
        $result = $db->query("exec dbo.HITUNG_MASA_STUDI @npm = ?", [$npm])->getRowArray();

        return $result ?: null;
    }

    public function cariMahasiswa(string $keyword): array
    {
        $db = Config::connect('smartSistemV2');

        $sql = "SELECT TOP 5 m.npm, m.NAMA as nama, d.NAMA_DEPT as prodi
                FROM mhs m
                JOIN department d ON m.kd_jur = d.kd_dept
                WHERE m.npm LIKE ? OR m.NAMA LIKE ?
                ORDER BY m.NAMA ASC";

        return $db->query($sql, ['%' . $keyword . '%', '%' . $keyword . '%'])->getResultArray();
    }
}
