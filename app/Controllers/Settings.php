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

        $openaiKeys = $settings->getSecretList('openai_keys');
        // backward compatibility: secret lama tetap dipakai bila daftar kosong
        if ($openaiKeys === []) {
            $old = $settings->getSecret('openai_key', '');
            if ($old !== '') {
                $openaiKeys = [$old];
            }
        }

        return view('settings/index', [
            'title'          => 'Pengaturan AI',
            'ai_provider'    => $settings->getGlobal('ai_provider', 'ollama') ?: 'ollama',
            'ollama_url'     => $settings->getGlobal('ollama_url', env('app.ollamaUrl', 'http://localhost:11434')),
            'ollama_model'   => $settings->getGlobal('ollama_model', env('app.ollamaModel', 'qwen2.5-coder:32b')),
            'openai_base'    => $settings->getGlobal('openai_base', 'https://api.openai.com'),
            'openai_model'   => $settings->getGlobal('openai_model', 'gpt-4o-mini'),
            'openai_keys'    => $openaiKeys,
            'has_openai_key' => $openaiKeys !== [],
            'opencode_url'   => $settings->getGlobal('opencode_url', 'http://127.0.0.1:4096'),
            'opencode_user'  => $settings->getGlobal('opencode_user', ''),
            'opencode_model' => $settings->getGlobal('opencode_model', ''),
            'has_oc_pass'    => $settings->getSecret('opencode_pass', '') !== '',
            'search_provider' => $settings->getGlobal('search_provider', 'off') ?: 'off',
            'search_max'     => $settings->getGlobal('search_max', '5'),
            'has_search_key' => $settings->getSecret('search_key', '') !== '',

            // ---- Tuning kualitas & kecepatan jawaban ----
            'ai_temperature'         => $settings->getGlobal('ai_temperature', '0.2'),
            'ai_top_p'               => $settings->getGlobal('ai_top_p', '0.9'),
            'ai_max_tokens'          => $settings->getGlobal('ai_max_tokens', '1024'),
            'ai_max_context_tokens'  => $settings->getGlobal('ai_max_context_tokens', '6000'),
            'ai_history_limit'       => $settings->getGlobal('ai_history_limit', '40'),
            'ai_num_ctx'             => $settings->getGlobal('ai_num_ctx', '8192'),
            'ai_keep_alive'          => $settings->getGlobal('ai_keep_alive', '30m'),
            'from_env'       => $settings->getGlobal('ollama_url') === null && $settings->getGlobal('ai_provider') === null,
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
            if (! $this->validate(['openai_base' => 'required|valid_url|max_length[255]', 'openai_model' => 'required|max_length[100]'])) {
                return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            }
            $settings->setGlobal('openai_base', rtrim(trim($this->request->getPost('openai_base')), '/'));
            $settings->setGlobal('openai_model', trim($this->request->getPost('openai_model')));

            $keys = $this->request->getPost('openai_keys');
            if (is_array($keys)) {
                $filtered = [];
                foreach ($keys as $k) {
                    $k = trim((string) $k);
                    if ($k !== '') {
                        $filtered[] = $k;
                    }
                }
                $settings->setSecretList('openai_keys', $filtered);
            }
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
        }

        $this->saveTuning($settings);

        return redirect()->back()->with('success', 'Pengaturan AI berhasil disimpan.');
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
                $base = rtrim(trim((string) $this->request->getPost('openai_base'))
                    ?: $settings->getGlobal('openai_base', 'https://api.openai.com'), '/');
                
                // Ambil key yang diuji dari request atau dari settings yang tersimpan
                $testKeys = $this->request->getPost('openai_keys');
                $keysToTest = [];
                
                if (is_array($testKeys) && !empty($testKeys)) {
                    foreach ($testKeys as $k) {
                        $k = trim((string) $k);
                        if ($k !== '') {
                            $keysToTest[] = $k;
                        }
                    }
                }
                
                if (empty($keysToTest)) {
                    $savedKeys = $settings->getSecretList('openai_keys');
                    if (!empty($savedKeys)) {
                        $keysToTest = $savedKeys;
                    } else {
                        $oldKey = $settings->getSecret('openai_key', '');
                        if ($oldKey !== '') {
                            $keysToTest = [$oldKey];
                        }
                    }
                }
                
                if (empty($keysToTest)) {
                    throw new \RuntimeException('Tidak ada API key yang tersedia untuk diuji.');
                }
                
                $models = null;
                $firstSuccessKey = null;
                
                // Uji setiap key secara individual
                foreach ($keysToTest as $key) {
                    $keyPrefix = substr($key, 0, 8) . '...';
                    try {
                        $result = AiClient::listModels('openai', $base, ['apiKey' => $key]);
                        $keyResults[$keyPrefix] = [
                            'success' => true,
                            'message' => '✓ ' . count($result['models'] ?? []) . ' model tersedia'
                        ];
                        
                        if ($models === null) {
                            $models = $result['models'] ?? [];
                            $firstSuccessKey = $key;
                        }
                    } catch (\RuntimeException $e) {
                        $keyResults[$keyPrefix] = [
                            'success' => false,
                            'message' => '✗ ' . $e->getMessage()
                        ];
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
}
