<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Jejak audit pelanggaran otorisasi (tabel `auth_violations`).
 *
 * Penulisan di sini tidak boleh pernah menggagalkan request utama —
 * lihat PolicyGuard::violate() yang membungkus semua panggilan dengan try/catch.
 */
class AuthViolationModel extends Model
{
    protected $table            = 'auth_violations';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    // 'created_at' WAJIB ada di daftar ini. Dengan $protectFields = true,
    // CI4 membuang diam-diam setiap field yang tidak terdaftar — sehingga
    // timestamp selalu NULL dan query berbasis waktu mengembalikan kosong.
    protected $allowedFields    = [
        'client_id', 'ip', 'violation', 'module', 'attempted', 'allowed',
        'endpoint', 'subject_id', 'created_at',
    ];
    protected $useTimestamps = false;
    protected $createdField  = 'created_at';

    /** Jenis pelanggaran yang dikenal (untuk filter di layar admin). */
    public const KINDS = [
        'no_policy',          // klien belum punya baris kebijakan
        'module_denied',      // modul di luar scope klien
        'idor',               // mencoba subject milik orang lain
        'subject_missing',    // scope self tanpa subject
        'subject_type',       // tipe subject tidak diizinkan
        'field_denied',       // kolom masuk daftar larangan
        'field_not_allowed',  // kolom tidak ada di daftar izin
        'unit_scope_empty',   // scope unit tanpa unit_ids
        'unit_unsupported',   // modul tidak punya kolom unit
        'scope_unknown',      // nilai scope tidak dikenali
        'tool_denied',        // AI mencoba memakai tool terlarang
        'budget_exceeded',    // anggaran query per request habis
        'role_unknown',       // peran tak dikenal (dulu: dapat hak admin)
        'file_outside_root',  // FileReaderTool di luar direktori izin
    ];

    public function record(array $row): void
    {
        $row['created_at'] = date('Y-m-d H:i:s');

        // Potong nilai agar tidak merusak lebar kolom
        foreach (['attempted', 'allowed'] as $f) {
            if (isset($row[$f])) {
                $row[$f] = mb_substr((string) $row[$f], 0, 100);
            }
        }
        if (isset($row['endpoint'])) {
            $row['endpoint'] = mb_substr((string) $row['endpoint'], 0, 150);
        }

        $this->insert($row);
    }

    /**
     * Pelanggaran terbaru, dengan nama klien.
     */
    public function recent(int $limit = 100, ?string $kind = null, ?int $clientId = null): array
    {
        $q = $this->select('auth_violations.*, api_clients.name as client_name')
                  ->join('api_clients', 'api_clients.id = auth_violations.client_id', 'left')
                  ->orderBy('auth_violations.id', 'DESC')
                  ->limit(max(1, min($limit, 500)));

        if ($kind !== null && $kind !== '') {
            $q->where('auth_violations.violation', $kind);
        }
        if ($clientId !== null && $clientId > 0) {
            $q->where('auth_violations.client_id', $clientId);
        }

        return $q->findAll();
    }

    /**
     * Hitung pelanggaran per klien dalam rentang waktu — untuk mendeteksi
     * enumerasi (banyak percobaan `idor` berurutan dari satu klien).
     *
     * @return array<int, array{client_id:?int, client_name:?string, violation:string, n:int}>
     */
    public function tally(int $minutes = 60, ?string $kind = null): array
    {
        $since = date('Y-m-d H:i:s', time() - ($minutes * 60));

        $q = $this->select('auth_violations.client_id, api_clients.name as client_name, auth_violations.violation, COUNT(*) as n')
                  ->join('api_clients', 'api_clients.id = auth_violations.client_id', 'left')
                  ->where('auth_violations.created_at >=', $since)
                  ->groupBy('auth_violations.client_id, api_clients.name, auth_violations.violation')
                  ->orderBy('n', 'DESC');

        if ($kind !== null && $kind !== '') {
            $q->where('auth_violations.violation', $kind);
        }

        return $q->findAll();
    }

    /** Bersihkan jejak lama agar tabel tidak membengkak. Return jumlah baris terhapus. */
    public function prune(int $keepDays = 90): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - (max(1, $keepDays) * 86400));

        if (! $this->where('created_at <', $cutoff)->delete()) {
            return 0;
        }

        return (int) $this->db->affectedRows();
    }
}
