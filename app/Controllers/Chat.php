<?php

namespace App\Controllers;

use App\Libraries\AiClient;
use App\Libraries\PromptBuilder;
use App\Libraries\Skills\SkillRouter;
use App\Models\ChatContextModel;
use App\Models\ChatHistoryModel;
use App\Models\SettingModel;

class Chat extends BaseController
{
    protected ChatHistoryModel $chatModel;
    protected SettingModel $settings;
    protected string $provider = 'ollama';
    protected string $aiUrl;
    protected string $aiModel;
    protected string $apiKey = '';
    protected array $apiKeys = [];
    protected string $ocUser = '';
    protected string $ocPass = '';

    /** Ekstensi file teks/kode yang boleh dilampirkan. */
    protected array $allowedExt = [
        'txt', 'md', 'markdown', 'csv', 'json', 'xml', 'yml', 'yaml', 'ini', 'log',
        'php', 'js', 'ts', 'jsx', 'tsx', 'vue', 'py', 'java', 'c', 'cpp', 'h',
        'go', 'rs', 'rb', 'kt', 'swift', 'html', 'css', 'scss', 'sql', 'sh', 'pdf',
    ];

    public function __construct()
    {
        helper('chat');
        $this->chatModel = new ChatHistoryModel();
        $this->settings  = new SettingModel();

        // Sumber terpusat: AiClient::currentConfig()
        [$this->provider, $this->aiUrl, $this->aiModel, $opt] = AiClient::currentConfig();
        $this->apiKey  = (string) ($opt['apiKey'] ?? '');
        $this->apiKeys = (array) ($opt['apiKeys'] ?? []);
        $this->ocUser  = (string) ($opt['username'] ?? '');
        $this->ocPass  = (string) ($opt['password'] ?? '');

        // Sesi web internal: bukan request API eksternal, tapi tetap diberi
        // kebijakan agar tool berbahaya (file_reader) tidak tersedia bagi AI.
        \App\Libraries\Auth\PolicyGuard::setPolicy(
            \App\Libraries\Auth\AccessPolicy::internalWebSession()
        );
    }

    public function index()
    {
        $userId   = session()->get('user_id');
        $sessions = $this->chatModel->getUserSessions($userId);

        return view('chat/index', [
            'title'    => 'AI Chat',
            'sessions' => $sessions,
        ]);
    }

    public function session(string $sessionId)
    {
        $userId   = session()->get('user_id');
        $sessions = $this->chatModel->getUserSessions($userId);
        $messages = $this->chatModel->getSessionMessages($sessionId, $userId);

        if (empty($messages)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Sesi chat tidak ditemukan.');
        }

        return view('chat/index', [
            'title'            => 'AI Chat',
            'sessions'         => $sessions,
            'messages'         => $messages,
            'current_session'  => $sessionId,
        ]);
    }

