<?php

namespace App\Libraries\Academic;

use App\Libraries\AcademicDb;
use RuntimeException;

/**
 * Asisten mahasiswa: transkrip (NILAI), IPS/IPK (mhs_nilai_status),
 * prasyarat (MK_PRASYARAT) + dokumen panduan → anjuran MK semester depan dll.
 */
class MahasiswaAssistant extends AssistantModule
{
    public static function slug(): string
    {
        return 'mahasiswa';
    }

    public static function name(): string
    {
        return 'Asisten Mahasiswa';
    }

    protected static function defaultMap(): array
    {
        return [
            'profile_table' => 'mhs',
            'id_col'        => 'NPM',
            'profile_cols'  => ['NPM', 'NAMA', 'KD_JUR', 'THA', 'STATUS_MHS', 'smstr', 'EMAIL'],
            'nilai_table'   => 'KRS',
            'nilai_id_col'  => 'NPM',
            'nilai_cols'    => ['KODE', 'SEMESTER', 'THN_AJARAN', 'NILAI'],
            'ips_table'     => 'mhs_nilai_status',
            'ips_id_col'    => 'npm',
            'ips_cols'      => ['ips', 'ipk', 'sks_sem', 'sks_kum', 'status'],
            'prasy_table'   => 'MK_PRASYARAT',
        ];
    }

    public static function analyze(array $params): array
    {
        $map      = static::tableMap();
        $nim      = static::requireParam($params, 'nim', 20);
        $question = static::requireQuestion($params);

        $rows = AcademicDb::select($map['profile_table'], $map['profile_cols'], [$map['id_col'] => $nim], 1, $map['id_col'] . ' ASC', [$map['id_col']]);

        if ($rows === []) {
            throw new RuntimeException("Data mahasiswa NIM {$nim} tidak ditemukan.");
        }
        $profile = $rows[0];

        $nilai = AcademicDb::select(
            $map['nilai_table'],
            $map['nilai_cols'],
            [$map['nilai_id_col'] => $nim],
            100,
            $map['nilai_cols'][0] . ' ASC',
            [$map['nilai_id_col']]
        );

        $ips = AcademicDb::select(
            $map['ips_table'],
            $map['ips_cols'],
            [$map['ips_id_col'] => $nim],
            20,
            $map['ips_cols'][0] . ' ASC',
            [$map['ips_id_col']]
        );

        // MK belum lulus (nilai E) + MK berjalan (belum ada nilai)
        $failed   = [];
        $ongoing  = [];
        foreach ($nilai as $n) {
            $grade = strtoupper(trim((string) ($n[$map['nilai_cols'][3]] ?? '')));
            $kode  = trim((string) $n[$map['nilai_cols'][0]]);
            if ($kode === '') {
                continue;
            }
            if ($grade === 'E') {
                $failed[] = $kode;
            } elseif ($grade === '') {
                $ongoing[] = $kode;
            }
        }
        $failed  = array_values(array_unique($failed));
        $ongoing = array_values(array_unique($ongoing));

        $prasyarat = [];
        $needPrasy = array_values(array_unique(array_merge($failed, $ongoing)));
        if ($needPrasy !== []) {
            $db   = AcademicDb::connect();
            $real = AcademicDb::columns($map['prasy_table']);
            if (in_array('KODE', $real, true) && in_array('PRASY', $real, true)) {
                $rows2 = $db->table($map['prasy_table'])
                    ->select('KODE,PRASY')
                    ->whereIn('KODE', array_slice($needPrasy, 0, 30))
                    ->limit(60)
                    ->get()->getResultArray();
                foreach ($rows2 as $r) {
                    $prasyarat[] = ['kode' => trim((string) $r['KODE']), 'prasyarat' => trim((string) $r['PRASY'])];
                }
            }
        }

        $dataText = 'DATA MAHASISWA (read-only, dari database akademik):'
            . "\nProfil: " . json_encode($profile, JSON_UNESCAPED_UNICODE)
            . "\nNilai (" . count($nilai) . ' MK): ' . json_encode($nilai, JSON_UNESCAPED_UNICODE)
            . "\nIPS/IPK: " . json_encode($ips, JSON_UNESCAPED_UNICODE)
            . "\nPrasyarat MK belum lulus: " . json_encode($prasyarat, JSON_UNESCAPED_UNICODE);

        $docsList = static::docsList();
        $docs = static::docs();
        if ($docs !== '') {
            $dataText .= "\n\nPANDUAN AKADEMIK RESMI (ikuti bila relevan):\n" . $docs;
        }

        $system = 'Kamu analis data akademik. Kamu HANYA boleh menganalisis data yang diberikan — tidak boleh mengubah data. '
            . 'Fokus: anjuran MK yang sebaiknya diambil/diulang, urutan prasyarat, target IP. '
            . 'Tolak topik di luar akademik (info_kurang).';

        $kamus = static::dictionary($question);
        if ($kamus !== '') {
            $system .= "\n\n" . $kamus;
        }

        [$analysis, $tokens, $model, $jejak, $keyakinan] = static::answer($system, $dataText, $question, 300, $params['__step'] ?? null);

        $dataOut = ['profil' => $profile, 'ips' => $ips, 'mk_belum_lulus' => $failed, 'mk_berjalan' => $ongoing];
        $evidence = static::evidence(
            [$map['profile_table'], $map['nilai_table'], $map['ips_table'], $map['prasy_table']],
            $docsList,
            $question,
            [
                'id_param' => 'nim',
                'id_value' => $nim,
                'rows'     => [
                    $map['profile_table'] => 1,
                    $map['nilai_table']   => count($nilai),
                    $map['ips_table']     => count($ips),
                    $map['prasy_table']   => count($prasyarat),
                ],
            ]
        );
        $confidence = static::confidence($dataOut, $analysis, $evidence, $keyakinan ?? null);

        return [
            'data'     => $dataOut,
            'analysis' => $analysis,
            'tokens'   => $tokens,
            'model'    => $model,
            'jejak'    => $jejak,
            'evidence' => $evidence,
            'confidence' => $confidence,
        ];
    }
}

