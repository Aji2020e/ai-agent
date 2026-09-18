<?php

namespace App\Libraries;

use RuntimeException;
use Throwable;

/**
 * Klien AI generik untuk 3 provider:
 *  - ollama  : POST {base}/api/chat            | GET {base}/api/tags
 *  - openai  : POST {base}/chat/completions    | GET {base}/models   (Bearer key)
 *  - opencode: POST {p}/session + {p}/session/{id}/message | GET {p}/config/providers
 *              (opencode serve, basic auth opsional, p = '' atau '/api')
 */
class AiClient
{
    /**
     * Konfigurasi provider aktif dari settings (DB) fallback .env (Ollama).
     * Return [provider, url, model, opt(apiKey/username/password/timeout)].
     */
    public static function currentConfig(): array
    {
        $s        = new \App\Models\SettingModel();
        $provider = $s->getGlobal('ai_provider', 'ollama') ?: 'ollama';

        if ($provider === 'openai') {
            return ['openai',
                rtrim($s->getGlobal('openai_base', 'https://api.openai.com') ?: 'https://api.openai.com', '/'),
                $s->getGlobal('openai_model', 'gpt-4o-mini') ?: 'gpt-4o-mini',
                [
                    'apiKey'  => (string) $s->getSecret('openai_key', ''),
                    'timeout' => 300,
                ] + self::sampling($s),
            ];
        }

        if ($provider === 'opencode') {
            return ['opencode',
                rtrim($s->getGlobal('opencode_url', 'http://127.0.0.1:4096') ?: 'http://127.0.0.1:4096', '/'),
                (string) $s->getGlobal('opencode_model', ''),
                [
                    'username' => (string) $s->getGlobal('opencode_user', ''),
                    'password' => (string) $s->getSecret('opencode_pass', ''),
                    'timeout'  => 600,
                ] + self::sampling($s),
            ];
        }

        return ['ollama',
            rtrim($s->getGlobal('ollama_url', env('app.ollamaUrl', 'http://localhost:11434')) ?: 'http://localhost:11434', '/'),
            $s->getGlobal('ollama_model', env('app.ollamaModel', 'qwen2.5-coder:32b')) ?: 'qwen2.5-coder:32b',
            ['timeout' => 300] + self::sampling($s),
        ];
    }

    /**
     * PERBAIKAN B3 — parameter sampling terpusat dari settings.
     *
     * Sebelumnya jalur OpenAI tidak mengirim `temperature` sama sekali, sehingga
     * provider memakai default 1.0: sangat kreatif, dan untuk pertanyaan data
     * akademik itu berarti mengarang.
     *
     * Default sengaja rendah (0.2) karena mayoritas beban kerja sistem ini
     * adalah melaporkan data, bukan berkreasi.
     *
     * @return array<string, float|int|string>
     */
    public static function sampling(\App\Models\SettingModel $s): array
    {
        return [
            'temperature' => self::clampFloat($s->getGlobal('ai_temperature', '0.2'), 0.0, 2.0, 0.2),
            'top_p'       => self::clampFloat($s->getGlobal('ai_top_p', '0.9'), 0.1, 1.0, 0.9),
            'max_tokens'  => self::clampInt($s->getGlobal('ai_max_tokens', '1024'), 64, 8192, 1024),
            'num_ctx'     => self::clampInt($s->getGlobal('ai_num_ctx', '8192'), 2048, 131072, 8192),
            'keep_alive'  => (string) ($s->getGlobal('ai_keep_alive', '30m') ?: '30m'),
        ];
    }

    private static function clampFloat(?string $v, float $min, float $max, float $def): float
    {
        if ($v === null || trim($v) === '' || ! is_numeric($v)) {
            return $def;
        }

        return max($min, min($max, (float) $v));
    }

    private static function clampInt(?string $v, int $min, int $max, int $def): int
    {
        if ($v === null || trim($v) === '' || ! is_numeric($v)) {
            return $def;
        }

        return max($min, min($max, (int) $v));
    }

