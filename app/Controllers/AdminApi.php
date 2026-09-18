<?php

namespace App\Controllers;

use App\Libraries\AcademicDb;
use App\Models\ApiClientModel;
use App\Models\ApiDocModel;
use App\Models\ApiLogModel;
use App\Models\SettingModel;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Admin UI: kunci API, koneksi DB akademik (read-only), dokumen panduan, log.
 */
class AdminApi extends BaseController
{
    public function index()
    {
        $clients = (new ApiClientModel())->orderBy('id', 'DESC')->findAll();
        $docs    = (new ApiDocModel())->orderBy('module', 'ASC')->orderBy('id', 'ASC')->findAll();
        $dict    = (new \App\Models\ApiDictionaryModel())->orderBy('module', 'ASC')->orderBy('id', 'ASC')->findAll();
        $logs    = (new ApiLogModel())->recent(30);
        $akad    = AcademicDb::config(true);

        // SENGAJA tidak konek saat render — status dicek async via tombol Tes
        // agar halaman tetap ringan walau DB akademik tak terjangkau/firewall.
        $akadStatus = ['ok' => null];

        return view('admin/api', [
            'title'      => 'API & Integrasi',
            'clients'    => $clients,
            'docs'       => $docs,
            'dict'       => $dict,
            'logs'       => $logs,
            'akad'       => $akad,
            'akadStatus' => $akadStatus,
            'newKey'     => session()->getFlashdata('newKey'),
            'modules'    => ['mahasiswa', 'dosen', 'staff'],
            'docModules' => ApiDocModel::$modules,
            'skills'     => [
                'academic'        => 'Data Akademik',
                'student_profile' => 'Profil Mahasiswa',
                'staff_profile'   => 'Profil Staff',
                'writing'         => 'Asisten Skripsi',
                'coding'          => 'Coding Assistant',
                'general'         => 'Obrolan Umum',
            ],
        ]);
    }

    public function createClient()
    {
        $name = trim((string) $this->request->getPost('name'));

        if ($name === '' || strlen($name) > 100) {
            return redirect()->back()->with('error', 'Nama klien wajib (maks 100 karakter).');
        }

        $mods = $this->request->getPost('modules') ?? [];
        $mods = array_values(array_intersect((array) $mods, ['mahasiswa', 'dosen', 'staff']));
        $scope = $mods === [] ? '*' : implode(',', $mods);

        $skills = $this->request->getPost('skills') ?? [];
        $allowedSkills = $this->normalizeSkills($skills);
        $skillsScope = $allowedSkills === [] ? '*' : implode(',', $allowedSkills);

        $days = (int) $this->request->getPost('expiry_days');
        $ips  = trim((string) $this->request->getPost('ips'));
        $cm   = self::cleanModel((string) $this->request->getPost('model'));

        $model = new ApiClientModel();
        $made  = $model->createClient($name, $scope, $skillsScope);
        $model->update($made['id'], [
            'expires_at'   => $days > 0 ? date('Y-m-d H:i:s', time() + $days * 86400) : null,
            'ip_allowlist' => $ips !== '' ? preg_replace('/[^\d\.\s,\/]/', '', $ips) : null,
            'model'        => $cm !== '' ? $cm : null,
        ]);

        return redirect()->to(site_url('admin/api'))
            ->with('success', 'Klien dibuat. Salin key sekarang — hanya tampil sekali!')
            ->with('newKey', $made['key']);
    }

    /** Aktifkan HMAC untuk klien (secret tampil sekali). */
    public function hmacClient(int $id)
    {
        $m = new ApiClientModel();
        $c = $m->find($id);

        if (! $c) {
            return redirect()->back()->with('error', 'Klien tidak ditemukan.');
        }

        $secret = bin2hex(random_bytes(32));
        $m->update($id, ['hmac_secret' => $secret, 'require_hmac' => $c['require_hmac'] ? 0 : 1]);

        if ($c['require_hmac']) {
            return redirect()->back()->with('success', 'HMAC dimatikan untuk klien ini.');
        }

        return redirect()->to(site_url('admin/api'))
            ->with('success', 'HMAC diaktifkan. Salin secret — hanya tampil sekali!')
            ->with('newKey', $secret);
    }

    public function toggleClient(int $id)
    {
        $m = new ApiClientModel();
        $c = $m->find($id);

        if ($c) {
            $m->update($id, ['is_active' => $c['is_active'] ? 0 : 1]);
        }

        return redirect()->back()->with('success', 'Status klien diubah.');
    }

