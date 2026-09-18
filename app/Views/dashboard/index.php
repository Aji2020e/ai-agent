<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="card ai-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="d-inline-flex align-items-center justify-content-center rounded-3 text-white" style="width:52px;height:52px;background:linear-gradient(135deg,#6366f1,#8b5cf6);font-size:1.4rem;"><i class="bi bi-chat-dots"></i></span>
                <div>
                    <h3 class="fw-bold mb-0"><?= $total_sessions ?></h3>
                    <span class="text-secondary">Sesi Chat</span>
                </div>
            </div>
            <a href="<?= site_url('chat') ?>" class="card-footer bg-transparent border-0 text-decoration-none fw-semibold">Buka Chat <i class="bi bi-arrow-right-circle"></i></a>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card ai-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="d-inline-flex align-items-center justify-content-center rounded-3 text-white" style="width:52px;height:52px;background:linear-gradient(135deg,#10b981,#0ea5e9);font-size:1.4rem;"><i class="bi bi-send"></i></span>
                <div>
                    <h3 class="fw-bold mb-0"><?= number_format($total_messages) ?></h3>
                    <span class="text-secondary">Pesan Terkirim</span>
                </div>
            </div>
            <a href="<?= site_url('chat') ?>" class="card-footer bg-transparent border-0 text-decoration-none fw-semibold">Lihat Detail <i class="bi bi-arrow-right-circle"></i></a>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card ai-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="d-inline-flex align-items-center justify-content-center rounded-3 text-white" style="width:52px;height:52px;background:linear-gradient(135deg,#f59e0b,#ef4444);font-size:1.4rem;"><i class="bi bi-cpu"></i></span>
                <div>
                    <h3 class="fw-bold mb-0"><?= number_format($total_tokens) ?></h3>
                    <span class="text-secondary">Token Digunakan</span>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 text-secondary">&nbsp;</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card ai-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="d-inline-flex align-items-center justify-content-center rounded-3 text-white" style="width:52px;height:52px;background:linear-gradient(135deg,#d946ef,#6366f1);font-size:1.4rem;"><i class="bi bi-folder2-open"></i></span>
                <div>
                    <h3 class="fw-bold mb-0"><?= $total_projects ?></h3>
                    <span class="text-secondary">Proyek</span>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 text-secondary">&nbsp;</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card ai-card">
            <div class="card-body p-4">
                <h5 class="fw-bold"><i class="bi bi-rocket-takeoff me-2"></i>Mulai Menggunakan</h5>
                <p class="text-secondary">Pilih jalan pintas di bawah untuk mulai.</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded-4 p-3 h-100">
                            <h6 class="fw-bold"><i class="bi bi-chat-dots me-2"></i>AI Chat</h6>
                            <p class="text-secondary small">Bantuan coding, debugging, dan arsitektur software.</p>
                            <a href="<?= site_url('chat') ?>" class="btn btn-ai btn-sm">Mulai Chat</a>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-4 p-3 h-100">
                            <h6 class="fw-bold"><i class="bi bi-person me-2"></i>Profil</h6>
                            <p class="text-secondary small">Kelola profil dan pengaturan akun Anda.</p>
                            <a href="<?= site_url('profile') ?>" class="btn btn-outline-secondary btn-sm">Lihat Profil</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card ai-card">
            <div class="card-body text-center p-4">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle text-white mb-3" style="width:72px;height:72px;background:linear-gradient(135deg,#6366f1,#d946ef);font-size:2rem;"><i class="bi bi-person"></i></span>
                <h5 class="fw-bold mb-0"><?= esc(session()->get('full_name')) ?></h5>
                <p class="text-secondary">@<?= esc(session()->get('username')) ?></p>
                <ul class="list-group list-group-flush text-start">
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-secondary">Email</span><strong><?= esc(session()->get('email')) ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-secondary">Role</span>
                        <span class="badge text-bg-<?= session()->get('user_role') === 'admin' ? 'danger' : 'primary' ?>"><?= ucfirst(session()->get('user_role')) ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