    /**
     * Model efektif untuk klien API: kolom `model` klien bila diisi,
     * selain itu default global. Selalu kembalikan string aman.
     */
    public static function clientModel(?array $client, string $default): string
    {
        $m = trim((string) ($client['model'] ?? ''));
        if ($m === '' || strlen($m) > 100 || ! preg_match('/^[\w.\-\/:]+$/', $m)) {
            return $default;
        }

        return $m;
    }

    /**
     * Konteks waktu berjalan — ditempel ke system prompt agar model tahu "sekarang".
     *
     * Tidak lagi memanggil date_default_timezone_set(): itu side-effect global
     * yang mengubah perilaku seluruh aplikasi setiap kali prompt dibangun.
     */
    public static function timeContext(): string
    {
        try {
            $tz = new \DateTimeZone((string) (config('App')->appTimezone ?: 'Asia/Jakarta'));
        } catch (\Throwable) {
            $tz = new \DateTimeZone('Asia/Jakarta');
        }

        $now = new \DateTimeImmutable('now', $tz);

        return 'Konteks waktu: hari ini ' . $now->format('l, d F Y H:i') . ' WIB. '
            . 'Jangan pernah mengaku pengetahuanmu mutakhir; bila ragu soal peristiwa terkini, katakan terus terang.';
    }

    /**
     * Kirim chat. Return ['content' => string, 'tokens' => int, 'session_id' => ?string].
     * $opt: apiKey, username, password, opencodeSession, timeout.
     *
     * @throws RuntimeException bila gagal (pesan sudah ramah pengguna)
     */
    public static function chat(string $provider, string $baseUrl, string $model, array $messages, array $opt = []): array
    {
        $baseUrl  = rtrim($baseUrl, '/');
        $messages = self::stampTime($messages);

        if ($provider === 'openai') {
            return self::chatOpenAi($baseUrl, $model, $messages, (string) ($opt['apiKey'] ?? ''), (int) ($opt['timeout'] ?? 300), $opt);
        }

        if ($provider === 'opencode') {
            return self::chatOpencode(
                $baseUrl,
                $model,
                $messages,
                (string) ($opt['opencodeSession'] ?? ''),
                (string) ($opt['username'] ?? ''),
                (string) ($opt['password'] ?? ''),
                (int) ($opt['timeout'] ?? 600)
            );
        }

        return self::chatOllama($baseUrl, $model, $messages, (int) ($opt['timeout'] ?? 300), $opt);
    }

    /** Sisipkan konteks waktu ke pesan system pertama (bila ada). */
    private static function stampTime(array $messages): array
    {
        foreach ($messages as $i => $m) {
            if (($m['role'] ?? '') === 'system' && ! str_contains($m['content'], 'Konteks waktu:')) {
                $messages[$i]['content'] .= "\n\n" . self::timeContext();
                break;
            }
        }

        return $messages;
    }

    /**
     * Daftar model terinstal. Return ['models' => string[], 'default' => string].
     *
     * @throws RuntimeException bila gagal
     */
    public static function listModels(string $provider, string $baseUrl, array $opt = []): array
    {
        $baseUrl = rtrim($baseUrl, '/');

        if ($provider === 'openai') {
            $base = self::openAiBase($baseUrl);
            $data = self::http('GET', $base . '/models', [], self::bearer((string) ($opt['apiKey'] ?? '')), 20);
            $models = [];
            foreach ($data['data'] ?? [] as $m) {
                if (isset($m['id'])) {
                    $models[] = $m['id'];
                }
            }

            return ['models' => $models, 'default' => (string) ($opt['default'] ?? '')];
        }

        if ($provider === 'opencode') {
            return [
                'models'  => self::opencodeModels($baseUrl, (string) ($opt['username'] ?? ''), (string) ($opt['password'] ?? '')),
                'default' => (string) ($opt['default'] ?? ''),
            ];
        }

        $data   = self::http('GET', $baseUrl . '/api/tags', [], [], 20);
        $models = array_column($data['models'] ?? [], 'name');

        return ['models' => $models, 'default' => (string) ($opt['default'] ?? '')];
    }

    // ---------------- Ollama ----------------

