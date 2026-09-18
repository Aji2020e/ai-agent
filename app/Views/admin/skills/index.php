<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="bi bi-gear-wide-connected"></i> Manajemen Skill AI</h3>
        <a href="<?= site_url('admin/skills/roles') ?>" class="btn btn-sm btn-primary float-end">
            <i class="bi bi-shield-lock"></i> Role Skill
        </a>
    </div>
    <div class="card-body">
        <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success"><?= session()->getFlashdata('success') ?></div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Icon</th>
                        <th>Nama</th>
                        <th>Nama Tampilan</th>
                        <th>Handler Class</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($skills as $skill): ?>
                    <tr>
                        <td><i class="bi <?= esc($skill['icon'] ?? 'bi-circle') ?>"></i></td>
                        <td><code><?= esc($skill['name']) ?></code></td>
                        <td><?= esc($skill['display_name']) ?></td>
                        <td><small><?= esc($skill['handler_class']) ?></small></td>
                        <td>
                            <span class="badge bg-<?= $skill['is_active'] ? 'success' : 'secondary' ?>">
                                <?= $skill['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                        </td>
                        <td>
                            <form action="/admin/skills/toggle/<?= $skill['id'] ?>" method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-<?= $skill['is_active'] ? 'warning' : 'success' ?>">
                                    <?= $skill['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                                </button>
                            </form>
                            <a href="/admin/skills/<?= $skill['id'] ?>/guides" class="btn btn-sm btn-info">
                                <i class="bi bi-book"></i> Panduan
                            </a>
                            <a href="/admin/skills/<?= $skill['id'] ?>/modules" class="btn btn-sm btn-primary">
                                <i class="bi bi-puzzle"></i> Modul
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
