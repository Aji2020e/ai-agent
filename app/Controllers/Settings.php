<?php

namespace App\Controllers;

use App\Libraries\AiClient;
use App\Models\SettingModel;

/**
 * Pengaturan AI (admin only): pilih provider (Ollama / API / OpenCode)
 * tanpa edit .env. API key & password tersimpan terenkripsi.
 */
class Settings extends BaseController
{
    public function index()
    {
        $settings = new SettingModel();

        // --- Kunci disimpan PER BASE_URL ---
        // Setiap provider (OpenAI, Groq, DeepSeek, OpenRouter) punya format key berbeda.
        // Kami simpan di setting `openai_key_map` berbentuk:
        //   {
        //     "https://api.openai.com":          ["sk-key1", "sk-key2"],
        //     "https://openrouter.ai/api":       ["sk-or-key1"],
        //     "https://api.groq.com/openai":     ["gsk_key1"]
        //   }
        // View hanya menampilkan kunci untuk Base URL yang sedang aktif saja.
        $baseGlobalRaw = rtrim(
            trim((string) $settings->getGlobal('openai_base', 'https://api.openai.com')) ?: 'https://api.openai.com',
            '/'
        );
        $baseGlobal = self::normalizeOpenAiBase($baseGlobalRaw) ?: $baseGlobalRaw;
        if ($baseGlobal !== $baseGlobalRaw) {
            $settings->setGlobal('openai_base', $baseGlobal);
        }

        $keyMapRaw      = $settings->getSecretMap('openai_key_map');
        $keyMap         = self::normalizeOpenAiKeyMap($keyMapRaw);
        if ($keyMap !== $keyMapRaw) {
            $settings->setSecretMap('openai_key_map', $keyMap);
        }
        $currentKeys    = $keyMap[$baseGlobal] ?? [];

        // Migrasi otomatis dari struktur lama (flat list / single key) → struktur per-base-url
        if ($currentKeys === [] && ! isset($keyMap[$baseGlobal])) {
            $legacyList = $settings->getSecretList('openai_keys');
            if (! empty($legacyList)) {
                $keyMap[$baseGlobal] = $legacyList;
                $settings->setSecretMap('openai_key_map', $keyMap);
                $currentKeys         = $legacyList;
            } else {
                $oldSingle = $settings->getSecret('openai_key', '');
                if ($oldSingle !== '') {
                    $keyMap[$baseGlobal] = [$oldSingle];
                    $settings->setSecretMap('openai_key_map', $keyMap);
                    $currentKeys        = [$oldSingle];
                }
            }
        }

        $allKeyRows = [];
        foreach ($keyMap as $url => $keys) {
            $u = rtrim((string) $url, '/');
            if ($u === '' || ! is_array($keys)) {
                continue;
            }
            foreach ($keys as $k) {
                $kk = trim((string) $k);
                if ($kk === '') {
                    continue;
                }
                $allKeyRows[] = ['key' => $kk, 'base' => $u];
            }
        }

        return view('settings/index', [
            'title'              => 'Pengaturan AI',
            'ai_provider'        => $settings->getGlobal('ai_provider', 'ollama') ?: 'ollama',
            'ollama_url'         => $settings->getGlobal('ollama_url', env('app.ollamaUrl', 'http://localhost:11434')),
            'ollama_model'       => $settings->getGlobal('ollama_model', env('app.ollamaModel', 'qwen2.5-coder:32b')),
            'openai_base'        => $baseGlobal,
            'openai_model'       => $settings->getGlobal('openai_model', 'gpt-4o-mini'),
            'openai_keys'        => $allKeyRows,
            'has_openai_key'     => $allKeyRows !== [],
            'openai_key_map_all' => $keyMap,      // Semua provider yang pernah diset (untuk info UI)
            'opencode_url'       => $settings->getGlobal('opencode_url', 'http://127.0.0.1:4096'),
            'opencode_user'      => $settings->getGlobal('opencode_user', ''),
            'opencode_model'     => $settings->getGlobal('opencode_model', ''),
            'has_oc_pass'        => $settings->getSecret('opencode_pass', '') !== '',
            'has_oc_key'         => $settings->getSecret('opencode_key', '') !== '',
            'search_provider'    => $settings->getGlobal('search_provider', 'off') ?: 'off',
            'search_max'         => $settings->getGlobal('search_max', '5'),
            'has_search_key'     => $settings->getSecret('search_key', '') !== '',

            // ---- Tuning kualitas & kecepatan jawaban ----
            'ai_temperature'           => $settings->getGlobal('ai_temperature', '0.2'),
            'ai_top_p'                 => $settings->getGlobal('ai_top_p', '0.9'),
            'ai_max_tokens'            => $settings->getGlobal('ai_max_tokens', '1024'),
            'ai_max_context_tokens'    => $settings->getGlobal('ai_max_context_tokens', '6000'),
            'ai_history_limit'         => $settings->getGlobal('ai_history_limit', '40'),
            'ai_num_ctx'               => $settings->getGlobal('ai_num_ctx', '8192'),
            'ai_keep_alive'            => $settings->getGlobal('ai_keep_alive', '30m'),
            'from_env'                 => $settings->getGlobal('ollama_url') === null && $settings->getGlobal('ai_provider') === null,
        ]);
    }