    private static function chatOllama(string $baseUrl, string $model, array $messages, int $timeout, array $opt = []): array
    {
        try {
            $response = \Config\Services::curlrequest()->post($baseUrl . '/api/chat', [
                'json'    => [
                    'model'    => $model,
                    'messages' => $messages,
                    'stream'   => false,
                    // keep_alive mencegah Ollama meng-unload model setelah 5 menit
                    // idle. Tanpa ini, request pertama tiap sesi membayar biaya
                    // memuat ulang model belasan GB ke VRAM.
                    'keep_alive' => (string) ($opt['keep_alive'] ?? '30m'),
                    'options'  => [
                        // Sebelumnya 0.7: terlalu kreatif untuk laporan data.
                        'temperature'    => (float) ($opt['temperature'] ?? 0.2),
                        'top_p'          => (float) ($opt['top_p'] ?? 0.9),
                        // Sebelumnya 4096: terlalu kecil. Saat prompt melewatinya
                        // Ollama memotong dari DEPAN, membuang system prompt
                        // beserta data akademik — akar halusinasi.
                        'num_ctx'        => (int) ($opt['num_ctx'] ?? 8192),
                        'num_predict'    => (int) ($opt['max_tokens'] ?? 1024),
                        'repeat_penalty' => 1.1,
                    ],
                ],
                'timeout' => $timeout,
            ]);
            $result = json_decode($response->getBody(), true);
        } catch (Throwable $e) {
            throw new RuntimeException("Tidak dapat terhubung ke Ollama di {$baseUrl}. Detail: " . $e->getMessage());
        }

        if (isset($result['message']['content'])) {
            return [
                'content'    => $result['message']['content'],
                'tokens'     => ($result['eval_count'] ?? 0) + ($result['prompt_eval_count'] ?? 0),
                'session_id' => null,
            ];
        }

        if (isset($result['error'])) {
            throw new RuntimeException('Ollama: ' . $result['error']);
        }

        throw new RuntimeException('Ollama mengembalikan respons tak dikenal.');
    }

    // ---------------- OpenAI-compatible ----------------

    private static function openAiBase(string $baseUrl): string
    {
        $path = parse_url($baseUrl, PHP_URL_PATH);

        if ($path === null || $path === '' || $path === '/') {
            return $baseUrl . '/v1';
        }

        // Sudah berversi (mis. .../v1) → pakai apa adanya.
        if (preg_match('#/v\d+$#', rtrim($path, '/'))) {
            return $baseUrl;
        }

        // Path kustom tanpa versi (mis. https://openrouter.ai/api)
        // → tambahkan /v1 sehingga menjadi .../api/v1.
        return rtrim($baseUrl, '/') . '/v1';
    }

    private static function bearer(string $apiKey): array
    {
        return $apiKey !== '' ? ['Authorization' => 'Bearer ' . $apiKey] : [];
    }

    private static function chatOpenAi(string $baseUrl, string $model, array $messages, string $apiKey, int $timeout, array $opt = []): array
    {
        $base = self::openAiBase($baseUrl);
        $data = self::http('POST', $base . '/chat/completions', [
            'model'    => $model,
            'messages' => $messages,
            // PERBAIKAN B3: tanpa parameter ini provider memakai default
            // temperature 1.0 — sangat tidak cocok untuk laporan data akademik.
            'temperature'       => (float) ($opt['temperature'] ?? 0.2),
            'top_p'             => (float) ($opt['top_p'] ?? 0.9),
            'max_tokens'        => (int) ($opt['max_tokens'] ?? 1024),
            'frequency_penalty' => 0.1,
        ], self::bearer($apiKey), $timeout);

        $content = $data['choices'][0]['message']['content'] ?? null;

        if (! is_string($content)) {
            throw new RuntimeException('Provider API: ' . ($data['error']['message'] ?? 'respons tak dikenal.'));
        }

        return [
            'content'    => $content,
            'tokens'     => (int) ($data['usage']['total_tokens'] ?? 0),
            'session_id' => null,
        ];
    }

    // ---------------- OpenCode ----------------