    /** Bersihkan daftar skill: array valid atau []. */
    private static function normalizeSkills($skills): array
    {
        $valid = ['academic', 'student_profile', 'staff_profile', 'writing', 'coding', 'general'];

        if (! is_array($skills)) {
            return [];
        }

        return array_values(array_intersect(array_map('strval', $skills), $valid));
    }

    /** Bersihkan nama model: kosong = ikut default global. */
    private static function cleanModel(string $raw): string
    {
        $m = trim($raw);
        if ($m === '' || strlen($m) > 100 || ! preg_match('/^[\w.\-\/:]+$/', $m)) {
            return '';
        }

        return $m;
    }

    /** Simpan model khusus klien (kosong = ikut default global). */
    public function modelClient(int $id)
    {
        $m = new ApiClientModel();
        $c = $m->find($id);

        if (! $c) {
            return redirect()->back()->with('error', 'Klien tidak ditemukan.');
        }

        $cm = self::cleanModel((string) $this->request->getPost('model'));
        $m->update($id, ['model' => $cm !== '' ? $cm : null]);

        return redirect()->back()->with('success', $cm !== '' ? 'Model klien diset ke ' . $cm . '.' : 'Model klien dikembalikan ke default global.');
    }

    public function deleteClient(int $id)
    {
        (new ApiClientModel())->delete($id);

        return redirect()->back()->with('success', 'Klien dihapus (log ikut terhapus).');
    }