    public function send()
    {
        $userId    = session()->get('user_id');
        $sessionId = trim((string) $this->request->getPost('session_id'));
        $message   = trim((string) $this->request->getPost('message'));

        if (empty($message)) {
            return $this->response->setJSON(['error' => 'Pesan tidak boleh kosong.', 'csrf' => csrf_hash()]);
        }

        if (strlen($message) > 4000) {
            return $this->response->setJSON(['error' => 'Pesan terlalu panjang (maks 4000 karakter).', 'csrf' => csrf_hash()]);
        }

        if (! empty($sessionId)) {
            // Sebelumnya memanggil getSessionMessages() hanya untuk memastikan
            // sesi milik user — menarik seluruh percakapan demi satu boolean.
            if (! $this->chatModel->sessionExists($sessionId, $userId)) {
                return $this->response->setJSON(['error' => 'Sesi tidak valid.', 'csrf' => csrf_hash()])->setStatusCode(403);
            }
        }

        $model = trim((string) $this->request->getPost('model'));
        if ($model === '' || strlen($model) > 100 || ! preg_match('/^[\w.\-\/:]+$/', $model)) {
            $model = $this->aiModel;
        }

        // Sesi baru harus ada dulu sebelum simpan lampiran
        $isNew = false;
        if (empty($sessionId)) {
            $isNew     = true;
            $sessionId = $this->generateSessionId();

            $title = strlen($message) > 50 ? substr($message, 0, 50) . '...' : $message;
            $this->chatModel->saveMessage([
                'user_id'    => $userId,
                'session_id' => $sessionId,
                'title'      => $title,
                'role'       => 'system',
                'content'    => 'Session started',
            ]);
        }

        // Lampiran file (opsional)
        $attach = $this->handleUpload($userId, $sessionId);
        if (isset($attach['error'])) {
            if ($isNew) {
                $this->chatModel->deleteSession($sessionId, $userId);
            }

            return $this->response->setJSON(['error' => $attach['error'], 'csrf' => csrf_hash()]);
        }

        if ($attach['text'] !== '') {
            $message .= "\n\n--- Lampiran: {$attach['name']} ---\n" . $attach['text'];
        }

        // PERBAIKAN B1 (langkah 1/2): ambil riwayat SEBELUM pesan baru disimpan.
        // Sebelumnya pesan disimpan dulu, lalu buildContext() mengambil riwayat
        // yang sudah memuat pesan itu, DAN PromptBuilder menambahkannya lagi —
        // sehingga pesan user terkirim dua kali dalam urutan yang salah.
        $historyLimit = (int) $this->settings->getGlobal('ai_history_limit', '40');
        $history      = $this->chatModel->getSessionMessages($sessionId, $userId, max(2, $historyLimit));

        $this->chatModel->saveMessage([
            'user_id'         => $userId,
            'session_id'      => $sessionId,
            'role'            => 'user',
            'content'         => $message,
            'attachment_name' => $attach['name'],
            'attachment_path' => $attach['path'],
        ]);

        // Sumber internet cadangan kini disuntik ke SYSTEM prompt di dalam
        // buildContext(), bukan lagi sebagai turn user tambahan (perbaikan B4).
        $context = $this->buildContext($history, $sessionId, $userId, $message);

        try {
            $assistantReply = AiClient::chat(
                $this->provider,
                $this->aiUrl,
                $model,
                $context,
                [
                    'apiKey'          => $this->apiKey,
                    'apiKeys'         => $this->apiKeys,
                    'username'        => $this->ocUser,
                    'password'        => $this->ocPass,
                    'opencodeSession' => (string) $this->settings->getGlobal('opencode_session_' . $sessionId, ''),
                ]
            );
        } catch (\RuntimeException $e) {
            $assistantReply = ['content' => 'Error: ' . $e->getMessage(), 'tokens' => 0, 'session_id' => null];
        }

        // Simpan mapping sesi opencode <-> sesi chat
        if (! empty($assistantReply['session_id'])) {
            $this->settings->setGlobal('opencode_session_' . $sessionId, $assistantReply['session_id']);
        }

        $this->chatModel->saveMessage([
            'user_id'     => $userId,
            'session_id'  => $sessionId,
            'role'        => 'assistant',
            'content'     => $assistantReply['content'],
            'tokens_used' => $assistantReply['tokens'],
        ]);

        return $this->response->setJSON([
            'success'    => true,
            'session_id' => $sessionId,
            'reply'      => $assistantReply['content'],
            'tokens'     => $assistantReply['tokens'],
            'model'      => $model,
            'attachment' => $attach['name'],
            'csrf'       => csrf_hash(),
        ]);
    }

