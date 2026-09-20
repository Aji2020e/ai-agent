<?php

namespace App\Libraries\Academic;

use App\Libraries\AcademicDb;
use App\Libraries\V2Client;
use App\Models\ApiDocModel;
use App\Models\ApiDictionaryModel;
use RuntimeException;

/**
 * Mesin analis untuk modul DINAMIS (dibuat via wizard).
 * query_config: {profile:{table,id_col,cols[]}, related:[{key,table,id_col,cols[],limit,order}]}
 */
class DynamicAssistant extends AssistantModule
{
    private static array $row = [];

    public static function slug(): string
    {
        return self::$row['slug'] ?? 'dinamis';
    }

    public static function name(): string
    {
        return self::$row['name'] ?? 'Asisten';
    }

    protected static function defaultMap(): array
    {
        return [];
    }

    public static function analyze(array $params): array
    {
        throw new RuntimeException('Gunakan DynamicAssistant::analyzeModule($row, $params).');
    }

    /**
     * Ambil data via fungsi siap pakai v2 (tanpa SQL langsung).
     * Tabel legacy → fungsi: mhs=profil, aktivitas_kuliah_mhs=akm, KRS=krs, dosen=dosen.
     * Return struktur $data yang sama dengan jalur SQL.
     */
    protected static function viaV2Functions(array $cfg, string $id, array $params): array
    {
        $map = ['mhs' => 'profil', 'aktivitas_kuliah_mhs' => 'akm', 'KRS' => 'krs', 'dosen' => 'dosen', 'KEUANGAN_PEMBAYARAN_MHS' => 'tagihan'];
        $data = [];

        $call = static function (?string $table, string $fallbackId) use ($map, $params) {
            $func = $map[$table ?? ''] ?? null;
            if ($func === null) {
                throw new RuntimeException("Tabel '{$table}' belum punya fungsi v2.");
            }
            $args = in_array($func, ['dosen'], true) ? ['nik' => $fallbackId] : ['npm' => $fallbackId];
            if ($func === 'krs') {
                foreach (['semester' => 'semester', 'tahun_ajaran' => 'tahun_ajaran'] as $pk => $qk) {
                    $v = trim((string) ($params[$pk] ?? ''));
                    if ($v !== '') {
                        $args[$qk] = $v;
                    }
                }
            }

            return V2Client::get($func, $args);
        };

        $p = $cfg['profile'] ?? [];
        if (empty($p['table'])) {
            throw new RuntimeException('Konfigurasi sumber data modul belum lengkap.');
        }
        $prof = $call($p['table'], $id);
        $data['profil'] = $prof;
        if (empty($data['profil']) && empty($cfg['profile_optional'])) {
            throw new RuntimeException('Data tidak ditemukan (via fungsi v2).');
        }

        foreach ($cfg['related'] ?? [] as $rel) {
            if (empty($rel['table'])) {
                continue;
            }
            try {
                $data[$rel['key'] ?? $rel['table']] = $call($rel['table'], $id);
            } catch (\Throwable $e) {
                log_message('warning', '[DynamicAssistant] v2 related gagal: ' . $e->getMessage());
            }
        }

        if (! empty($params['tabel']) && in_array($params['tabel'], $cfg['lookup_tables'] ?? [], true)) {
            $data['lookup'] = $call($params['tabel'], $id);
        }

        return $data;
    }

    protected static function allowedTables(): array
    {
        $tables = parent::allowedTables();

        if (! empty(self::$row['query_config'])) {
            $cfg = json_decode(self::$row['query_config'], true);
            if (is_array($cfg)) {
                foreach (['profile'] as $k) {
                    if (! empty($cfg[$k]['table'])) {
                        $tables[] = $cfg[$k]['table'];
                    }
                }
                foreach ($cfg['related'] ?? [] as $rel) {
                    if (! empty($rel['table'])) {
                        $tables[] = $rel['table'];
                    }
                }
                foreach ($cfg['lookup_tables'] ?? [] as $t) {
                    $tables[] = $t;
                }
            }
        }

        return array_values(array_unique($tables));
    }

