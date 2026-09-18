<?php

namespace App\Libraries;

use App\Database\Seeds\DefaultUserSeeder;
use Throwable;

/**
 * Logika instalasi terpusat (dipakai spark app:install & web installer).
 *
 * Setiap step: ['label' => ..., 'ok' => bool, 'detail' => ...]
 */
class Installer
{
    public static function dbName(): string
    {
        /** @var \Config\Database $cfg */
        $cfg = config('Database');

        return $cfg->default['database'] ?? '';
    }

    /** True bila DB sudah siap (tabel inti ada). Tidak throw. */
    public static function isInstalled(): bool
    {
        try {
            $db = \Config\Database::connect();

            return $db->tableExists('migrations') && $db->tableExists('users');
        } catch (Throwable) {
            return false;
        }
    }

    /** Jalankan semua step. Return ['success' => bool, 'steps' => [...]]. Tidak throw. */
    public static function run(): array
    {
        $steps  = [];
        $dbName = self::dbName();

        if ($dbName === '') {
            return [
                'success' => false,
                'steps'   => [self::step('Konfigurasi database', false, 'database.default.database kosong (cek .env).')],
            ];
        }

        // 1. Buat database
        try {
            /** @var \Config\Database $cfg */
            $cfg  = config('Database');
            $host = $cfg->default['hostname'] ?? 'localhost';
            $port = (int) ($cfg->default['port'] ?? 3306);
            $user = $cfg->default['username'] ?? '';
            $pass = $cfg->default['password'] ?? '';

            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $link = new \mysqli($host, $user, $pass, '', $port);
            $link->set_charset('utf8mb4');
            $safe = '`' . str_replace('`', '``', $dbName) . '`';
            $link->query("CREATE DATABASE IF NOT EXISTS {$safe} CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
            $link->close();

            $steps[] = self::step("Database '{$dbName}' siap", true, "Host {$host}:{$port}");
        } catch (Throwable $e) {
            $steps[] = self::step("Database '{$dbName}'", false, $e->getMessage());

            return ['success' => false, 'steps' => $steps];
        }

        // 2. Migrasi (namespace App = 4 tabel proyek ini)
        try {
            $runner = \Config\Services::migrations();
            $runner->setNamespace('App');

            $before = count($runner->getHistory());
            $ok     = $runner->latest();
            $after  = count($runner->getHistory());

            if ($ok === false) {
                throw new \RuntimeException('MigrationRunner mengembalikan gagal.');
            }

            $moved = $after - $before;
            $steps[] = self::step(
                'Migrasi database',
                true,
                $moved > 0 ? "{$moved} migrasi baru dijalankan." : 'Tidak ada migrasi baru (sudah terkini).'
            );
        } catch (Throwable $e) {
            $steps[] = self::step('Migrasi database', false, $e->getMessage());

            return ['success' => false, 'steps' => $steps];
        }

        // 3. Seed admin bila users kosong
        try {
            $db = \Config\Database::connect();

            if (! $db->tableExists('users')) {
                throw new \RuntimeException("Tabel 'users' tidak ditemukan setelah migrasi.");
            }

            $count = $db->table('users')->countAllResults();

            if ($count > 0) {
                $steps[] = self::step('Data awal', true, "Tabel users sudah berisi {$count} baris — seed dilewati.");
            } else {
                (new DefaultUserSeeder(config('Database')))->run();
                $steps[] = self::step('Data awal', true, 'Admin default dibuat (admin / admin123 — segera ganti!).');
            }
        } catch (Throwable $e) {
            $steps[] = self::step('Data awal', false, $e->getMessage());

            return ['success' => false, 'steps' => $steps];
        }

        return ['success' => true, 'steps' => $steps];
    }

    private static function step(string $label, bool $ok, string $detail = ''): array
    {
        return ['label' => $label, 'ok' => $ok, 'detail' => $detail];
    }
}
