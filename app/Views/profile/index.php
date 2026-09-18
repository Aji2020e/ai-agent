<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card ai-card">
            <div class="card-body text-center p-4">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle text-white mb-3" style="width:72px;height:72px;background:linear-gradient(135deg,#6366f1,#d946ef);font-size:2rem;"><i class="bi bi-person"></i></span>
                <h5 class="fw-bold mb-0"><?= esc($user['full_name']) ?></h5>
                <p class="text-secondary">@<?= esc($user['username']) ?></p>
                <ul class="list-group list-group-flush text-start">
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-secondary">Email</span><strong><?= esc($user['email']) ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-secondary">Role</span>
                        <span class="badge text-bg-<?= $user['role'] === 'admin' ? 'danger' : 'primary' ?>"><?= ucfirst($user['role']) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-secondary">Terdaftar</span><span><?= date('d M Y', strtotime($user['created_at'])) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-secondary">Last Login</span><span><?= $user['last_login'] ? date('d M Y H:i', strtotime($user['last_login'])) : '-' ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card ai-card mb-3">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-pencil-square me-2"></i>Edit Profil</h5>
                <?php if (session()->getFlashdata('errors')): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                    <?php foreach (session()->getFlashdata('errors') as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <form action="<?= site_url('profile/update') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Lengkap</label>
                        <input type="text" name="full_name" class="form-control" value="<?= esc($user['full_name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= esc($user['email']) ?>" required>
                    </div>
                    <button type="submit" class="btn btn-ai">Simpan Perubahan</button>
                </form>
            </div>
        </div>

        <div class="card ai-card">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-key me-2"></i>Ubah Password</h5>
                <form action="<?= site_url('profile/change-password') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password Saat Ini</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password Baru</label>
                        <input type="password" name="new_password" class="form-control" minlength="6" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Konfirmasi Password Baru</label>
                        <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                    </div>
                    <button type="submit" class="btn btn-warning">Ubah Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