    /** Lookup satu tabel whitelist. Kolom sensitif (pass/foto) selalu dibuang. */
    private static function lookup(string $table, string $kolom, string $fcol, string $fval, int $limit): array
    {
        $real = AcademicDb::columns($table);
        $safe = [];
        foreach ($real as $c) {
            if (preg_match('/pass|password|passwd|foto/i', $c)) {
                continue;
            }
            $safe[] = $c;
        }

        $want = [];
        foreach (preg_split('/[\s,]+/', $kolom) as $c) {
            $c = trim($c);
            if ($c !== '' && preg_match('/^[A-Za-z0-9_]+$/', $c) && in_array($c, $safe, true)) {
                $want[] = $c;
            }
        }
        $cols = $want !== [] ? array_slice($want, 0, 20) : array_slice($safe, 0, 15);

        $where = [];
        if ($fcol !== '' && preg_match('/^[A-Za-z0-9_]+$/', $fcol) && in_array($fcol, $real, true) && mb_strlen($fval) <= 100) {
            $where = [$fcol => $fval];
        }

        $order = $cols[0] . ' ASC';

        return [
            'tabel' => $table,
            'baris' => AcademicDb::select($table, $cols, $where, max(1, min($limit, 50)), $order, [], static::slug()),
        ];
    }

