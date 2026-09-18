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

        return view('settings/index', [
            'title'          => 'Pengaturan AI',
            'ai_provider'    => $settings->getGlobal('ai_provider', 'ollama') ?: 'ollama',
            'ollama_url'     => $settings->getGlobal('ollama_url', env('app.ollamaUrl', 'http://localhost:11434')),
            'ollama_model'   => $settings->getGlobal('ollama_model', env('app.ollamaModel', 'qwen2.5-coder:32b')),
            'openai_base'    => $settings->getGlobal('openai_base', 'https://api.openai.com'),
            'openai_model'   => $settings->getGlobal('openai_model', 'gpt-4o-mini'),
            'has_openai_key' => $settings->getSecret('openai_key', '') !== '',
            'opencode_url'   => $settings->getGlobal('opencode_url', 'http://127.0.0.1:4096'),
            'opencode_user'  => $settings->getGlobal('opencode_user', ''),
            'opencode_model' => $settings->getGlobal('opencode_model', ''),
            'has_oc_pass'    => $settings->getSecret('opencode_pass', '') !== '',
            'search_provider' => $settings->getGlobal('search_provider', 'off') ?: 'off',
            'search_max'     => $settings->getGlobal('search_max', '5'),
            'has_search_key' => $settings->getSecret('search_key', '') !== '',
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
            $key = trim((string) $this->request->getPost('openai_key'));
            if ($key !== '') {
                $settings->setSecret('openai_key', $key);
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

        return redirect()->back()->with('success', 'Pengaturan AI berhasil disimpan.');
    }

    /** Tes koneksi provider + daftar model. Dipakai via AJAX. */
    public function test()
    {
        $settings = new SettingModel();
        $provider = $this->request->getPost('ai_provider') ?: $settings->getGlobal('ai_provider', 'ollama');

        try {
            if ($provider === 'openai') {
                $base = rtrim(trim((string) $this->request->getPost('openai_base'))
                    ?: $settings->getGlobal('openai_base', 'https://api.openai.com'), '/');
                $key = trim((string) $this->request->getPost('openai_key'))
                    ?: $settings->getSecret('openai_key', '');
                $result = AiClient::listModels('openai', $base, ['apiKey' => $key]);
            } elseif ($provider === 'opencode') {
                $base = rtrim(trim((string) $this->request->getPost('opencode_url'))
                    ?: $settings->getGlobal('opencode_url', 'http://127.0.0.1:4096'), '/');
                $result = AiClient::listModels('opencode', $base, [
                    'username' => trim((string) $this->request->getPost('opencode_user'))
                        ?: $settings->getGlobal('opencode_user', ''),
                    'password' => (string) $this->request->getPost('opencode_pass')
                        ?: $settings->getSecret('opencode_pass', ''),
                ]);
            } else {
                $base = rtrim(trim((string) $this->request->getPost('ollama_url'))
                    ?: $settings->getGlobal('ollama_url', env('app.ollamaUrl', 'http://localhost:11434')), '/');
                $result = AiClient::listModels('ollama', $base);
            }

            return $this->response->setJSON([
                'success' => true,
                'models'  => $result['models'],
                'csrf'    => csrf_hash(),
            ]);
        } catch (\RuntimeException $e) {
            return $this->response->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
                'csrf'    => csrf_hash(),
            ]);
        }
    }
}