    /** Tampilkan daftar provider berdasarkan prefix key API. Dipakai via AJAX. */
    public function keyProviders()
    {
        $settings = new SettingModel();
        $provider = trim((string) $this->request->getPost('provider'));

        if ($provider !== 'openai') {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Invalid provider.',
                'csrf'    => csrf_hash(),
            ]);
        }

        $baseUrl = rtrim(trim((string) $this->request->getPost('base_url'))
            ?: $settings->getGlobal('openai_base', 'https://api.openai.com'), '/');
        $baseUrl = self::normalizeOpenAiBase($baseUrl) ?: $baseUrl;

        $keyMap = self::normalizeOpenAiKeyMap($settings->getSecretMap('openai_key_map'));
        $bucket = $keyMap[$baseUrl] ?? [];

        $providers = [];
        foreach ($bucket as $k) {
            $providers[] = [
                'prefix' => substr($k, 0, 8),
                'name'   => self::detectProvider($k),
            ];
        }

        return $this->response->setJSON([
            'success' => true,
            'keys'    => $providers,
            'csrf'    => csrf_hash(),
        ]);
    }

    public function update()
    {
        $provider = $this->request->getPost('ai_provider');

        if (! in_array($provider, ['ollama', 'openai', 'opencode'], true)) {
            return redirect()->back()->with('error', 'Provider tidak dikenal.');
        }

        $settings = new SettingModel();
        $settings->setGlobal('ai_provider', $provider);

        // Pencarian web (independen dari provider AI)
        $sp = $this->request->getPost('search_provider');
        if (in_array($sp, ['off', 'tavily', 'ddg'], true)) {
            $settings->setGlobal('search_provider', $sp);
            $settings->setGlobal('search_max', (string) max(1, min(8, (int) $this->request->getPost('search_max'))));
            $skey = trim((string) $this->request->getPost('search_key'));
            if ($skey !== '') {
                $settings->setSecret('search_key', $skey);
            }
        }

        if ($provider === 'ollama') {
            if (! $this->validate(['ollama_url' => 'required|valid_url|max_length[255]', 'ollama_model' => 'required|max_length[100]'])) {
                return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            }
            $settings->setGlobal('ollama_url', rtrim(trim($this->request->getPost('ollama_url')), '/'));
            $settings->setGlobal('ollama_model', trim($this->request->getPost('ollama_model')));
        } elseif ($provider === 'openai') {
            if (! $this->validate([
                'openai_base'   => 'required|valid_url|max_length[255]',
                'openai_model'  => 'required|max_length[100]',
            ])) {
                return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            }

            // Base URL & model disimpan sesuai biasa
            $baseRaw   = rtrim(trim((string) $this->request->getPost('openai_base')), '/');
            $base      = self::normalizeOpenAiBase($baseRaw) ?: $baseRaw;
            $settings->setGlobal('openai_base', $base);
            $settings->setGlobal('openai_model', trim((string) $this->request->getPost('openai_model')));

            // Kunci disimpan PER BASE_URL — tiap provider punya bucket kunci sendiri
            $keys      = $this->request->getPost('openai_keys');
            $keyBases  = $this->request->getPost('openai_key_bases');
            $keyMap    = [];
            if (is_array($keys)) {
                $grouped = [];
                foreach ($keys as $i => $k) {
                    $k = trim((string) $k);
                    if ($k === '') {
                        continue;
                    }

                    $rowBaseRaw = is_array($keyBases) ? trim((string) ($keyBases[$i] ?? '')) : '';
                    $rowBase = self::normalizeOpenAiBase($rowBaseRaw) ?: rtrim($rowBaseRaw, '/');
                    if ($rowBase === '' || filter_var($rowBase, FILTER_VALIDATE_URL) === false) {
                        $rowBase = $base;
                    }

                    $grouped[$rowBase]   = $grouped[$rowBase] ?? [];
                    $grouped[$rowBase][] = $k;
                }

                // Simpan hasil pairing key->endpoint per baris.
                foreach ($grouped as $bucketBase => $bucketKeys) {
                    $norm = array_values(array_unique(array_filter(array_map(static fn ($v) => trim((string) $v), $bucketKeys), static fn ($v) => $v !== '')));
                    if ($norm !== []) {
                        $keyMap[$bucketBase] = $norm;
                    }
                }
            }
            $settings->setSecretMap('openai_key_map', self::normalizeOpenAiKeyMap($keyMap));
        } else {
            if (! $this->validate([
                'opencode_url'   => 'required|valid_url|max_length[255]',
                'opencode_model' => 'permit_empty|max_length[150]',
            ])) {
                return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            }
            $settings->setGlobal('opencode_url', rtrim(trim($this->request->getPost('opencode_url')), '/'));
            $settings->setGlobal('opencode_user', trim((string) $this->request->getPost('opencode_user')));
            $settings->setGlobal('opencode_model', trim((string) $this->request->getPost('opencode_model')));
            $pass = (string) $this->request->getPost('opencode_pass');
            if ($pass !== '') {
                $settings->setSecret('opencode_pass', $pass);
            }
            $ockey = trim((string) $this->request->getPost('opencode_key'));
            if ($ockey !== '') {
                $settings->setSecret('opencode_key', $ockey);
            }
        }

        $this->saveTuning($settings);

        return redirect()->back()->with('success', 'Pengaturan AI berhasil disimpan.');
    }

    /** Hapus satu API key dari bucket Base URL tertentu. Dipakai via AJAX. */
    public function removeKey()
    {
        // Pastikan CSRF guard tidak menghalangi
        helper('csrf');

        $settings = new SettingModel();
        $provider = trim((string) $this->request->getPost('provider'));
        $prefix   = trim((string) $this->request->getPost('key_prefix')); // 8 char prefix
        $fullKey  = trim((string) $this->request->getPost('api_key'));

        // Jika tidak ada base_url di request, pakai yang tersimpan
        $postedBase = trim((string) $this->request->getPost('base_url'));
        if ($postedBase === '') {
            $baseUrlRaw = rtrim(
                trim((string) $settings->getGlobal('openai_base', 'https://api.openai.com')) ?: 'https://api.openai.com',
                '/'
            );
        } else {
            $baseUrlRaw = rtrim($postedBase, '/');
        }
        $baseUrl = self::normalizeOpenAiBase($baseUrlRaw) ?: $baseUrlRaw;

        if ($provider !== 'openai' || ($prefix === '' && $fullKey === '')) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Param tidak valid.',
                'csrf'    => csrf_hash(),
            ]);
        }

        // Ambil semua key map
        $keyMap = self::normalizeOpenAiKeyMap($settings->getSecretMap('openai_key_map'));
        $bucket = $keyMap[$baseUrl] ?? [];

        if (empty($bucket)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Tidak ada key tersimpan untuk Base URL: ' . parse_url($baseUrl, PHP_URL_HOST),
                'csrf'    => csrf_hash(),
            ]);
        }

        $before = count($bucket);

        if ($fullKey !== '') {
            $bucket = array_values(array_filter($bucket, static fn ($k) => trim((string) $k) !== $fullKey));
        } else {
            $bucket = array_values(array_filter($bucket, static fn ($k) => ! str_starts_with((string) $k, $prefix)));
        }

        $found = count($bucket) < $before;

        if (! $found) {
            // Tampilkan semua prefix yang ada agar admin tahu mana yang sebenarnya ada
            $existingPrefixes = [];
            foreach ($bucket as $k) {
                $existingPrefixes[] = substr($k, 0, 8);
            }
            $hint = ! empty($existingPrefixes)
                ? ' Key yang ada: ' . implode(', ', $existingPrefixes)
                : ' Bucket kosong.';

            return $this->response->setJSON([
                'success' => false,
                'message' => 'Key dengan prefix "' . substr($prefix, 0, 8) . '" tidak ditemukan di bucket ' . parse_url($baseUrl, PHP_URL_HOST) . '.' . $hint,
                'csrf'    => csrf_hash(),
            ]);
        }

        // Normalisasi & simpan kembali
        if ($bucket === []) {
            unset($keyMap[$baseUrl]);
        } else {
            $keyMap[$baseUrl] = array_values(array_unique(array_filter($bucket, static fn ($v) => trim((string) $v) !== '')));
        }
        $settings->setSecretMap('openai_key_map', self::normalizeOpenAiKeyMap($keyMap));

        $remaining = isset($keyMap[$baseUrl]) ? count($keyMap[$baseUrl]) : 0;

        return $this->response->setJSON([
            'success'      => true,
            'message'      => 'API key berhasil dihapus.',
            'remaining'    => $remaining,
            'csrf'         => csrf_hash(),
        ]);
    }

    /**
     * Normalisasi base URL OpenAI-compatible untuk penyimpanan key map.
     * Contoh: https://api.groq.com/openai/v1 -> https://api.groq.com/openai
     */
    private static function normalizeOpenAiBase(string $url): string
    {
        $url = rtrim(trim($url), '/');
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host   = strtolower((string) $parts['host']);
        $port   = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path   = isset($parts['path']) ? rtrim((string) $parts['path'], '/') : '';

        // Satukan bucket /v1, /v2, dst dengan endpoint dasarnya.
        if ($path !== '' && preg_match('#^(.*)/v\d+$#', $path, $m)) {
            $path = rtrim((string) $m[1], '/');
        }

        return $scheme . '://' . $host . $port . ($path !== '' ? $path : '');
    }

    /**
     * Normalisasi seluruh map key berdasarkan canonical base URL.
     * Menggabungkan bucket setara lalu menghapus key duplikat.
     *
     * @param array<string, array<int, string>> $map
     * @return array<string, array<int, string>>
     */
    private static function normalizeOpenAiKeyMap(array $map): array
    {
        $out = [];
        foreach ($map as $url => $keys) {
            $base = self::normalizeOpenAiBase((string) $url);
            if ($base === '') {
                continue;
            }

            $bucket = [];
            foreach ((array) $keys as $k) {
                $kk = trim((string) $k);
                if ($kk !== '') {
                    $bucket[] = $kk;
                }
            }

            if ($bucket === []) {
                continue;
            }

            $out[$base] = isset($out[$base])
                ? array_values(array_unique(array_merge($out[$base], $bucket)))
                : array_values(array_unique($bucket));
        }

        return $out;
    }

    /** Deteksi jenis provider berdasarkan prefix key API. */
    public static function detectProvider(string $key): string
    {
        $key = strtoupper(trim($key));
        if (str_starts_with($key, 'GSK_') || str_starts_with($key, 'GSK-')) {
            return 'GROQ';
        }
        if (str_starts_with($key, 'SK-OR-')) {
            return 'OPENROUTER';
        }
        if (str_starts_with($key, 'SK-ISD-') || str_starts_with($key, 'DEEPSEEK')) {
            return 'DEEPSEEK';
        }
        if (str_starts_with($key, 'ANTHROPIC') || str_starts_with($key, 'HSK-')) {
            return 'ANTHROPIC';
        }
        if (str_starts_with($key, 'SK-') || str_starts_with($key, 'sk-')) {
            return 'OPENAI-COMPATIBLE';
        }

        return 'UNKNOWN';
    }

    /**
     * Simpan parameter tuning dengan pembatasan rentang.
     *
     * Nilai di luar rentang dikembalikan ke default, bukan ditolak — admin
     * tidak boleh mengunci dirinya sendiri dengan angka yang membuat
     * provider menolak request.
     */
    private function saveTuning(SettingModel $settings): void
    {
        $num = function (string $field, float $min, float $max, float $def): float {
            $v = $this->request->getPost($field);
            if ($v === null || trim((string) $v) === '' || ! is_numeric($v)) {
                return $def;
            }

            return max($min, min($max, (float) $v));
        };

        $int = function (string $field, int $min, int $max, int $def): int {
            $v = $this->request->getPost($field);
            if ($v === null || trim((string) $v) === '' || ! is_numeric($v)) {
                return $def;
            }

            return max($min, min($max, (int) $v));
        };

        // temperature rendah = jawaban faktual & konsisten (anti-halusinasi)
        $settings->setGlobal('ai_temperature', (string) $num('ai_temperature', 0.0, 2.0, 0.2));
        $settings->setGlobal('ai_top_p', (string) $num('ai_top_p', 0.1, 1.0, 0.9));
        $settings->setGlobal('ai_max_tokens', (string) $int('ai_max_tokens', 64, 8192, 1024));
        $settings->setGlobal('ai_max_context_tokens', (string) $int('ai_max_context_tokens', 500, 100000, 6000));
        $settings->setGlobal('ai_history_limit', (string) $int('ai_history_limit', 2, 200, 40));
        $settings->setGlobal('ai_num_ctx', (string) $int('ai_num_ctx', 2048, 131072, 8192));

        $keepAlive = trim((string) $this->request->getPost('ai_keep_alive'));
        $settings->setGlobal('ai_keep_alive', preg_match('/^\d+[smh]$/', $keepAlive) ? $keepAlive : '30m');
    }

    /** Tes koneksi provider + daftar model. Dipakai via AJAX. */
    public function test()
    {
        $settings = new SettingModel();
        $provider = $this->request->getPost('ai_provider') ?: $settings->getGlobal('ai_provider', 'ollama');

        try {
            $keyResults = [];
            
            if ($provider === 'openai') {
                // Ambil Base URL yang diuji (dari form atau settings)
                $baseRaw = rtrim(trim((string) $this->request->getPost('openai_base'))
                    ?: $settings->getGlobal('openai_base', 'https://api.openai.com'), '/');
                $base = self::normalizeOpenAiBase($baseRaw) ?: $baseRaw;

                // Kunci dari form request (prioritas, BERPASANGAN dengan endpoint
                // tiap baris) atau dari map settings yang memang sudah per base URL.
                // Tiap key dites ke endpointnya SENDIRI, bukan ke Base URL atas.
                $pairs = []; // [base => [keys...]]

                $testKeys  = $this->request->getPost('openai_keys');
                $testBases = $this->request->getPost('openai_key_bases');
                if (is_array($testKeys) && !empty($testKeys)) {
                    foreach ($testKeys as $i => $k) {
                        $k = trim((string) $k);
                        if ($k === '') {
                            continue;
                        }
                        $rowBase = self::normalizeOpenAiBase(trim((string) (is_array($testBases) ? ($testBases[$i] ?? '') : ''))) ?: $base;
                        $pairs[$rowBase][] = $k;
                    }
                }

                // Jika form tidak mengirim key, ambil dari map per base URL
                if (empty($pairs)) {
                    $keyMap = self::normalizeOpenAiKeyMap($settings->getSecretMap('openai_key_map'));
                    foreach ($keyMap as $bucketBase => $bucketKeys) {
                        if (! empty($bucketKeys)) {
                            $pairs[$bucketBase] = $bucketKeys;
                        }
                    }
                    if (empty($pairs)) {
                        // Fallback ke legacy flat list (diuji ke base atas)
                        $legacy = $settings->getSecretList('openai_keys');
                        if (! empty($legacy)) {
                            $pairs[$base] = $legacy;
                        } else {
                            $oldKey = $settings->getSecret('openai_key', '');
                            if ($oldKey !== '') {
                                $pairs[$base] = [$oldKey];
                            }
                        }
                    }
                }

                if (empty($pairs)) {
                    throw new \RuntimeException('Tidak ada API key untuk Base URL ' . parse_url($base, PHP_URL_HOST) . '. Tambahkan terlebih dahulu.');
                }

                $models = null;
                $firstSuccessKey = null;

                // Uji setiap key ke endpointnya masing-masing
                foreach ($pairs as $rowBase => $keysToTest) {
                    foreach ($keysToTest as $key) {
                        $keyPrefix = substr($key, 0, 8);
                        $dispPrefix = $keyPrefix . '...';
                        try {
                            $result = AiClient::listModels('openai', $rowBase, ['apiKey' => $key]);
                            $keyResults[$dispPrefix] = [
                                'success' => true,
                                'message' => '✓ ' . count($result['models'] ?? []) . ' model tersedia (' . parse_url($rowBase, PHP_URL_HOST) . ')'
                            ];

                            if ($models === null) {
                                $models = $result['models'] ?? [];
                                $firstSuccessKey = $key;
                            }
                        } catch (\RuntimeException $e) {
                            $keyResults[$dispPrefix] = [
                                'success' => false,
                                'message' => '✗ ' . $e->getMessage()
                            ];
                        }
                    }
                }

                if ($models === null) {
                    throw new \RuntimeException('Semua API key gagal. Coba periksa kembali key dan Base URL.');
                }
                
                $result = ['models' => $models];
            } 
            elseif ($provider === 'opencode') {
                $base = rtrim(trim((string) $this->request->getPost('opencode_url'))
                    ?: $settings->getGlobal('opencode_url', 'http://127.0.0.1:4096'), '/');
                $result = AiClient::listModels('opencode', $base, [
                    'username' => trim((string) $this->request->getPost('opencode_user'))
                        ?: $settings->getGlobal('opencode_user', ''),
                    'password' => (string) $this->request->getPost('opencode_pass')
                        ?: $settings->getSecret('opencode_pass', ''),
                    'apiKey' => trim((string) $this->request->getPost('opencode_key'))
                        ?: $settings->getSecret('opencode_key', ''),
                ]);
            } 
            else {
                $base = rtrim(trim((string) $this->request->getPost('ollama_url'))
                    ?: $settings->getGlobal('ollama_url', env('app.ollamaUrl', 'http://localhost:11434')), '/');
                $result = AiClient::listModels('ollama', $base);
            }

            $response = [
                'success' => true,
                'models'  => $result['models'] ?? [],
                'csrf'    => csrf_hash(),
            ];
            
            // Tambahkan hasil per-key bila ada
            if (!empty($keyResults)) {
                $response['key_results'] = $keyResults;
            }
            
            return $this->response->setJSON($response);
        } catch (\RuntimeException $e) {
            return $this->response->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
                'csrf'    => csrf_hash(),
            ]);
        }
    }

    /**
     * Tes SEMUA provider sekaligus (Ollama + OpenAI per-base + OpenCode)
     * memakai kredensial TERSIMPAN. Dipakai via AJAX.
     */
    public function testAll()
    {
        $settings = new SettingModel();
        $out = ['success' => true, 'csrf' => csrf_hash(), 'results' => []];

        // Ollama
        try {
            $base = rtrim($settings->getGlobal('ollama_url', env('app.ollamaUrl', 'http://localhost:11434')) ?: 'http://localhost:11434', '/');
            $r = AiClient::listModels('ollama', $base);
            $out['results']['ollama'] = ['success' => true, 'models' => $r['models'] ?? [], 'base' => $base];
        } catch (\Throwable $e) {
            $out['results']['ollama'] = ['success' => false, 'error' => $e->getMessage()];
        }

        // OpenAI: tiap bucket base URL dites dengan key-nya sendiri
        try {
            $keyMap = self::normalizeOpenAiKeyMap($settings->getSecretMap('openai_key_map'));
            if (empty($keyMap)) {
                $legacy = $settings->getSecretList('openai_keys');
                if (! empty($legacy)) {
                    $keyMap = [rtrim($settings->getGlobal('openai_base', 'https://api.openai.com'), '/') => $legacy];
                } else {
                    $oldKey = $settings->getSecret('openai_key', '');
                    if ($oldKey !== '') {
                        $keyMap = [rtrim($settings->getGlobal('openai_base', 'https://api.openai.com'), '/') => [$oldKey]];
                    }
                }
            }
            if (empty($keyMap)) {
                $out['results']['openai'] = ['success' => false, 'error' => 'Belum ada API key tersimpan.'];
            } else {
                $buckets = [];
                foreach ($keyMap as $bucketBase => $bucketKeys) {
                    $row = ['base' => $bucketBase, 'keys' => [], 'models' => []];
                    foreach ((array) $bucketKeys as $key) {
                        try {
                            $r = AiClient::listModels('openai', $bucketBase, ['apiKey' => $key]);
                            $row['keys'][substr($key, 0, 8) . '...'] = [
                                'success' => true,
                                'message' => '✓ ' . count($r['models'] ?? []) . ' model tersedia',
                            ];
                            if (empty($row['models'])) {
                                $row['models'] = $r['models'] ?? [];
                            }
                        } catch (\Throwable $e) {
                            $row['keys'][substr($key, 0, 8) . '...'] = [
                                'success' => false,
                                'message' => '✗ ' . $e->getMessage(),
                            ];
                        }
                    }
                    $buckets[] = $row;
                }
                $out['results']['openai'] = ['success' => true, 'buckets' => $buckets];
            }
        } catch (\Throwable $e) {
            $out['results']['openai'] = ['success' => false, 'error' => $e->getMessage()];
        }

        // OpenCode (saved creds; API key prioritas)
        try {
            $base = rtrim($settings->getGlobal('opencode_url', 'http://127.0.0.1:4096') ?: 'http://127.0.0.1:4096', '/');
            $r = AiClient::listModels('opencode', $base, [
                'username' => $settings->getGlobal('opencode_user', ''),
                'password' => $settings->getSecret('opencode_pass', ''),
                'apiKey'   => $settings->getSecret('opencode_key', ''),
            ]);
            $out['results']['opencode'] = ['success' => true, 'models' => $r['models'] ?? [], 'base' => $base];
        } catch (\Throwable $e) {
            $out['results']['opencode'] = ['success' => false, 'error' => $e->getMessage()];
        }

        return $this->response->setJSON($out);
    }
}
