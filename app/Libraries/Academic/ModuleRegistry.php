<?php

namespace App\Libraries\Academic;

use App\Models\ApiModuleModel;

/**
 * Gabungan modul bawaan (kode) + modul dinamis (wizard).
 * + peta peran → modul (settings role_modules).
 */
class ModuleRegistry
{
    /** slug => [name, id_param, id_label, class, id_table, id_col, examples] */
    public static function coded(): array
    {
        return [
            'mahasiswa' => [
                'name'     => 'Asisten Mahasiswa',
                'id_param' => 'nim',
                'id_label' => 'NIM',
                'id_table' => 'mhs',
                'id_col'   => 'NPM',
                'name_col' => 'NAMA',
                'class'    => MahasiswaAssistant::class,
                'examples' => [
                    'MK apa yang sebaiknya saya ambil semester depan?',
                    'Apakah saya sudah lulus MK IF113?',
                    'Bagaimana cara menaikkan IPK saya?',
                ],
            ],
            'dosen' => [
                'name'     => 'Asisten Dosen',
                'id_param' => 'nik',
                'id_label' => 'NIK/Kode',
                'id_table' => 'DOSEN',
                'id_col'   => 'NIK',
                'name_col' => 'NAMA',
                'class'    => DosenAssistant::class,
                'examples' => [
                    'Sebutkan nama dan NIK saya.',
                    'Apa saja tugas administrasi saya semester ini?',
                ],
            ],
            'staff' => [
                'name'     => 'Asisten Staff',
                'id_param' => 'nik',
                'id_label' => 'NIK',
                'id_table' => 'PSDM_KARYAWAN',
                'id_col'   => 'nik',
                'name_col' => 'nama',
                'class'    => StaffAssistant::class,
                'examples' => [
                    'Sebutkan nama, jabatan dan email saya.',
                ],
            ],
        ];
    }

    /** Semua modul aktif (kode + dinamis), ternormalisasi. */
    public static function allActive(): array
    {
        $out = [];

        foreach (self::coded() as $slug => $c) {
            $out[$slug] = array_merge($c, [
                'slug'        => $slug,
                'description' => '',
                'source'      => 'bawaan',
            ]);
        }

        $m = new ApiModuleModel();
        foreach ($m->active() as $row) {
            $out[$row['slug']] = [
                'slug'        => $row['slug'],
                'name'        => $row['name'],
                'description' => (string) ($row['description'] ?? ''),
                'id_param'    => $row['id_param'] ?: 'id',
                'id_label'    => $row['id_label'] ?: 'ID',
                'examples'    => $m->examples($row),
                'source'      => 'dinamis',
                'row'         => $row,
            ];
        }

        return $out;
    }

    /**
     * Normalisasi role mentah portal → bucket peran.
     * Role tak dikenal (mis. keuangan) dikembalikan apa adanya agar
     * bisa cocok dengan peta kustom / slug modul; terakhir jatuh ke admin.
     */
    public static function normalizeRole(string $role): string
    {
        $role = strtolower(trim($role));

        if (str_starts_with($role, 'upt_')) {
            return 'staff'; // unit pelaksana teknis = staff
        }

        return match ($role) {
            'mhs', 'mahasiswa', 'student', 'siswa' => 'mahasiswa',
            'dsn', 'dosen', 'lecturer'             => 'dosen',
            'tendik', 'staff', 'karyawan', 'pegawai', 'operator', 'user' => 'staff',
            'admin', 'administrator', 'superadmin', 'baak', 'baa', 'akademik', 'admin_akademik' => 'admin_akademik',
            default                               => $role,
        };
    }

    /** Peta peran → daftar slug. Default: peran sama dengan slug modulnya. */
    public static function roleMap(): array
    {
        $defaults = [
            'mahasiswa'      => ['mahasiswa'],
            'dosen'          => ['dosen'],
            'staff'          => ['staff'],
            'admin_akademik' => ['adm_akademik', 'mahasiswa', 'dosen', 'staff'],
        ];

        try {
            $json = (new \App\Models\SettingModel())->getGlobal('role_modules', '');
            if ($json !== '') {
                $custom = json_decode($json, true);
                if (is_array($custom)) {
                    foreach ($custom as $role => $slugs) {
                        $role = strtolower(trim((string) $role));
                        if ($role !== '' && is_array($slugs)) {
                            $defaults[$role] = array_values(array_filter(array_map('trim', $slugs)));
                        }
                    }
                }
            }
        } catch (\Throwable) {
        }

        return $defaults;
    }