    /** Kandidat path dengan prefix '' lalu '/api' (antar versi opencode). */
    private static function ocPaths(string $path): array
    {
        return [$path, '/api' . $path];
    }

    private static function ocAuth(string $username, string $password): array
    {
        if ($password === '') {
            return [];
        }

        return ['auth' => [$username !== '' ? $username : 'opencode', $password]];
    }

    /** GET pertama yang 200 dari kandidat. Return [body, pathTerpakai]. */
    private static function ocGet(string $baseUrl, array $candidates, array $auth, int $timeout = 20): array
    {
        $last = null;

        foreach ($candidates as $path) {
            try {
                $response = \Config\Services::curlrequest()->get($baseUrl . $path, [
                    'timeout' => $timeout,
                    'http_errors' => false,
                ] + $auth);

                if ($response->getStatusCode() === 200) {
                    return [json_decode($response->getBody(), true), $path];
                }

                $last = 'HTTP ' . $response->getStatusCode() . " untuk {$path}";
            } catch (Throwable $e) {
                $last = $e->getMessage();
            }
        }

        throw new RuntimeException("Server OpenCode tak terjangkau di {$baseUrl}. Detail: {$last}");
    }

    /** POST pertama yang tidak 404 dari kandidat. Return [body, pathTerpakai]. */
    private static function ocPost(string $baseUrl, array $candidates, array $json, array $auth, int $timeout): array
    {
        $last = null;

        foreach ($candidates as $path) {
            try {
                $response = \Config\Services::curlrequest()->post($baseUrl . $path, [
                    'json'        => $json,
                    'timeout'     => $timeout,
                    'http_errors' => false,
                ] + $auth);
                $code = $response->getStatusCode();

                if ($code >= 200 && $code < 300) {
                    return [json_decode($response->getBody(), true), $path];
                }

                if ($code === 404) {
                    $last = "HTTP 404 untuk {$path}";
                    continue;
                }

                throw new RuntimeException('OpenCode: HTTP ' . $code . ' — ' . substr($response->getBody(), 0, 300));
            } catch (RuntimeException $e) {
                throw $e;
            } catch (Throwable $e) {
                $last = $e->getMessage();
            }
        }

        throw new RuntimeException("Server OpenCode tak terjangkau di {$baseUrl}. Detail: {$last}");
    }

    private static function opencodeModels(string $baseUrl, string $username, string $password): array
    {
        $auth = self::ocAuth($username, $password);

        [$data] = self::ocGet($baseUrl, ['/config/providers', '/provider', '/api/config/providers', '/api/provider'], $auth);

        $providers = $data['providers'] ?? $data['all'] ?? (is_array($data) ? $data : []);
        $models    = [];

        foreach ((array) $providers as $p) {
            if (! is_array($p) || ! isset($p['id'])) {
                continue;
            }
            $pid = $p['id'];
            $pm  = $p['models'] ?? [];

            if (array_is_list($pm)) {
                foreach ($pm as $m) {
                    $models[] = is_array($m) ? ($pid . '/' . ($m['id'] ?? $m['name'] ?? '?')) : ($pid . '/' . $m);
                }
            } else {
                foreach ((array) $pm as $mid => $info) {
                    $models[] = $pid . '/' . $mid;
                }
            }
        }

        return array_values(array_unique($models));
    }