    public function deleteSession()
    {
        $userId    = session()->get('user_id');
        $sessionId = trim((string) $this->request->getPost('session_id'));

        if (empty($sessionId)) {
            return $this->response->setJSON(['error' => 'Session tidak valid.', 'csrf' => csrf_hash()])->setStatusCode(400);
        }

        $this->chatModel->deleteSession($sessionId, $userId);
        $this->settings->where('user_id', null)->where('key', 'opencode_session_' . $sessionId)->delete();
        // delete() langsung melewati setGlobal(), jadi cache harus dibuang manual.
        SettingModel::flushCache();

        return $this->response->setJSON(['success' => true, 'csrf' => csrf_hash()]);
    }

    public function renameSession()
    {
        $userId    = session()->get('user_id');
        $sessionId = trim((string) $this->request->getPost('session_id'));
        $title     = trim((string) $this->request->getPost('title'));

        if (empty($sessionId) || empty($title)) {
            return $this->response->setJSON(['error' => 'Data tidak valid.', 'csrf' => csrf_hash()])->setStatusCode(400);
        }

        if (strlen($title) > 100) {
            return $this->response->setJSON(['error' => 'Judul terlalu panjang.', 'csrf' => csrf_hash()])->setStatusCode(400);
        }

        $this->chatModel->renameSession($sessionId, $userId, $title);

        return $this->response->setJSON(['success' => true, 'csrf' => csrf_hash()]);
    }

    /**
     * Rakit pesan untuk AI.
     *
     * PERBAIKAN B1: riwayat diterima sebagai parameter, BUKAN diambil lagi di
     * dalam sini setelah pesan baru disimpan. Itu sumber duplikasi pesan.
     *
     * @param array<int, array> $history riwayat SEBELUM pesan saat ini disimpan
     */
    protected function buildContext(array $history, string $sessionId, int $userId, string $currentMessage = ''): array
    {
        // --- Context Memory & Skill Routing ---
        $contextModel = new ChatContextModel();
        $context      = $contextModel->getContext($sessionId, $userId);
        $identifier   = session()->get('identifier') ?: ($context['identifier'] ?? null);
        $userType     = session()->get('user_type') ?: ($context['user_type'] ?? 'guest');

        if (session()->has('identifier')) {
            $context['identifier'] = session()->get('identifier');
        }
        if (session()->has('user_type')) {
            $context['user_type'] = session()->get('user_type');
        }

        $context['role'] = session()->get('user_role') ?? 'user';

        $skillName = 'general';
        $intent    = 'general';
        $skillResult = null;

        if ($currentMessage !== '') {
            $router  = new SkillRouter();
            $route   = $router->route($currentMessage, $context);
            $skillName = $route['skill'];
            $intent    = $route['intent'];
            $context['last_intent'] = $intent;

            $skillResult = $router->execute($skillName, [
                'intent'     => $intent,
                'identifier' => $identifier,
                'user_type'  => $userType,
            ]);

            // If skill succeeded and returned identifier, cache it
            if ($skillResult->success && ! empty($skillResult->data['npm'])) {
                $context['identifier'] = $skillResult->data['npm'];
            }

            $contextModel->saveContext($sessionId, $userId, $context);
        }

        // ---- 1. Bangun SYSTEM PROMPT saja (PromptBuilder tak lagi menyisipkan
        //         pesan user — itu tugas ContextBuilder) ----
        $system = $skillResult !== null
            ? PromptBuilder::build($skillName, $skillResult)
            : PromptBuilder::general('AI Coding & Academic Assistant');

        // ---- 2. PERBAIKAN B4: hasil web masuk ke SYSTEM, bukan sebagai
        //         turn user tambahan yang menggeser pertanyaan asli ----
        if (\App\Libraries\WebSearch::needsWeb($currentMessage)) {
            $tmp    = \App\Libraries\WebSearch::withWebContext(
                [['role' => 'system', 'content' => $system]],
                $currentMessage
            );
            $system = (string) ($tmp[0]['content'] ?? $system);
        }

        // ---- 3. ContextBuilder menjamin urutan benar + anggaran token ----
        return (new \App\Libraries\ContextBuilder(
            (int) $this->settings->getGlobal('ai_max_context_tokens', '6000'),
            (int) $this->settings->getGlobal('ai_max_tokens', '1024'),
        ))->build($system, $history, $currentMessage);
    }