    /**
     * Modul yang boleh diakses: irisan (peta peran ∩ scope klien ∩ aktif).
     * $role '' = semua dalam scope (untuk discovery umum).
     *
     * @return array slug => info
     */
    /** Bagian dengan hak penuh (koma id di settings bagian_full, default IT=11). */
    public static function fullAccessBagians(): array
    {
        try {
            $raw = (new \App\Models\SettingModel())->getGlobal('bagian_full', '11');
        } catch (\Throwable) {
            $raw = '11';
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $raw))));
    }

    /** [id_bagian, nama_bagian] staff by NIK, null bila tak ketemu. */
    public static function staffBagian(string $nik): ?array
    {
        static $cache = [];

        if (array_key_exists($nik, $cache)) {
            return $cache[$nik];
        }

        try {
            // '_authz': lookup untuk MENENTUKAN wewenang (bagian staff),
            // bukan mengambil data subjek. Lihat AcademicDb::guard().
            $rows = \App\Libraries\AcademicDb::select(
                'PSDM_KARYAWAN', ['nik', 'id_bagian'], ['nik' => $nik], 1, 'nik ASC', ['nik'], '_authz'
            );
            if ($rows === []) {
                return $cache[$nik] = null;
            }
            $bid  = trim((string) ($rows[0]['id_bagian'] ?? ''));
            $nama = '';
            if ($bid !== '') {
                $brow = \App\Libraries\AcademicDb::select('PSDM_BAGIAN', ['nama_bagian'], ['id_bagian' => $bid], 1, 'id_bagian ASC', [], '_authz');
                $nama = trim((string) ($brow[0]['nama_bagian'] ?? ''));
            }

            return $cache[$nik] = ['id_bagian' => $bid, 'nama' => $nama];
        } catch (\Throwable) {
            return $cache[$nik] = null;
        }
    }

    /** Daftar role yang dikenal (untuk editor admin). */
    public static function knownRoles(): array
    {
        $roles = ['mahasiswa', 'dosen', 'staff', 'admin_akademik'];
        foreach (self::roleMap() as $role => $slugs) {
            if (! in_array($role, $roles, true)) {
                $roles[] = $role;
            }
        }
        sort($roles);

        return $roles;
    }

    public static function accessible(?array $client, string $role = ''): array
    {
        $all   = self::allActive();
        $raw   = strtolower(trim($role));
        $slugs = null;

        if ($raw !== '') {
            $map = self::roleMap();
            if (isset($map[$raw])) {
                // 1. Peta kustom/explisit dari admin (prioritas tertinggi)
                $slugs = $map[$raw];
            } else {
                $norm = self::normalizeRole($raw);
                if (isset($map[$norm])) {
                    $slugs = $map[$norm];
                } elseif (isset($all[$norm])) {
                    // 2. Role sama dengan slug modul (mis. hasil wizard)
                    $slugs = [$norm];
                } else {
                    // 3. PERBAIKAN H3 — role asing = TIDAK ADA modul.
                    // Sebelumnya cabang ini memberi hak admin_akademik, sehingga
                    // mengirim "role":"asdfgh" saja sudah cukup untuk naik hak.
                    // Kebijakan sekarang gagal-tertutup: tidak dikenali = ditolak.
                    $slugs = [];
                }
            }
        }

        $out = [];
        foreach ($all as $slug => $info) {
            if ($slugs !== null && ! in_array($slug, $slugs, true)) {
                continue;
            }
            if ($client !== null && ! (new \App\Models\ApiClientModel())->allowsModule($client, $slug)) {
                continue;
            }
            $out[$slug] = $info;
        }

        return $out;
    }
}
