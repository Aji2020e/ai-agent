<?php

namespace App\Libraries\Academic;

use App\Libraries\AcademicDb;
use RuntimeException;

/**
 * Asisten dosen: profil DOSEN + dokumen panduan.
 */
class DosenAssistant extends AssistantModule
{
    public static function slug(): string
    {
        return 'dosen';
    }

    public static function name(): string
    {
        return 'Asisten Dosen';
    }

    protected static function defaultMap(): array
    {
        return [
            'profile_table' => 'DOSEN',
            'id_col'        => 'NIK',
            'profile_cols'  => ['KODE', 'NIK', 'NAMA', 'JENKEL', 'TglLahir'],
        ];
    }

    public static function analyze(array $params): array
    {
        $map      = static::tableMap();
        $nik      = static::requireParam($params, 'nik', 30);
        $question = static::requireQuestion($params);

        $rows = AcademicDb::select($map['profile_table'], $map['profile_cols'], [$map['id_col'] => $nik], 1, $map['id_col'] . ' ASC', [$map['id_col']], static::slug());

        if ($rows === []) {
            // Coba cari by KODE juga
            $rows = AcademicDb::select($map['profile_table'], $map['profile_cols'], ['KODE' => $nik], 1, 'KODE ASC', ['KODE'], static::slug());
        }

        if ($rows === []) {
            throw new RuntimeException("Data dosen '{$nik}' tidak ditemukan.");
        }
        $profile = $rows[0];

        $dataText = 'DATA DOSEN (read-only, dari database akademik):'
            . "\nProfil: " . json_encode($profile, JSON_UNESCAPED_UNICODE);

        $docsList = static::docsList();
        $docs = static::docs();
        if ($docs !== '') {
            $dataText .= "\n\nPANDUAN RESMI (ikuti bila relevan):\n" . $docs;
        }

        $system = 'Kamu analis data kampus untuk dosen. Kamu HANYA boleh menganalisis data yang diberikan — tidak boleh mengubah data.';

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

