<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="card ai-card">
    <div class="card-body p-4">
        <div class="d-flex align-items-center gap-2 mb-3">
            <span class="d-inline-flex align-items-center justify-content-center rounded-3 text-white" style="width:44px;height:44px;background:linear-gradient(135deg,#6366f1,#d946ef);"><i class="bi bi-shield-lock"></i></span>
            <div class="flex-grow-1">
                <h5 class="fw-bold mb-0">Role Skill</h5>
                <small class="text-secondary">Atur skill yang boleh diakses tiap role user</small>
            </div>
            <a href="<?= site_url('admin/skills') ?>" class="btn btn-outline-secondary btn-sm">Kembali</a>
        </div>

        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success"><?= session()->getFlashdata('success') ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger"><?= session()->getFlashdata('error') ?></div>
        <?php endif; ?>

        <form action="<?= site_url('admin/skills/roles/save') ?>" method="post">
            <?= csrf_field() ?>

            <div class="row g-4">
                <?php
                $roleLabels = [
                    'student' => 'Mahasiswa',
                    'dosen'   => 'Dosen',
                    'staff'   => 'Staff',
                    'admin'   => 'Admin',
                ];
                ?>

                <?php foreach ($roles as $role): ?>
                    <div class="col-md-6">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-header bg-white fw-bold text-capitalize"><?= ucfirst($roleLabels[$role] ?? $role) ?></div>
                            <div class="card-body">
                                <?php if ($role === 'admin'): ?>
                                    <p class="text-muted small mb-2">Admin memiliki akses ke semua skill.</p>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" checked disabled>
                                        <label class="form-check-label text-muted">Semua skill</label>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted small mb-2">Pilih skill yang boleh diakses role ini.</p>
                                    <?php foreach ($skills as $skill): ?>
                                        <?php $checked = in_array($skill['name'], $roleSkills[$role], true) ? 'checked' : ''; ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="roles[<?= $role ?>][]" value="<?= esc($skill['name']) ?>" id="<?= $role ?>_<?= $skill['name'] ?>" <?= $checked ?>
                                                <?= $skill['is_active'] ? '' : 'disabled' ?>>
                                            <label class="form-check-label" for="<?= $role ?>_<?= $skill['name'] ?>">
                                                <?= esc($skill['display_name'] ?: $skill['name']) ?>
                                                <?php if (! $skill['is_active']): ?>
                                                    <span class="badge bg-secondary text-white">nonaktif</span>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-ai"><i class="bi bi-save me-1"></i> Simpan Mapping</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
