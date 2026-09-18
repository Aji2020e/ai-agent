<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Panduan Skill: <?= esc($skill['display_name']) ?></h3>
    </div>
    <div class="card-body">
        <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success"><?= session()->getFlashdata('success') ?></div>
        <?php endif; ?>

        <form action="/admin/skills/<?= $skill['id'] ?>/guides/save" method="post" class="mb-4">
            <?= csrf_field() ?>
            <h5>Tambah/Edit Panduan</h5>
            <div class="mb-3">
                <label>Judul</label>
                <input type="text" name="title" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Konten (Prompt/System Prompt)</label>
                <textarea name="content" class="form-control" rows="6" required></textarea>
            </div>
            <div class="mb-3">
                <label>Section</label>
                <select name="section" class="form-select">
                    <option value="system_prompt">System Prompt</option>
                    <option value="example">Example</option>
                    <option value="rule">Rule</option>
                </select>
            </div>
            <div class="mb-3">
                <label>Order Index</label>
                <input type="number" name="order_index" class="form-control" value="0">
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" name="is_active" class="form-check-input" value="1" checked>
                <label class="form-check-label">Aktif</label>
            </div>
            <button type="submit" class="btn btn-primary">Simpan Panduan</button>
        </form>

        <hr>

        <h5>Daftar Panduan</h5>
        <table class="table table-sm table-bordered">
            <thead>
                <tr>
                    <th>Judul</th>
                    <th>Section</th>
                    <th>Order</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($guides as $guide): ?>
                <tr>
                    <td><?= esc($guide['title']) ?></td>
                    <td><?= esc($guide['section']) ?></td>
                    <td><?= $guide['order_index'] ?></td>
                    <td>
                        <span class="badge bg-<?= $guide['is_active'] ? 'success' : 'secondary' ?>">
                            <?= $guide['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
