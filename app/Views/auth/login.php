<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>

<h3 class="fw-bold mb-1">Selamat datang kembali</h3>
<p class="text-secondary mb-4">Login untuk mengakses AI Coding Assistant</p>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert alert-success"><?= session()->getFlashdata('success') ?></div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
<div class="alert alert-danger"><?= session()->getFlashdata('error') ?></div>
<?php endif; ?>

<?php if (session()->getFlashdata('errors')): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
    <?php foreach (session()->getFlashdata('errors') as $error): ?>
        <li><?= $error ?></li>
    <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form action="<?= site_url('login') ?>" method="post">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label fw-semibold">Username atau Email</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" name="login" class="form-control" placeholder="cth: admin" value="<?= old('login') ?>" required autofocus>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label fw-semibold">Password</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="form-check">
            <input type="checkbox" id="remember" name="remember" value="1" class="form-check-input">
            <label for="remember" class="form-check-label">Ingat Saya</label>
        </div>
    </div>
    <div class="d-grid">
        <button type="submit" class="btn btn-ai btn-lg"><i class="bi bi-box-arrow-in-right me-2"></i>Login</button>
    </div>
</form>

<p class="mb-0 mt-4 text-center text-secondary small">
    Belum punya akun? Hubungi administrator.
</p>

<?= $this->endSection() ?>
