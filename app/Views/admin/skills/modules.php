<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Modul Skill: <?= esc($skill['display_name']) ?></h3>
    </div>
    <div class="card-body">
        <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success"><?= session()->getFlashdata('success') ?></div>
        <?php endif; ?>

        <form action="/admin/skills/<?= $skill['id'] ?>/modules/save" method="post" class="mb-4">
            <?= csrf_field() ?>
            <h5>Tambah/Edit Modul</h5>
            <div class="mb-3">
                <label>Nama Modul</label>
                <input type="text" name="module_name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Deskripsi</label>
                <textarea name="description" class="form-control" rows="3"></textarea>
            </div>
            <div class="mb-3">
                <label>Config (JSON)</label>
                <textarea name="module_config" class="form-control" rows="3" placeholder='{&quot;key&quot;: &quot;value&quot;}'></textarea>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" name="is_active" class="form-check-input" value="1" checked>
                <label class="form-check-label">Aktif</label>
            </div>
            <button type="submit" class="btn btn-primary">Simpan Modul</button>
        </form>

        <hr>

        <h5>Daftar Modul</h5>
        <table class="table table-sm table-bordered">
            <thead>
                <tr>
                    <th>Nama Modul</th>
                    <th>Deskripsi</th>
                    <th>Config</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($modules as $module): ?>
                <tr>
                    <td><?= esc($module['module_name']) ?></td>
                    <td><?= esc($module['description']) ?></td>
                    <td><code><?= esc($module['module_config']) ?></code></td>
                    <td>
                        <span class="badge bg-<?= $module['is_active'] ? 'success' : 'secondary' ?>">
                            <?= $module['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
