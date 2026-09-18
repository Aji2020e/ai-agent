<?php

namespace App\Libraries\Academic;

use App\Libraries\AcademicDb;
use RuntimeException;

/**
 * Asisten staff/tendik: profil PSDM_KARYAWAN (TANPA kolom sensitif: pass, foto).
 */
class StaffAssistant extends AssistantModule
{
    public static function slug(): string
    {
        return 'staff';
    }

    public static function name(): string
    {
        return 'Asisten Staff';
    }

    protected static function defaultMap(): array
    {
        return [
            'profile_table' => 'PSDM_KARYAWAN',
            'id_col'        => 'nik',
            // SENGAJA tanpa: pass, foto
            'profile_cols'  => ['nik', 'nama', 'jabatan', 'id_bagian', 'status', 'jenis_kelamin', 'email', 'nohp', 'tgl_masuk', 'pendidikan_terakhir', 'is_dosen'],
        ];
    }

    public static function analyze(array $params): array
    {
        $map      = static::tableMap();
        $nik      = static::requireParam($params, 'nik', 30);
        $question = static::requireQuestion($params);

        $rows = AcademicDb::select($map['profile_table'], $map['profile_cols'], [$map['id_col'] => $nik], 1, $map['id_col'] . ' ASC', [$map['id_col']]);

        if ($rows === []) {
            throw new RuntimeException("Data staff '{$nik}' tidak ditemukan.");
        }
        $profile = $rows[0];

        $dataText = 'DATA STAFF (read-only, dari database akademik):'
            . "\nProfil: " . json_encode($profile, JSON_UNESCAPED_UNICODE);

        $docsList = static::docsList();
        $docs = static::docs();
        if ($docs !== '') {
            $dataText .= "\n\nPANDUAN RESMI (ikuti bila relevan):\n" . $docs;
        }

        $system = 'Kamu analis data kampus untuk staff/tendik. Kamu HANYA boleh menganalisis data yang diberikan — tidak boleh mengubah data.';

        $kamus = static::dictionary($question);
        if ($kamus !== '') {
            $system .= "\n\n" . $kamus;
        }

        [$analysis, $tokens, $model, $jejak, $keyakinan] = static::answer($system, $dataText, $question, 300, $params['__step'] ?? null);

        $dataOut = ['profil' => $profile];
        $evidence = static::evidence(
            [$map['profile_table']],
            $docsList,
            $question,
            ['id_param' => 'nik', 'id_value' => $nik, 'rows' => [$map['profile_table'] => 1]]
        );
        $confidence = static::confidence($dataOut, $analysis, $evidence, $keyakinan ?? null);

        return [
            'data'       => $dataOut,
            'analysis'   => $analysis,
            'tokens'     => $tokens,
            'model'      => $model,
            'jejak'      => $jejak,
            'evidence'   => $evidence,
            'confidence' => $confidence,
        ];
    }
}