    /** Daftar model dari provider aktif (untuk dropdown). */
    public function models()
    {
        try {
            $result = AiClient::listModels($this->provider, $this->aiUrl, [
                'apiKey'   => $this->apiKey,
                'username' => $this->ocUser,
                'password' => $this->ocPass,
                'default'  => $this->aiModel,
            ]);

            return $this->response->setJSON([
                'success'  => true,
                'models'   => $result['models'],
                'default'  => $result['default'],
                'provider' => $this->provider,
            ]);
        } catch (\RuntimeException $e) {
            return $this->response->setJSON([
                'success'  => false,
                'models'   => [],
                'default'  => $this->aiModel,
                'provider' => $this->provider,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Tangani upload lampiran. Return ['name'=>?string,'path'=>?string,'text'=>'','error'?].
     * Tidak throw untuk kasus file tak ada (kembali kosong).
     */
    protected function handleUpload(int $userId, string $sessionId): array
    {
        $empty = ['name' => null, 'path' => null, 'text' => ''];
        $file  = $this->request->getFile('attachment');

        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return $empty;
        }

        if (! $file->isValid()) {
            return $empty + ['error' => 'Upload file gagal (kode ' . $file->getError() . ').'];
        }

        $ext     = strtolower($file->getExtension());
        $maxSize = $ext === 'pdf' ? 10 * 1024 * 1024 : 2 * 1024 * 1024;

        if ($file->getSize() > $maxSize) {
            return $empty + ['error' => 'File terlalu besar (maks ' . ($ext === 'pdf' ? '10 MB' : '2 MB') . ').'];
        }

        if (! in_array($ext, $this->allowedExt, true)) {
            return $empty + ['error' => 'Jenis file .' . $ext . ' tidak didukung.'];
        }

        $dir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'chat'
             . DIRECTORY_SEPARATOR . $userId . DIRECTORY_SEPARATOR . $sessionId;

        if (! is_dir($dir) && ! mkdir($dir, 0755, true)) {
            return $empty + ['error' => 'Tidak dapat menyimpan file.'];
        }

        $newName = $file->getRandomName();
        $file->move($dir, $newName);

        $full = $dir . DIRECTORY_SEPARATOR . $newName;
        $raw  = @file_get_contents($full);

        $text = '';
        if ($ext === 'pdf') {
            $text = $this->extractPdfText($full);
        } elseif (is_string($raw) && $raw !== '' && strpos($raw, "\0") === false) {
            $text = mb_substr($raw, 0, 6000);
            if (mb_strlen($raw) > 6000) {
                $text .= "\n[...file dipotong, total " . strlen($raw) . ' byte...]';
            }
        }

        return [
            'name' => $file->getClientName(),
            'path' => 'chat/' . $userId . '/' . $sessionId . '/' . $newName,
            'text' => $text !== '' ? $text : '(isi file tidak terbaca sebagai teks)',
        ];
    }

    /** Ekstrak teks PDF (murni PHP, tanpa binary eksternal). Maks 8000 char. */
    protected function extractPdfText(string $path): string
    {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf    = $parser->parseFile($path);
            $text   = trim(preg_replace('/\s+/', ' ', $pdf->getText() ?? ''));

            if ($text === '') {
                return '(PDF tidak mengandung teks — kemungkinan hasil scan/gambar.)';
            }

            if (mb_strlen($text) > 8000) {
                $total = mb_strlen($text);
                $text  = mb_substr($text, 0, 8000) . "\n[...PDF dipotong, total {$total} karakter...]";
            }

            return $text;
        } catch (\Throwable $e) {
            return '(PDF gagal dibaca: ' . $e->getMessage() . ')';
        }
    }

    protected function generateSessionId(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
