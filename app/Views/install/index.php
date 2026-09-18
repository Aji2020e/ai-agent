<!DOCTYPE html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <meta name="csrf-name" content="<?= csrf_token() ?>">
    <title>Instalasi - AI Coding Assistant</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        * { font-family: 'Inter', system-ui, sans-serif; }
        body { min-height: 100vh; background: linear-gradient(150deg, #1a1440 0%, #3b1d6e 50%, #7c2d8e 85%); display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; }
        .install-card { width: 100%; max-width: 560px; border: 0; border-radius: 1.25rem; box-shadow: 0 24px 70px rgba(0,0,0,.4); overflow: hidden; }
        .install-head { background: linear-gradient(135deg, #6366f1, #8b5cf6 55%, #d946ef); color: #fff; padding: 2rem; }
        .step { display: flex; gap: .8rem; align-items: flex-start; padding: .8rem 1rem; border: 1px solid var(--bs-border-color); border-radius: .9rem; margin-bottom: .6rem; }
        .step .dot { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: var(--bs-tertiary-bg); }
        .step.running .dot { background: #6366f1; color: #fff; }
        .step.ok .dot { background: #198754; color: #fff; }
        .step.fail .dot { background: #dc3545; color: #fff; }
        .btn-ai { background: linear-gradient(135deg, #6366f1, #8b5cf6 55%, #d946ef); border: 0; color: #fff; font-weight: 600; }
        .btn-ai:hover { color: #fff; filter: brightness(1.08); }
        .spinner { width: 1rem; height: 1rem; border: 2px solid rgba(255,255,255,.4); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
<div class="card install-card">
    <div class="install-head text-center">
        <span class="d-inline-flex align-items-center justify-content-center rounded-4 mb-3" style="width:64px;height:64px;background:rgba(255,255,255,.16);font-size:1.8rem;"><i class="bi bi-hdd-network"></i></span>
        <h3 class="fw-bold mb-1">Instalasi AI Coding Assistant</h3>
        <p class="mb-0 opacity-75">Database belum siap — siapkan dalam sekali klik.</p>
    </div>
    <div class="card-body p-4">
        <div id="stepList">
            <div class="step" data-step="0">
                <span class="dot"><i class="bi bi-database"></i></span>
                <div><strong>Database</strong><br><small class="text-secondary step-detail">Buat database bila belum ada.</small></div>
            </div>
            <div class="step" data-step="1">
                <span class="dot"><i class="bi bi-table"></i></span>
                <div><strong>Migrasi</strong><br><small class="text-secondary step-detail">Buat tabel users, chat_history, projects, settings.</small></div>
            </div>
            <div class="step" data-step="2">
                <span class="dot"><i class="bi bi-person-plus"></i></span>
                <div><strong>Data awal</strong><br><small class="text-secondary step-detail">Buat admin default bila masih kosong.</small></div>
            </div>
        </div>

        <div id="resultBox"></div>

        <div class="d-grid mt-3">
            <button class="btn btn-ai btn-lg" id="btnInstall"><i class="bi bi-play-fill me-2"></i>Jalankan Instalasi</button>
            <a href="<?= site_url('login') ?>" class="btn btn-success btn-lg d-none" id="btnLogin"><i class="bi bi-box-arrow-in-right me-2"></i>Selesai — Buka Login</a>
        </div>
        <p class="text-center text-secondary small mt-3 mb-0">Kredensial default: <code>admin / admin123</code> — segera ganti setelah login.</p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const BASE_URL = '<?= rtrim(site_url(), '/') ?>/';
const CSRF_NAME = document.querySelector('meta[name="csrf-name"]')?.content || 'csrf_test_name';
const getCsrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
const setCsrf = (h) => { if (h) document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', h); };

function paintStep(i, state, detail) {
    const el = document.querySelector(`.step[data-step="${i}"]`);
    if (!el) return;
    const dot = el.querySelector('.dot');
    if (!el.dataset.icon) el.dataset.icon = dot.innerHTML;
    el.classList.remove('running', 'ok', 'fail');
    if (state) el.classList.add(state);
    if (state === 'running') dot.innerHTML = '<span class="spinner"></span>';
    else if (state === 'ok') dot.innerHTML = '<i class="bi bi-check-lg"></i>';
    else if (state === 'fail') dot.innerHTML = '<i class="bi bi-x-lg"></i>';
    else dot.innerHTML = el.dataset.icon;
    if (detail) el.querySelector('.step-detail').textContent = detail;
}

document.getElementById('btnInstall').addEventListener('click', async function () {
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner me-2"></span>Memasang...';
    document.getElementById('resultBox').innerHTML = '';
    paintStep(0, 'running', 'Menghubungkan ke server database...');
    paintStep(1, '', 'Menunggu...');
    paintStep(2, '', 'Menunggu...');

    const fd = new FormData();
    fd.append(CSRF_NAME, getCsrf());

    let data;
    try {
        const res = await fetch(BASE_URL + 'install/run', { method: 'POST', body: fd });
        data = await res.json();
    } catch (e) {
        paintStep(0, 'fail', 'Gagal menghubungi server.');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-play-fill me-2"></i>Coba Lagi';
        return;
    }
    if (data.csrf) setCsrf(data.csrf);

    // Tampilkan progres satu per satu agar terbaca
    for (let i = 0; i < (data.steps || []).length; i++) {
        paintStep(i, 'running', data.steps[i].label + '...');
        await new Promise(r => setTimeout(r, 450));
        paintStep(i, data.steps[i].ok ? 'ok' : 'fail', data.steps[i].detail || data.steps[i].label);
        if (!data.steps[i].ok) break;
    }

    const box = document.getElementById('resultBox');
    if (data.success) {
        box.innerHTML = '<div class="alert alert-success mt-3 mb-0"><i class="bi bi-check-circle me-2"></i>Instalasi selesai. Silakan login.</div>';
        btn.classList.add('d-none');
        document.getElementById('btnLogin').classList.remove('d-none');
    } else {
        const msg = (data.steps || []).filter(s => !s.ok).map(s => s.detail || s.label).join(' ');
        box.innerHTML = '<div class="alert alert-danger mt-3 mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Instalasi gagal: ' + msg + '</div>';
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-play-fill me-2"></i>Coba Lagi';
    }
});
</script>
</body>
</html>
