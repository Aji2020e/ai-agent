<!DOCTYPE html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $title ?? 'Login' ?> - AI Coding Assistant</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        * { font-family: 'Inter', system-ui, sans-serif; }
        body { min-height: 100vh; background: #0e1030; }
        .auth-wrap { min-height: 100vh; display: flex; }
        .auth-show {
            flex: 1.1; color: #fff; padding: 3rem;
            background: linear-gradient(150deg, #1a1440 0%, #3b1d6e 45%, #7c2d8e 75%, #d946ef 130%);
            position: relative; overflow: hidden;
            display: flex; flex-direction: column; justify-content: center;
        }
        .auth-show::before {
            content: ''; position: absolute; width: 520px; height: 520px;
            border-radius: 50%; top: -160px; right: -140px;
            background: radial-gradient(circle, rgba(255,255,255,.22), transparent 65%);
        }
        .auth-show::after {
            content: ''; position: absolute; width: 420px; height: 420px;
            border-radius: 50%; bottom: -160px; left: -120px;
            background: radial-gradient(circle, rgba(99,102,241,.5), transparent 65%);
        }
        .auth-show > * { position: relative; z-index: 1; }
        .feat { display: flex; gap: .8rem; align-items: flex-start; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.15); border-radius: 1rem; padding: .9rem 1rem; margin-top: .8rem; backdrop-filter: blur(6px); }
        .feat i { font-size: 1.3rem; }
        .auth-form-side { flex: 1; background: #eef1f8; display: flex; align-items: center; justify-content: center; padding: 2rem; }
        [data-bs-theme="dark"] .auth-form-side { background: #0b0e1a; }
        .auth-card { width: 100%; max-width: 430px; border: 0; border-radius: 1.25rem; box-shadow: 0 20px 60px rgba(30,20,80,.18); }
        .btn-ai { background: linear-gradient(135deg, #6366f1, #8b5cf6 55%, #d946ef); border: 0; color: #fff; font-weight: 600; }
        .btn-ai:hover { color: #fff; filter: brightness(1.08); }
        @media (max-width: 991.98px) { .auth-show { display: none; } }
    </style>
</head>
<body>
<div class="auth-wrap">
    <div class="auth-show">
        <a href="<?= site_url('login') ?>" class="d-flex align-items-center gap-2 text-white text-decoration-none mb-4">
            <span class="d-inline-flex align-items-center justify-content-center rounded-3" style="width:46px;height:46px;background:rgba(255,255,255,.16);font-size:1.4rem;"><i class="bi bi-cpu"></i></span>
            <span><small class="d-block opacity-75" style="font-size:.72rem;letter-spacing:.1em;">AI PLATFORM</small><strong>Coding Assistant</strong></span>
        </a>
        <h1 class="fw-bold display-6">Ngoding ditemani AI, bukan begadang sendirian.</h1>
        <p class="opacity-75">Tanya soal coding, debugging, dan arsitektur software — dengan riwayat chat tersimpan per user.</p>
        <div class="feat"><i class="bi bi-chat-dots"></i><div><strong>Multi-session chat</strong><br><small class="opacity-75">Bikin banyak sesi percakapan, ganti-ganti topik kapan saja.</small></div></div>
        <div class="feat"><i class="bi bi-shield-check"></i><div><strong>Aman per user</strong><br><small class="opacity-75">Riwayat terisolasi, admin kelola akun dari panel khusus.</small></div></div>
        <div class="feat"><i class="bi bi-lightning-charge"></i><div><strong>Powered by Ollama</strong><br><small class="opacity-75">Model qwen-coder berjalan di infrastruktur sendiri.</small></div></div>
    </div>
    <div class="auth-form-side">
        <div class="card auth-card">
            <div class="card-body p-4 p-md-5">
                <?= $this->renderSection('content') ?>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