    /** Daftar model provider aktif untuk combobox (JSON). */
    public function models()
    {
        try {
            [$provider, $url, $def, $opt] = \App\Libraries\AiClient::currentConfig();
            $res = \App\Libraries\AiClient::listModels($provider, $url, $opt);

            return $this->response->setJSON([
                'success'  => true,
                'provider' => $provider,
                'default'  => $def,
                'models'   => array_values($res['models'] ?? []),
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success' => false, 'error' => $e->getMessage(), 'models' => []]);
        }
    }

    public function saveAkademik()
    {
        $rules = [
            'akad_host'   => 'required|max_length[100]',
            'akad_port'   => 'required|integer|greater_than[0]|less_than[65536]',
            'akad_db'     => 'required|max_length[100]',
            'akad_user'   => 'required|max_length[100]',
            'akad_driver' => 'required|in_list[MySQLi,SQLSRV]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $s = new SettingModel();
        $s->setGlobal('akad_host', trim($this->request->getPost('akad_host')));
        $s->setGlobal('akad_port', trim($this->request->getPost('akad_port')));
        $s->setGlobal('akad_db', trim($this->request->getPost('akad_db')));
        $s->setGlobal('akad_user', trim($this->request->getPost('akad_user')));
        $s->setGlobal('akad_driver', $this->request->getPost('akad_driver'));
        $pass = (string) $this->request->getPost('akad_pass');
        if ($pass !== '') {
            $s->setSecret('akad_pass', $pass);
        }
        AcademicDb::reset();

        return redirect()->back()->with('success', 'Koneksi akademik disimpan.');
    }

    public function testAkademik()
    {
        AcademicDb::reset();

        try {
            $info = AcademicDb::test();

            return $this->response->setJSON([
                'success' => true,
                'info'    => $info,
                'csrf'    => csrf_hash(),
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
                'csrf'    => csrf_hash(),
            ]);
        }
    }

    public function saveDoc()
    {
        $rules = [
            'module'  => 'required|in_list[umum,mahasiswa,dosen,staff]',
            'title'   => 'required|max_length[255]',
            'content' => 'required',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $id = (int) $this->request->getPost('id');
        $m  = new ApiDocModel();
        $data = [
            'module'    => $this->request->getPost('module'),
            'title'     => trim($this->request->getPost('title')),
            'content'   => $this->request->getPost('content'),
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];

        if ($id > 0) {
            $m->update($id, $data);
        } else {
            $m->insert($data);
        }

        return redirect()->back()->with('success', 'Dokumen panduan disimpan.');
    }

    public function deleteDoc(int $id)
    {
        (new ApiDocModel())->delete($id);

        return redirect()->back()->with('success', 'Dokumen dihapus.');
    }

    /** Upload file panduan akademik lalu simpan ke tabel api_docs. */
    public function uploadDoc()
    {
        $module = trim((string) $this->request->getPost('module'));
        if (! in_array($module, ApiDocModel::$modules, true)) {
            return redirect()->back()->withInput()->with('error', 'Modul dokumen tidak valid.');
        }

        $files = $this->request->getFileMultiple('guide_files');
        if (! is_array($files) || $files === []) {
            return redirect()->back()->withInput()->with('error', 'Pilih file panduan terlebih dahulu.');
        }

        $titleInput = trim((string) $this->request->getPost('title'));
        $isActive   = $this->request->getPost('is_active') ? 1 : 0;
        $allowed    = [
            'txt', 'md', 'markdown', 'csv', 'json', 'xml', 'html', 'htm', 'yml', 'yaml', 'ini', 'log',
            'docx', 'doc', 'rtf', 'pdf',
            'png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp', 'tif', 'tiff',
        ];

        $docModel = new ApiDocModel();
        $ok       = 0;
        $failed   = [];

        foreach ($files as $idx => $file) {
            if (! $file instanceof UploadedFile || $file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $name = (string) $file->getClientName();
            if (! $file->isValid()) {
                $failed[] = $name . ' (upload gagal: ' . $file->getError() . ')';
                continue;
            }
            if ($file->getSize() > 10 * 1024 * 1024) {
                $failed[] = $name . ' (melebihi 10 MB)';
                continue;
            }

            $ext = strtolower($file->getExtension());
            if (! in_array($ext, $allowed, true)) {
                $failed[] = $name . ' (format tidak didukung)';
                continue;
            }

            try {
                $content = $this->extractGuideText($file, $ext);
            } catch (\RuntimeException $e) {
                $failed[] = $name . ' (' . $e->getMessage() . ')';
                continue;
            }

            if ($content === '') {
                $failed[] = $name . ' (isi kosong/tidak terbaca)';
                continue;
            }

            $title = $titleInput;
            if (count($files) > 1 || $title === '') {
                $title = pathinfo($name, PATHINFO_FILENAME);
            }
            if ($title === '' || mb_strlen($title) > 255) {
                $failed[] = $name . ' (judul tidak valid)';
                continue;
            }

            $docModel->insert([
                'module'    => $module,
                'title'     => $title,
                'content'   => $content,
                'is_active' => $isActive,
            ]);
            $ok++;
        }

        if ($ok === 0) {
            return redirect()->back()->withInput()->with('error', 'Semua file gagal diproses: ' . implode('; ', array_slice($failed, 0, 3)));
        }

        $msg = 'Berhasil upload ' . $ok . ' dokumen.';
        if ($failed !== []) {
            $msg .= ' Gagal: ' . count($failed) . ' file (' . implode('; ', array_slice($failed, 0, 2)) . (count($failed) > 2 ? '; ...' : '') . ').';
        }

        return redirect()->back()->with('success', $msg);
    }

    public function saveDict()
    {
        $rules = [
            'module' => 'required|in_list[umum,mahasiswa,dosen,staff]',
            'topik'  => 'required|max_length[255]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $id = (int) $this->request->getPost('id');
        $m  = new \App\Models\ApiDictionaryModel();
        $data = [
            'module'    => $this->request->getPost('module'),
            'topik'     => trim($this->request->getPost('topik')),
            'tabel'     => trim((string) $this->request->getPost('tabel')),
            'kolom'     => $this->request->getPost('kolom'),
            'deskripsi' => $this->request->getPost('deskripsi'),
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];

        if ($id > 0) {
            $m->update($id, $data);
        } else {
            $m->insert($data);
        }

        return redirect()->back()->with('success', 'Kamus data disimpan.');
    }

    public function deleteDict(int $id)
    {
        (new \App\Models\ApiDictionaryModel())->delete($id);

        return redirect()->back()->with('success', 'Entri kamus dihapus.');
    }

    // ---------- Modul dinamis (wizard) ----------

    public function modules()
    {
        $mods = (new \App\Models\ApiModuleModel())->orderBy('id', 'ASC')->findAll();
        $roleMap = \App\Libraries\Academic\ModuleRegistry::roleMap();

        // Balik: slug => [roles] untuk editor per-modul
        $modRoles = [];
        foreach ($roleMap as $role => $slugs) {
            foreach ((array) $slugs as $s) {
                $modRoles[$s][] = $role;
            }
        }

        return view('admin/modules', [
            'title'      => 'Modul Asisten',
            'modules'    => $mods,
            'bagianFull' => implode(',', \App\Libraries\Academic\ModuleRegistry::fullAccessBagians()),
            'roleMap'    => $roleMap,
            'modRoles'   => $modRoles,
            'knownRoles' => \App\Libraries\Academic\ModuleRegistry::knownRoles(),
            'newKey'     => session()->getFlashdata('newKey'),
            'newSlug'    => session()->getFlashdata('newSlug'),
        ]);
    }

    public function columns()
    {
        $table = trim((string) $this->request->getGet('table'));

        try {
            $cols = \App\Libraries\AcademicDb::columns($table);

            return $this->response->setJSON(['success' => true, 'columns' => $cols]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function saveRoleMap()
    {
        $json = trim((string) $this->request->getPost('role_modules'));
        $data = json_decode($json, true);

        if (! is_array($data)) {
            return redirect()->back()->with('error', 'JSON peta peran tidak valid.');
        }

        (new SettingModel())->setGlobal('role_modules', json_encode($data, JSON_UNESCAPED_UNICODE));

        return redirect()->back()->with('success', 'Peta peran disimpan.');
    }

    /** Bagian staff dengan akses penuh semua modul. */
    public function saveBagian()
    {
        $raw = [];
        foreach (preg_split('/[\s,]+/', (string) $this->request->getPost('bagian_full')) as $b) {
            $b = trim($b);
            if ($b !== '' && preg_match('/^[0-9]+$/', $b)) {
                $raw[] = $b;
            }
        }

        (new SettingModel())->setGlobal('bagian_full', implode(',', array_unique($raw)));

        return redirect()->back()->with('success', 'Daftar bagian akses penuh disimpan.');
    }

    /** Simpan "modul ini untuk role apa" dari editor per-modul. */
    public function saveModuleRoles()
    {
        $posted  = (array) ($this->request->getPost('modroles') ?? []);
        $custom  = strtolower(trim((string) $this->request->getPost('new_role')));
        $map     = [];

        foreach ($posted as $slug => $roles) {
            $slug = strtolower(trim((string) $slug));
            if (! preg_match('/^[a-z0-9_]+$/', $slug)) {
                continue;
            }
            foreach ((array) $roles as $role) {
                $role = strtolower(trim((string) $role));
                if ($role === '' || (! preg_match('/^[a-z0-9_]+$/', $role) && $role !== $custom)) {
                    continue;
                }
                $map[$role][] = $slug;
            }
        }

        if ($custom !== '' && preg_match('/^[a-z0-9_]+$/', $custom) && empty($map[$custom])) {
            $map[$custom] = $map[$custom] ?? [];
        }

        foreach ($map as $role => $slugs) {
            $map[$role] = array_values(array_unique($slugs));
        }

        (new SettingModel())->setGlobal('role_modules', json_encode($map, JSON_UNESCAPED_UNICODE));

        return redirect()->back()->with('success', 'Role tiap modul disimpan.');
    }

    public function saveModule()
    {
        $rules = [
            'name'     => 'required|max_length[100]',
            'slug'     => 'required|regex_match[/^[a-z0-9_]+$/]|max_length[50]|is_unique[api_modules.slug]',
            'id_param' => 'required|regex_match[/^[a-z0-9_]+$/]|max_length[30]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $sources = $this->request->getPost('sources') ?? [];
        $sources = array_values(array_filter((array) $sources, static fn ($s) => trim((string) ($s['tabel'] ?? '')) !== ''));

        if ($sources === []) {
            return redirect()->back()->withInput()->with('error', 'Tambahkan minimal 1 sumber data (profil).');
        }

        $slug = $this->request->getPost('slug');
        $db   = \Config\Database::connect();
        $db->transStart();

        try {
            $profile = array_shift($sources);
            $lookup  = [];
            foreach (preg_split('/[\s,]+/', (string) $this->request->getPost('lookup_tables')) as $t) {
                $t = trim($t);
                if ($t !== '' && preg_match('/^[A-Za-z0-9_]+$/', $t)) {
                    $lookup[] = $t;
                }
            }
            $profCols = $this->parseCols($profile['cols'] ?? '');
            $queryConfig = [
                'profile' => [
                    'table'  => trim($profile['tabel']),
                    'id_col' => trim($profile['id_col'] ?? '') ?: $this->request->getPost('id_param'),
                    'cols'   => $profCols,
                    'name_col' => $profCols[1] ?? null,
                ],
                'related'          => [],
                'lookup_tables'    => array_values(array_unique($lookup)),
                'profile_optional' => $this->request->getPost('profile_optional') ? true : false,
            ];

            foreach ($sources as $i => $s) {
                $queryConfig['related'][] = [
                    'key'    => trim($s['key'] ?? '') ?: ('rel' . ($i + 1)),
                    'table'  => trim($s['tabel']),
                    'id_col' => trim($s['id_col'] ?? '') ?: $this->request->getPost('id_param'),
                    'cols'   => $this->parseCols($s['cols'] ?? ''),
                    'limit'  => max(1, min(200, (int) ($s['limit'] ?? 50))),
                    'order'  => trim($s['order'] ?? ''),
                ];
            }

            $examples = array_values(array_filter(array_map('trim', explode("\n", (string) $this->request->getPost('examples')))));

            $modModel = new \App\Models\ApiModuleModel();
            $modModel->insert([
                'slug'         => $slug,
                'name'         => trim($this->request->getPost('name')),
                'description'  => trim((string) $this->request->getPost('description')),
                'id_param'     => $this->request->getPost('id_param'),
                'id_label'     => trim((string) $this->request->getPost('id_label')) ?: 'ID',
                'query_config' => json_encode($queryConfig, JSON_UNESCAPED_UNICODE),
                'examples'     => json_encode($examples, JSON_UNESCAPED_UNICODE),
                'is_active'    => 1,
            ]);

            // Kamus dari sumber
            $dict = new \App\Models\ApiDictionaryModel();
            $dict->insert([
                'module' => $slug, 'topik' => '*', 'tabel' => null, 'kolom' => null,
                'deskripsi' => 'ATURAN WAJIB: gunakan HANYA tabel/kolom pada kamus ini. Bila data tidak ada, katakan terus terang, jangan mengarang.',
                'is_active' => 1,
            ]);
            $all = array_merge([$profile], $sources);
            foreach ($all as $s) {
                $dict->insert([
                    'module'    => $slug,
                    'topik'     => trim($s['topik'] ?? '') ?: '-',
                    'tabel'     => trim($s['tabel']),
                    'kolom'     => trim($s['kolom'] ?? ''),
                    'deskripsi' => trim($s['keterangan'] ?? ''),
                    'is_active' => 1,
                ]);
            }

            // Panduan
            $docModel = new ApiDocModel();
            foreach ((array) ($this->request->getPost('docs') ?? []) as $d) {
                if (trim($d['title'] ?? '') === '' || trim($d['content'] ?? '') === '') {
                    continue;
                }
                $docModel->insert(['module' => $slug, 'title' => trim($d['title']), 'content' => $d['content'], 'is_active' => 1]);
            }

            // Klien + key otomatis
            $clientName = trim((string) $this->request->getPost('client_name')) ?: ('APP-' . strtoupper($slug));
            $made       = (new ApiClientModel())->createClient($clientName, $slug);

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Transaksi database gagal.');
            }
        } catch (\Throwable $e) {
            $db->transComplete();

            return redirect()->back()->withInput()->with('error', 'Gagal membuat modul: ' . $e->getMessage());
        }

        return redirect()->to(site_url('admin/api/modules'))
            ->with('success', "Modul '{$slug}' jadi! Endpoint: POST /api/assistant/{$slug}/analyze")
            ->with('newKey', $made['key'])
            ->with('newSlug', $slug);
    }

    public function deleteModule(int $id)
    {
        $m   = new \App\Models\ApiModuleModel();
        $row = $m->find($id);

        if ($row) {
            $db = \Config\Database::connect();
            $db->transStart();
            $db->table('api_dictionary')->where('module', $row['slug'])->delete();
            $db->table('api_docs')->where('module', $row['slug'])->delete();
            $m->delete($id);
            $db->transComplete();
        }

        return redirect()->back()->with('success', 'Modul + kamus + panduannya dihapus.');
    }

    /** @return string[] */
    private function parseCols(string|array $cols): array
    {
        if (is_array($cols)) {
            $list = $cols;
        } else {
            $list = preg_split('/[\s,]+/', $cols);
        }

        $out = [];
        foreach ($list as $c) {
            // Dukung format "KODE=kode MK" → ambil nama kolomnya saja
            $c = trim(explode('=', (string) $c, 2)[0]);
            if ($c !== '' && preg_match('/^[A-Za-z0-9_]+$/', $c)) {
                $out[] = $c;
            }
        }

        return array_values(array_unique($out));
    }

    /** Ekstrak teks dari file panduan yang di-upload. */
    private function extractGuideText(UploadedFile $file, string $ext): string
    {
        $tmp = $file->getTempName();
        if ($tmp === '' || ! is_file($tmp)) {
            throw new \RuntimeException('Berkas upload tidak ditemukan di server. Coba ulangi.');
        }

        if ($ext === 'pdf') {
            return $this->normalizeGuideText($this->extractPdfText($tmp));
        }

        if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp', 'tif', 'tiff'], true)) {
            return $this->normalizeGuideText($this->extractImageText($tmp));
        }

        if ($ext === 'doc') {
            return $this->normalizeGuideText($this->extractDocText($tmp));
        }

        if ($ext === 'docx') {
            if (! class_exists('ZipArchive')) {
                throw new \RuntimeException('Server belum mendukung pembacaan DOCX (ZipArchive tidak tersedia).');
            }

            $zip = new \ZipArchive();
            if ($zip->open($tmp) !== true) {
                throw new \RuntimeException('File DOCX tidak valid atau rusak.');
            }

            $xml = $zip->getFromName('word/document.xml') ?: '';
            $zip->close();

            if ($xml === '') {
                throw new \RuntimeException('Isi DOCX tidak ditemukan.');
            }

            $xml = str_replace(['</w:p>', '</w:tr>', '<w:tab/>'], ["\n", "\n", ' '], $xml);
            $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');

            return $this->normalizeGuideText($text);
        }

        $raw = @file_get_contents($tmp);
        if (! is_string($raw) || $raw === '') {
            throw new \RuntimeException('File tidak bisa dibaca.');
        }

        if ($ext === 'rtf') {
            $raw = preg_replace('/\\\\par[d]?\\s*/i', "\n", $raw) ?? $raw;
            $raw = preg_replace('/\\\\[a-z]+-?\d*\\s?/i', ' ', $raw) ?? $raw;
            $raw = str_replace(['{', '}'], ' ', $raw);
            $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return $this->normalizeGuideText($raw);
        }

        if (strpos($raw, "\0") !== false) {
            throw new \RuntimeException('File tampak biner. Simpan sebagai teks/DOCX terlebih dahulu.');
        }

        return $this->normalizeGuideText($raw);
    }

    private function normalizeGuideText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        $text = trim($text);

        $maxChars = 30000;
        if (mb_strlen($text) > $maxChars) {
            $text = mb_substr($text, 0, $maxChars) . "\n\n[Dokumen dipotong agar efisien token]";
        }

        return $text;
    }

    private function extractPdfText(string $tmp): string
    {
        if (class_exists('Smalot\\PdfParser\\Parser')) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf    = $parser->parseFile($tmp);
                $text   = $pdf->getText();
                if (trim($text) !== '') {
                    return $text;
                }
            } catch (\Throwable) {
            }
        }

        if ($this->commandExists('pdftotext')) {
            $cmd = 'pdftotext -layout -nopgbrk ' . escapeshellarg($tmp) . ' -';
            [$out, $code] = $this->runCommand($cmd);
            if ($code === 0 && trim($out) !== '') {
                return $out;
            }
        }

        throw new \RuntimeException('PDF belum bisa dibaca. Install pdftotext (poppler) atau paket smalot/pdfparser.');
    }

    private function extractImageText(string $tmp): string
    {
        if (! $this->commandExists('tesseract')) {
            throw new \RuntimeException('OCR gambar butuh tesseract di server.');
        }

        $cmd = 'tesseract ' . escapeshellarg($tmp) . ' stdout -l ind+eng --psm 6';
        [$out, $code] = $this->runCommand($cmd);
        if ($code !== 0 || trim($out) === '') {
            throw new \RuntimeException('OCR gagal membaca gambar.');
        }

        return $out;
    }

    private function extractDocText(string $tmp): string
    {
        if (! $this->commandExists('antiword')) {
            throw new \RuntimeException('Format DOC butuh antiword di server. Gunakan DOCX/PDF jika belum tersedia.');
        }

        $cmd = 'antiword ' . escapeshellarg($tmp);
        [$out, $code] = $this->runCommand($cmd);
        if ($code !== 0 || trim($out) === '') {
            throw new \RuntimeException('Gagal membaca file DOC.');
        }

        return $out;
    }

    private function commandExists(string $bin): bool
    {
        $check = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'where ' . $bin : 'command -v ' . $bin;
        [, $code] = $this->runCommand($check);

        return $code === 0;
    }

    /** @return array{0:string,1:int} */
    private function runCommand(string $cmd): array
    {
        $out = [];
        $ret = 1;
        @exec($cmd . ' 2>&1', $out, $ret);

        return [implode("\n", $out), (int) $ret];
    }
}
