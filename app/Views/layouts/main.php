<!DOCTYPE html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <meta name="csrf-name" content="<?= csrf_token() ?>">
    <title><?= $title ?? 'AI Coding Assistant' ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <style>
        :root {
            --ai-1: #6366f1;
            --ai-2: #8b5cf6;
            --ai-3: #d946ef;
            --ai-grad: linear-gradient(135deg, #6366f1 0%, #8b5cf6 55%, #d946ef 100%);
            --side-w: 264px;
            --radius: 1rem;
        }
        * { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        body { background: #eef1f8; min-height: 100vh; }
        [data-bs-theme="dark"] body, [data-bs-theme="dark"] { background: #0b0e1a; }

        /* ---- Sidebar kaca ---- */
        .ai-sidebar {
            width: var(--side-w);
            min-height: 100vh;
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 1030;
            color: #e6e8ff;
            background: linear-gradient(170deg, #1a1440 0%, #241d5e 45%, #3b1d6e 100%);
            display: flex;
            flex-direction: column;
            padding: 1.25rem 1rem;
            overflow-y: auto;
        }
        .ai-sidebar::before {
            content: '';
            position: absolute;
            width: 340px; height: 340px;
            top: -120px; right: -120px;
            background: radial-gradient(circle, rgba(217,70,239,.45), transparent 65%);
            pointer-events: none;
        }
        .ai-brand {
            display: flex; align-items: center; gap: .7rem;
            color: #fff; text-decoration: none;
            padding: .4rem .5rem 1.1rem;
            position: relative;
        }
        .ai-brand:hover { color: #fff; }
        .ai-logo {
            width: 42px; height: 42px; border-radius: 14px;
            background: var(--ai-grad);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; color: #fff; flex-shrink: 0;
            box-shadow: 0 8px 20px rgba(139,92,246,.45);
        }
        .ai-brand small { display: block; color: #b9bdf5; font-size: .72rem; letter-spacing: .06em; text-transform: uppercase; }
        .ai-brand strong { font-size: 1.02rem; }
        .ai-nav-label {
            font-size: .68rem; letter-spacing: .14em; text-transform: uppercase;
            color: #8f96d8; padding: 1rem .6rem .35rem;
        }
        .ai-link {
            display: flex; align-items: center; gap: .7rem;
            color: #c9cdf7; text-decoration: none;
            padding: .62rem .8rem; border-radius: .8rem;
            font-weight: 500; font-size: .92rem;
            border: 1px solid transparent;
            margin-bottom: .15rem;
            position: relative;
        }
        .ai-link i { font-size: 1.05rem; width: 1.4rem; text-align: center; }
        .ai-link:hover { color: #fff; background: rgba(255,255,255,.08); }
        .ai-link.active {
            color: #fff;
            background: rgba(255,255,255,.14);
            border-color: rgba(255,255,255,.16);
            box-shadow: 0 6px 18px rgba(0,0,0,.25);
        }
        .ai-link.active::before {
            content: ''; position: absolute; left: -.95rem; top: 20%;
            width: 4px; height: 60%; border-radius: 4px; background: var(--ai-3);
        }
        .ai-side-foot {
            margin-top: auto; padding-top: 1rem;
            border-top: 1px solid rgba(255,255,255,.12);
        }
        .ai-user-chip {
            display: flex; align-items: center; gap: .6rem;
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: .9rem; padding: .55rem .7rem;
            color: #fff; text-decoration: none;
        }
        .ai-user-chip:hover { color: #fff; background: rgba(255,255,255,.13); }
        .ai-avatar {
            width: 36px; height: 36px; border-radius: 50%;
            background: var(--ai-grad);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; flex-shrink: 0;
        }

        /* ---- Area utama ---- */
        .ai-main { margin-left: var(--side-w); min-height: 100vh; display: flex; flex-direction: column; }
        .ai-topbar {
            position: sticky; top: 0; z-index: 1020;
            backdrop-filter: blur(14px);
            background: rgba(255,255,255,.75);
            border-bottom: 1px solid rgba(15,20,50,.08);
        }
        [data-bs-theme="dark"] .ai-topbar { background: rgba(11,14,26,.75); border-color: rgba(255,255,255,.08); }
        .ai-page-title { font-weight: 800; font-size: 1.25rem; margin: 0; }
        .ai-page-sub { font-size: .82rem; color: var(--bs-secondary-color); margin: 0; }
        .ai-icon-btn {
            width: 40px; height: 40px; border-radius: 12px;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .ai-content { padding: 1.5rem; flex: 1; }
        .ai-card {
            border: 0; border-radius: var(--radius);
            box-shadow: 0 10px 30px rgba(35,30,90,.08);
        }
        [data-bs-theme="dark"] .ai-card { box-shadow: 0 10px 30px rgba(0,0,0,.4); }
        .ai-foot {
            padding: 1rem 1.5rem; font-size: .82rem;
            color: var(--bs-secondary-color);
            display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap;
        }
        .grad-text {
            background: var(--ai-grad);
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .btn-ai { background: var(--ai-grad); border: 0; color: #fff; font-weight: 600; }
        .btn-ai:hover { color: #fff; filter: brightness(1.08); }

        @media (max-width: 991.98px) {
            .ai-sidebar { transform: translateX(-105%); transition: transform .25s ease; }
            body.side-open .ai-sidebar { transform: none; }
            body.side-open .ai-backdrop { display: block; }
            .ai-main { margin-left: 0; }
            .ai-backdrop {
                display: none; position: fixed; inset: 0; z-index: 1025;
                background: rgba(5,5,20,.5); backdrop-filter: blur(2px);
            }
        }
    </style>
    <?= $this->renderSection('styles') ?>
</head>
<body>
<div class="ai-backdrop" id="aiBackdrop"></div>

<!-- ============ SIDEBAR ============ -->
<aside class="ai-sidebar">
    <a href="<?= site_url('dashboard') ?>" class="ai-brand">
        <span class="ai-logo"><i class="bi bi-cpu"></i></span>
        <span>
            <small>AI Platform</small>
            <strong>Coding Assistant</strong>
        </span>
    </a>

    <div class="ai-nav-label">Menu</div>
    <nav>
        <a href="<?= site_url('dashboard') ?>" class="ai-link <?= uri_string() === 'dashboard' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <a href="<?= site_url('chat') ?>" class="ai-link <?= str_starts_with(uri_string(), 'chat') ? 'active' : '' ?>">
            <i class="bi bi-chat-dots"></i> AI Chat
        </a>
        <a href="<?= site_url('profile') ?>" class="ai-link <?= uri_string() === 'profile' ? 'active' : '' ?>">
            <i class="bi bi-person"></i> Profil
        </a>
    </nav>

    <?php if (session()->get('user_role') === 'admin'): ?>
    <div class="ai-nav-label">Admin</div>
    <nav>
        <a href="<?= site_url('admin/skills') ?>" class="ai-link <?= str_starts_with(uri_string(), 'admin/skills') ? 'active' : '' ?>">
            <i class="bi bi-gear-wide-connected"></i> Manajemen Skill
        </a>
        <a href="<?= site_url('admin/users') ?>" class="ai-link <?= uri_string() === 'admin/users' ? 'active' : '' ?>">
            <i class="bi bi-people"></i> Manajemen User
        </a>
        <a href="<?= site_url('admin/settings') ?>" class="ai-link <?= uri_string() === 'admin/settings' ? 'active' : '' ?>">
            <i class="bi bi-sliders"></i> Pengaturan AI
        </a>
        <a href="<?= site_url('admin/api') ?>" class="ai-link <?= uri_string() === 'admin/api' ? 'active' : '' ?>">
            <i class="bi bi-plug"></i> API &amp; Integrasi
        </a>
        <a href="<?= site_url('admin/api/modules') ?>" class="ai-link <?= str_starts_with(uri_string(), 'admin/api/modules') ? 'active' : '' ?>">
            <i class="bi bi-grid"></i> Modul Asisten
        </a>
    </nav>
    <?php endif; ?>

    <div class="ai-side-foot">
        <a href="<?= site_url('profile') ?>" class="ai-user-chip mb-2">
            <span class="ai-avatar"><?= strtoupper(substr(session()->get('full_name') ?? 'U', 0, 1)) ?></span>
            <span class="flex-grow-1">
                <strong class="d-block" style="font-size:.85rem;"><?= esc(session()->get('full_name')) ?></strong>
                <small style="color:#b9bdf5;"><?= ucfirst(session()->get('user_role')) ?></small>
            </span>
        </a>
        <a href="<?= site_url('logout') ?>" class="ai-link">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</aside>

<!-- ============ MAIN ============ -->
<div class="ai-main">
    <div class="ai-topbar">
        <div class="d-flex align-items-center gap-2 px-3 py-2">
            <button class="btn btn-light ai-icon-btn d-lg-none" id="btnSidebar" type="button">
                <i class="bi bi-list"></i>
            </button>
            <div class="flex-grow-1">
                <h1 class="ai-page-title"><?= $title ?? 'Dashboard' ?></h1>
                <p class="ai-page-sub d-none d-sm-block">AI Coding Assistant · <span class="grad-text fw-semibold">qwen-coder</span></p>
            </div>
            <button class="btn btn-light ai-icon-btn" id="btnTheme" type="button" title="Mode gelap/terang">
                <i class="bi bi-moon-stars" id="themeIcon"></i>
            </button>
            <div class="dropdown">
                <button class="btn btn-light ai-icon-btn" data-bs-toggle="dropdown" type="button">
                    <i class="bi bi-person-circle"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li><h6 class="dropdown-header"><?= esc(session()->get('full_name')) ?></h6></li>
                    <li><a class="dropdown-item" href="<?= site_url('profile') ?>"><i class="bi bi-person me-2"></i>Profil</a></li>
                    <?php if (session()->get('user_role') === 'admin'): ?>
                    <li><a class="dropdown-item" href="<?= site_url('admin/users') ?>"><i class="bi bi-people me-2"></i>Manajemen User</a></li>
                    <li><a class="dropdown-item" href="<?= site_url('admin/settings') ?>"><i class="bi bi-sliders me-2"></i>Pengaturan AI</a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= site_url('logout') ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="ai-content container-fluid">
        <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success alert-dismissible fade show ai-card" role="alert">
            <i class="bi bi-check-circle me-2"></i><?= session()->getFlashdata('success') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show ai-card" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?= session()->getFlashdata('error') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?= $this->renderSection('content') ?>
    </div>

    <footer class="ai-foot">
        <span><strong>AI Coding Assistant</strong> © <?= date('Y') ?></span>
        <span>Powered by Ollama &amp; CodeIgniter 4</span>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const root = document.documentElement;
    const saved = localStorage.getItem('ai-theme');
    if (saved) root.setAttribute('data-bs-theme', saved);
    const icon = document.getElementById('themeIcon');
    const syncIcon = () => {
        if (!icon) return;
        icon.className = root.getAttribute('data-bs-theme') === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
    };
    syncIcon();
    document.getElementById('btnTheme')?.addEventListener('click', () => {
        const next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-bs-theme', next);
        localStorage.setItem('ai-theme', next);
        syncIcon();
    });
    document.getElementById('btnSidebar')?.addEventListener('click', () => document.body.classList.toggle('side-open'));
    document.getElementById('aiBackdrop')?.addEventListener('click', () => document.body.classList.remove('side-open'));
})();
</script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