    public static function analyzeModule(array $module, array $params): array
    {
        self::$row = $module;

        $cfg = json_decode($module['query_config'] ?? '', true);
        if (! is_array($cfg) || empty($cfg['profile']['table'])) {
            throw new RuntimeException('Konfigurasi sumber data modul belum lengkap.');
        }

        $idParam = $module['id_param'] ?: 'id';
        $id      = self::requireParam($params, $idParam, 50);
        $question = self::requireQuestion($params);

        $p = $cfg['profile'];

        // Jalur utama: fungsi siap pakai di v2 (tanpa menebak tabel).
        // Otomatis aktif bila v2_base/v2_key dikonfigurasi; fallback ke SQL langsung bila gagal.
        $viaV2 = V2Client::configured();
        if ($viaV2) {
            try {
                $data = self::viaV2Functions($cfg, $id, $params);
            } catch (\Throwable $e) {
                log_message('warning', '[DynamicAssistant] v2 functions gagal, fallback SQL: ' . $e->getMessage());
                $viaV2 = false;
            }
        }
        if (! $viaV2) {
            $prows = AcademicDb::select(
                $p['table'], $p['cols'] ?? [], [$p['id_col'] => $id],
                1, ($p['cols'][0] ?? $p['id_col']) . ' ASC', [$p['id_col']], static::slug()
            );

            if ($prows === []) {
                if (empty($cfg['profile_optional'])) {
                    throw new RuntimeException('Data ' . ($module['id_label'] ?: 'ID') . " '{$id}' tidak ditemukan.");
                }
                $data = [];
            } else {
                $data = ['profil' => $prows[0]];
            }
        }

        // Jalur SQL langsung (hanya bila fungsi v2 tidak dipakai).
        if (! $viaV2) {
            // Lookup bebas antar-tabel (khusus modul berhak, mis. admin akademik).
            // Tabel HARUS terdaftar di query_config.lookup_tables + lolos skema nyata.
            if (! empty($params['tabel']) && in_array($params['tabel'], $cfg['lookup_tables'] ?? [], true)) {
                $data['lookup'] = self::lookup(
                    $params['tabel'],
                    $params['kolom'] ?? '',
                    $params['filter_kolom'] ?? '',
                    $params['filter_nilai'] ?? '',
                    (int) ($params['limit'] ?? 20)
                );
            }

            foreach ($cfg['related'] ?? [] as $rel) {
                if (empty($rel['table']) || empty($rel['id_col'])) {
                    continue;
                }
                $data[$rel['key'] ?? $rel['table']] = AcademicDb::select(
                    $rel['table'], $rel['cols'] ?? [], [$rel['id_col'] => $id],
                    (int) ($rel['limit'] ?? 50), ($rel['order'] ?? ($rel['cols'][0] ?? $rel['id_col']) . ' ASC'), [$rel['id_col']],
                    static::slug()
                );
            }
        }

        $dataText = 'DATA (read-only, dari database akademik):' . "\n" . json_encode($data, JSON_UNESCAPED_UNICODE);

        $docs = (new ApiDocModel())->forModule($module['slug']);
        foreach ($docs as $d) {
            $dataText .= "\n\nPANDUAN RESMI — " . $d['title'] . ":\n" . $d['content'];
        }

        $system = 'Kamu analis data kampus untuk modul "' . $module['name'] . '". '
            . ($module['description'] ? $module['description'] . ' ' : '')
            . 'Kamu HANYA boleh menganalisis data yang diberikan — tidak boleh mengubah data.'
            . "\n\nSKEMA LEGACY DB_MAPPING (acuan kunci, jangan ditebak): "
            . 'mhs(NPM,NAMA,KD_JUR,THA,KODA:A=Aktif/L=Lulus/K=Keluar/D=DO/C=Cuti,PA=NIK dosen,NIKKTP, TEMLAHIR,TGLAHIR,JENIS:L/P); '
            . 'DEPARTMENT(KD_DEPT=NAMA_PRODI,JENJANG); dosen(NIK,NAMA,NIDN); '
            . 'KULIAH(KODE,MKL=nama,SKS,SEM1,KD_JUR); KULIAHTP(IDKULIAH,KODE,KELAS,NIK dosen,TAHUN_AKTIF); '
            . 'KRS(NPM,KODE,THN_AJARAN,SEMESTER,KELAS,NILAI=huruf); aktivitas_kuliah_mhs(npm,status,sks,total_sks,ip_sem,ipk); '
            . 'JADWALPERKULIAHAN(IDKULIAH,HARI,JAM→JAM.idJam,RUANG); RUANGAN(Ruang,NamaRuang); tahun_akademik(ID_TAHUN,THN_AKADEMIK,SEMESTER).';

        try {
            $lines = (new ApiDictionaryModel())->forQuestion($module['slug'], $question);
            if ($lines !== []) {
                $system .= "\n\nKAMUS DATA RESMI (sumber kebenaran tabel/kolom):\n" . implode("\n", $lines);
            }
        } catch (\Throwable) {
        }

        [$analysis, $tokens, $model, $jejak, $keyakinan] = static::answer($system, $dataText, $question, 300, $params['__step'] ?? null);

        $tables = [];
        $tables[] = (string) ($cfg['profile']['table'] ?? '');
        foreach ((array) ($cfg['related'] ?? []) as $rel) {
            $tables[] = (string) ($rel['table'] ?? '');
        }
        if (! empty($params['tabel']) && in_array($params['tabel'], (array) ($cfg['lookup_tables'] ?? []), true)) {
            $tables[] = (string) $params['tabel'];
        }

        $rowsCount = [];
        foreach ($data as $k => $v) {
            $rowsCount[$k] = is_array($v) ? count($v) : 1;
        }
        $evidence = static::evidence(
            array_values(array_filter(array_unique($tables))),
            $docs,
            $question,
            ['id_param' => $idParam, 'id_value' => $id, 'module' => (string) ($module['slug'] ?? ''), 'rows' => $rowsCount]
        );
        $confidence = static::confidence($data, $analysis, $evidence, $keyakinan ?? null);

        return [
            'data'       => $data,
            'analysis'   => $analysis,
            'tokens'     => $tokens,
            'model'      => $model,
            'jejak'      => $jejak,
            'evidence'   => $evidence,
            'confidence' => $confidence,
        ];
    }
}

