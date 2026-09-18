<?php

namespace App\Libraries;

use App\Models\SettingModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/**
 * Akses READ-ONLY ke database akademik.
 *
 * - Konfigurasi: settings akad_* (UI admin) fallback ke env group akademik.
 * - HANYA menyediakan SELECT. Tidak ada method tulis di class ini.
 * - Nama tabel/kolom divalidasi regex + dicocokkan ke skema nyata (INFORMATION_SCHEMA).
 */
class AcademicDb
{
    private static ?BaseConnection $conn = null;

    /** @return array{hostname,port,database,username,password,DBDriver} tanpa password bila $masked */
    public static function config(bool $masked = false): array
    {
        $s = new SettingModel();
        /** @var \Config\Database $env */
        $env = config('Database');

        $pass = $s->getSecret('akad_pass', '');
        if ($pass === '') {
            $pass = $env->akademik['password'] ?? '';
        }

        return [
            'hostname' => $s->getGlobal('akad_host', $env->akademik['hostname'] ?? 'localhost') ?: 'localhost',
            'port'     => (int) ($s->getGlobal('akad_port', (string) ($env->akademik['port'] ?? 3306)) ?: 3306),
            'database' => $s->getGlobal('akad_db', $env->akademik['database'] ?? '') ?: '',
            'username' => $s->getGlobal('akad_user', $env->akademik['username'] ?? '') ?: '',
            'password' => $masked ? ($pass !== '' ? '***' : '') : $pass,
            'DBDriver' => $s->getGlobal('akad_driver', $env->akademik['DBDriver'] ?? 'MySQLi') ?: 'MySQLi',
        ];
    }

    public static function connect(): BaseConnection
    {
        if (self::$conn instanceof BaseConnection) {
            return self::$conn;
        }

        $c = self::config();

        if ($c['database'] === '') {
            throw new RuntimeException('Database akademik belum dikonfigurasi (admin → API & Integrasi).');
        }

        /** @var \Config\Database $cfg */
        $cfg             = config('Database');
        $cfg->akademik   = array_merge($cfg->akademik, [
            'hostname' => $c['hostname'],
            'port'     => $c['port'],
            'database' => $c['database'],
            'username' => $c['username'],
            'password' => $c['password'],
            'DBDriver' => $c['DBDriver'],
            'DBDebug'  => false,
            'encrypt'  => false,
        ]);

        try {
            self::$conn = \Config\Database::connect('akademik', false);
            self::$conn->query('SELECT 1');
        } catch (Throwable $e) {
            self::$conn = null;
            throw new RuntimeException('Tidak dapat terhubung ke DB akademik: ' . $e->getMessage());
        }

        return self::$conn;
    }

    public static function reset(): void
    {
        self::$conn = null;
    }

    /** Tes koneksi + hitung tabel. */
    public static function test(): array
    {
        $db     = self::connect();
        $tables = self::tables($db);

        return ['database' => self::config(true)['database'], 'tables' => count($tables)];
    }

    public static function tables(?BaseConnection $db = null): array
    {
        $db  ??= self::connect();
        $out = [];

        $driver = $db->DBDriver;
        $sql    = str_contains($driver, 'SQLSRV')
            ? "SELECT TABLE_NAME AS n FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME"
            : 'SHOW FULL TABLES WHERE Table_type = ' . $db->escape('BASE TABLE');

        foreach ($db->query($sql)->getResultArray() as $row) {
            $out[] = array_values($row)[0];
        }

        return $out;
    }

    /** Daftar kolom nyata sebuah tabel (563+ tabel? tidak — per tabel yang diminta saja). */
    public static function columns(string $table): array
    {
        self::ident($table);

        // Skema jarang berubah: cache 1 jam agar tiap select() tidak
        // mengulang query INFORMATION_SCHEMA (round-trip ke DB akademik).
        $ck = 'akad_cols_' . md5($table);
        try {
            $hit = cache()->get($ck);
            if (is_array($hit)) {
                return $hit;
            }
        } catch (\Throwable) {
        }

        $db = self::connect();

        if (str_contains($db->DBDriver, 'SQLSRV')) {
            $rows = $db->query(
                'SELECT COLUMN_NAME AS n FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ' . $db->escape($table) . ' ORDER BY ORDINAL_POSITION'
            )->getResultArray();
        } else {
            $rows = $db->query('SHOW COLUMNS FROM ' . $db->protectIdentifiers($table))->getResultArray();
            $rows = array_map(static fn ($r) => ['n' => $r['Field'] ?? array_values($r)[0]], $rows);
        }

        $out = array_column($rows, 'n');
        try {
            cache()->save($ck, $out, 3600);
        } catch (\Throwable) {
        }

        return $out;
    }

    /**
     * SELECT aman: tabel & kolom tervalidasi, nilai di-escape Query Builder.
     * $orderBy wajib (SQLSRV butuh ORDER untuk LIMIT).
     * $trimWhere: kolom ID yang dicocokkan tanpa spasi tepi (data legacy sering berpadding).
     */
    public static function select(string $table, array $cols, array $where = [], int $limit = 50, string $orderBy = '', array $trimWhere = []): array
    {
        self::ident($table);
        $real = self::columns($table);

        $safe = [];
        foreach ($cols as $c) {
            self::ident($c);
            if (in_array($c, $real, true)) {
                $safe[] = $c;
            }
        }

        if ($safe === []) {
            throw new RuntimeException("Tidak ada kolom valid untuk tabel {$table}.");
        }

        $db      = self::connect();
        $builder = $db->table($table)->select(implode(',', $safe));

        foreach ($where as $k => $v) {
            self::ident($k);
            if (in_array($k, $trimWhere, true)) {
                $builder->where('LTRIM(RTRIM(' . $k . ')) = ' . $db->escape($v), null, false);
            } else {
                $builder->where($k, $v);
            }
        }

        if ($orderBy !== '') {
            $parts = preg_split('/\s+/', trim($orderBy));
            self::ident($parts[0]);
            $dir = strtoupper($parts[1] ?? 'ASC');
            $builder->orderBy($parts[0], in_array($dir, ['ASC', 'DESC'], true) ? $dir : 'ASC');
        }

        return $builder->limit(max(1, min($limit, 200)))->get()->getResultArray();
    }

    private static function ident(string $s): void
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $s)) {
            throw new RuntimeException('Identifikator tidak valid.');
        }
    }
}
