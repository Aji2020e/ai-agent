<?php

namespace App\Database\Seeds;

use App\Libraries\Academic\ModuleRegistry;
use CodeIgniter\Database\Seeder;

/**
 * Buat kebijakan awal untuk klien API yang sudah ada.
 *
 * PENTING — seeder ini dirancang untuk TIDAK mengubah perilaku. Menyalakan
 * penegakan otorisasi secara langsung akan memutus integrasi yang sedang
 * berjalan, jadi:
 *
 *  - `on_violation` = log_only  → pelanggaran DICATAT tapi belum ditolak
 *  - `scope`        = all       → tidak ada pembatasan baris (seperti sekarang)
 *  - `modules`      = '*' di-materialisasi menjadi daftar slug nyata
 *  - `tools_allowed`= semua tool yang sekarang tersedia
 *
 * Setelah 1-2 minggu memantau tabel `auth_violations` dan tidak ada
 * false-positive, admin mempersempit tiap klien lalu mengubah
 * `on_violation` menjadi `deny_and_log`.
 *
 * Jalankan: php spark db:seed ClientPolicySeeder
 */
class ClientPolicySeeder extends Seeder
{
    public function run()
    {
        // Tabel mungkin belum ada bila migrasi belum dijalankan.
        if (! $this->db->tableExists('client_policies') || ! $this->db->tableExists('api_clients')) {
            echo "Lewati: tabel client_policies/api_clients belum ada. Jalankan 'php spark migrate' dulu.\n";

            return;
        }

        $clients  = $this->db->table('api_clients')->get()->getResultArray();
        $existing = array_map(
            'intval',
            array_column($this->db->table('client_policies')->select('client_id')->get()->getResultArray(), 'client_id')
        );

        $allSlugs = array_keys(ModuleRegistry::allActive());
        $created  = 0;

        foreach ($clients as $c) {
            $id = (int) $c['id'];
            if (in_array($id, $existing, true)) {
                continue;               // sudah punya kebijakan: jangan ditimpa
            }

            $modules = trim((string) ($c['modules'] ?? ''));

            // '*' atau kosong → pakai seluruh modul aktif saat ini.
            // Dengan begini klien wildcard tidak tiba-tiba kehilangan akses,
            // tapi juga tidak lagi bergantung pada makna '*' yang ambigu.
            if ($modules === '*' || $modules === '') {
                $moduleList = implode(',', $allSlugs);
                $note       = 'Dibuat otomatis dari modules="*". PERSEMPIT daftar ini, '
                            . 'lalu ubah on_violation ke deny_and_log.';
            } else {
                $moduleList = $modules;
                $note       = 'Dibuat otomatis mempertahankan daftar modul lama. '
                            . 'Tinjau ulang, lalu ubah on_violation ke deny_and_log.';
            }

            $this->db->table('client_policies')->insert([
                'client_id'        => $id,
                'scope'            => 'all',
                'subject_required' => 0,
                'subject_types'    => '',
                'unit_type'        => null,
                'unit_ids'         => null,
                'role_scope'       => null,
                'field_policy'     => null,
                'row_limit'        => 200,
                'query_budget'     => 25,
                'tools_allowed'    => 'db_lookup,web_search,file_reader',
                'on_violation'     => 'log_only',
                'notes'            => $note,
                'created_at'       => date('Y-m-d H:i:s'),
                'updated_at'       => date('Y-m-d H:i:s'),
            ]);

            $created++;

            // Selaraskan kolom modules di api_clients agar tidak lagi memakai '*'
            if ($modules === '*') {
                $this->db->table('api_clients')->where('id', $id)->update(['modules' => $moduleList]);
            }
        }

        echo "ClientPolicySeeder: {$created} kebijakan dibuat (mode log_only).\n";

        if ($created > 0) {
            echo "Langkah berikutnya:\n"
                . "  1. Pantau tabel auth_violations selama 1-2 minggu.\n"
                . "  2. Persempit scope/modul/kolom tiap klien lewat menu Admin > API.\n"
                . "  3. Ubah on_violation menjadi deny_and_log per klien.\n";
        }
    }
}
