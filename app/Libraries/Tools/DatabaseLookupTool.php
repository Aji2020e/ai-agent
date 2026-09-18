<?php

namespace App\Libraries\Tools;

use App\Models\AcademicModel;

class DatabaseLookupTool implements ToolInterface
{
    public function getName(): string
    {
        return 'db_lookup';
    }

    public function getDescription(): string
    {
        return 'Mengambil data akademik dari database smart-sistem-v2.';
    }

    public function run(array $params): ToolResult
    {
        $intent = $params['intent'] ?? 'biodata';
        $npm = $params['identifier'] ?? null;

        if (empty($npm)) {
            return new ToolResult(false, null, 'NPM tidak ditemukan.');
        }

        $model = new AcademicModel();

        try {
            switch ($intent) {
                case 'biodata':
                    $data = $model->getBiodataMahasiswa($npm);
                    return new ToolResult(
                        $data !== null,
                        $data,
                        $data === null ? 'Data mahasiswa tidak ditemukan.' : null,
                        'Biodata mahasiswa:'
                    );

                case 'nilai':
                    $data = $model->getNilaiMahasiswa($npm);
                    return new ToolResult(
                        ! empty($data),
                        $data,
                        empty($data) ? 'Data nilai tidak ditemukan.' : null,
                        'Data nilai mahasiswa:'
                    );

                case 'krs':
                    $thnAjaran = $params['thn_ajaran'] ?? null;
                    $semester = $params['semester'] ?? null;

                    if (! $thnAjaran || $semester === null) {
                        return new ToolResult(false, null, 'Tahun ajaran dan semester diperlukan.');
                    }

                    $data = $model->getKrsMahasiswa($npm, $thnAjaran, (int) $semester);
                    return new ToolResult(
                        ! empty($data),
                        $data,
                        empty($data) ? 'Data KRS tidak ditemukan.' : null,
                        'Data KRS mahasiswa:'
                    );

                case 'sks':
                    $biodata = $model->getBiodataMahasiswa($npm);
                    $sks = 0;
                    if ($biodata) {
                        $sks = $model->getSksKumulatif($npm, $biodata['kd_jur'] ?? '');
                    }
                    return new ToolResult(true, ['sks' => $sks], null, 'Total SKS mahasiswa:');

                default:
                    return new ToolResult(false, null, 'Intent db_lookup tidak dikenali.');
            }
        } catch (\Throwable $e) {
            return new ToolResult(false, null, $e->getMessage(), 'Gagal mengambil data akademik.');
        }
    }
}
