<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>

<h3 class="fw-bold mb-1">Buat akun baru</h3>
<p class="text-secondary mb-4">Daftar untuk mulai memakai AI Coding Assistant</p>

<?php if (session()->getFlashdata('errors')): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
    <?php foreach (session()->getFlashdata('errors') as $error): ?>
        <li><?= $error ?></li>
    <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form action="<?= site_url('register') ?>" method="post">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label fw-semibold">Nama Lengkap</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" name="full_name" class="form-control" placeholder="Nama lengkap" value="<?= old('full_name') ?>" required>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label fw-semibold">Username</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-at"></i></span>
            <input type="text" name="username" class="form-control" placeholder="Username" value="<?= old('username') ?>" required>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label fw-semibold">Email</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" name="email" class="form-control" placeholder="nama@email.com" value="<?= old('email') ?>" required>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label fw-semibold">Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input type="password" name="password" class="form-control" placeholder="Min. 6 karakter" required>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label fw-semibold">Konfirmasi</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                <input type="password" name="password_confirm" class="form-control" placeholder="Ulangi password" required>
            </div>
        </div>
    </div>
    <div class="d-grid">
        <button type="submit" class="btn btn-ai btn-lg"><i class="bi bi-person-plus me-2"></i>Daftar</button>
    </div>
</form>

<p class="mb-0 mt-4 text-center text-secondary">
    Sudah punya akun? <a href="<?= site_url('login') ?>" class="fw-semibold text-decoration-none">Login di sini</a>
</p>

<?= $this->endSection() ?>