    private static function chatOpencode(
        string $baseUrl,
        string $model,
        array $messages,
        string $sessionId,
        string $username,
        string $password,
        int $timeout
    ): array {
        $auth = self::ocAuth($username, $password);

        // 1. Sesi opencode (pakai ulang bila ada mapping)
        $prefix = '';
        if ($sessionId === '') {
            [$created, $used] = self::ocPost($baseUrl, self::ocPaths('/session'), [
                'title' => 'AI Assistant',
            ], $auth, 30);
            $sessionId = $created['id'] ?? null;

            if (! is_string($sessionId) || $sessionId === '') {
                throw new RuntimeException('OpenCode: gagal membuat sesi.');
            }
            $prefix = str_starts_with($used, '/api') ? '/api' : '';
        } else {
            // Verifikasi sesi masih ada; bila tidak, buat baru
            try {
                [$got, $gotPath] = self::ocGet($baseUrl, ['/session/' . $sessionId, '/api/session/' . $sessionId], $auth);
                $prefix          = str_starts_with($gotPath, '/api') ? '/api' : '';
            } catch (Throwable) {
                [$created, $used] = self::ocPost($baseUrl, self::ocPaths('/session'), ['title' => 'AI Assistant'], $auth, 30);
                $sessionId = $created['id'] ?? null;

                if (! is_string($sessionId) || $sessionId === '') {
                    throw new RuntimeException('OpenCode: gagal membuat sesi.');
                }
                $prefix = str_starts_with($used, '/api') ? '/api' : '';
            }
        }

        // 2. Bentuk pesan: gabung riwayat jadi satu teks
        $history = [];
        foreach ($messages as $m) {
            if (($m['role'] ?? '') === 'system') {
                continue;
            }
            $history[] = ($m['role'] === 'assistant' ? 'Asisten' : 'User') . ': ' . $m['content'];
        }
        $sentText = implode("\n\n", $history);

        $body = ['parts' => [['type' => 'text', 'text' => $sentText]]];

        if ($model !== '' && str_contains($model, '/')) {
            [$pid, $mid]       = explode('/', $model, 2);
            $body['model']     = ['providerID' => $pid, 'modelID' => $mid];
        }

        [$data] = self::ocPost(
            $baseUrl,
            [$prefix . '/session/' . $sessionId . '/message', '/session/' . $sessionId . '/message', '/api/session/' . $sessionId . '/message'],
            $body,
            $auth,
            $timeout
        );

        $text = self::extractOpencodeText($data, $sentText);

        if ($text === '') {
            throw new RuntimeException('OpenCode: respons kosong (mungkin menunggu izin tool di server — cek opencode).');
        }

        return ['content' => $text, 'tokens' => 0, 'session_id' => $sessionId];
    }

    private static function extractOpencodeText(mixed $data, string $sentText): string
    {
        if (is_string($data)) {
            return $data === $sentText ? '' : $data;
        }

        if (! is_array($data)) {
            return '';
        }

        $out = [];

        $walk = static function ($node) use (&$walk, &$out) {
            if (is_array($node)) {
                if (($node['type'] ?? '') === 'text' && isset($node['text']) && is_string($node['text'])) {
                    $out[] = $node['text'];
                }
                foreach ($node as $v) {
                    $walk($v);
                }
            }
        };
        $walk($data);

        // Buang gema pesan yang kita kirim, gabung sisa teks asisten
        $texts = array_values(array_unique(array_filter($out, static fn ($t) => $t !== '' && $t !== $sentText)));

        return implode("\n\n", $texts);
    }

    // ---------------- HTTP generik (ollama/openai) ----------------

    private static function http(string $method, string $url, array $json, array $headers, int $timeout): array
    {
        try {
            $client   = \Config\Services::curlrequest();
            // http_errors=false agar body error provider (mis. penjelasan 429) bisa dibaca.
            $options  = ['timeout' => $timeout, 'http_errors' => false];
            $options  = $headers !== [] ? ($options + ['headers' => $headers]) : $options;
            $options  = $json !== [] ? ($options + ['json' => $json]) : $options;
            $response = strtolower($method) === 'post' ? $client->post($url, $options) : $client->get($url, $options);
            $code     = $response->getStatusCode();
            $decoded  = (array) json_decode($response->getBody(), true);

            if ($code >= 400) {
                $msg = $decoded['error']['message'] ?? $decoded['error'] ?? $decoded['message'] ?? ('HTTP ' . $code);
                if (is_array($msg)) {
                    $msg = json_encode($msg);
                }
                $hint = $code === 429 ? ' (batas pemakaian model terlampaui — coba lagi nanti atau naikkan limit akun)' : '';
                throw new RuntimeException("Provider API (HTTP {$code}){$hint}: " . $msg);
            }

            return $decoded;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException("Gagal menghubungi {$url}. Detail: " . $e->getMessage());
        }
    }
}
