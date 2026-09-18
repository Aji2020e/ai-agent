<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="card ai-card">
    <div class="card-body p-4">
        <div class="d-flex align-items-center gap-2 mb-3">
            <span class="d-inline-flex align-items-center justify-content-center rounded-3 text-white" style="width:44px;height:44px;background:linear-gradient(135deg,#6366f1,#d946ef);"><i class="bi bi-people"></i></span>
            <div class="flex-grow-1">
                <h5 class="fw-bold mb-0">Daftar User</h5>
                <small class="text-secondary">Kelola akses dan status akun</small>
            </div>
            <button class="btn btn-ai btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="bi bi-plus-lg me-1"></i>Tambah User</button>
        </div>

        <?php if (session()->getFlashdata('errors')): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
            <?php foreach (session()->getFlashdata('errors') as $error): ?>
                <li><?= $error ?></li>
            <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="modal fade" id="addUserModal" tabindex="-1">
            <div class="modal-dialog">
                <form action="<?= site_url('admin/users/create') ?>" method="post" class="modal-content">
                    <?= csrf_field() ?>
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Tambah User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2">
                            <label class="form-label fw-semibold">Nama Lengkap</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-semibold">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-2">
                                <label class="form-label fw-semibold">Password</label>
                                <input type="password" name="password" class="form-control" minlength="6" required>
                            </div>
                            <div class="col-6 mb-2">
                                <label class="form-label fw-semibold">Role</label>
                                <select name="role" class="form-select">
                                    <option value="user">User</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-2">
                                <label class="form-label fw-semibold">Jenis User</label>
                                <select name="user_type" class="form-select" id="user_type_select">
                                    <option value="guest">Guest</option>
                                    <option value="student">Mahasiswa</option>
                                    <option value="dosen">Dosen</option>
                                    <option value="staff">Staff</option>
                                </select>
                            </div>
                            <div class="col-6 mb-2" id="identifier_field">
                                <label class="form-label fw-semibold" id="identifier_label">NPM/NIP</label>
                                <input type="text" name="identifier" class="form-control" placeholder="Contoh: 22SA11A001">
                            </div>
                            <div class="col-6 mb-2" id="nik_field" style="display:none;">
                                <label class="form-label fw-semibold">NIK Dosen</label>
                                <input type="text" name="nik" class="form-control" placeholder="NIK dosen">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-ai">Simpan</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Username</th>
                        <th>Nama Lengkap</th>
                        <th>Jenis</th>
                        <th>NPM/NIP</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Terdaftar</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (! empty($users)): ?>
                        <?php foreach ($users as $i => $user): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><strong><?= esc($user['username']) ?></strong></td>
                            <td><?= esc($user['full_name']) ?></td>
                            <td><?= ucfirst($user['user_type'] ?? 'Guest') ?></td>
                            <td><?= esc($user['identifier'] ?: '-') ?></td>
                            <td>
                                <span class="badge text-bg-<?= $user['role'] === 'admin' ? 'danger' : 'primary' ?>">
                                    <?= ucfirst($user['role']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge text-bg-<?= $user['is_active'] ? 'success' : 'secondary' ?>">
                                    <?= $user['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                                </span>
                            </td>
                            <td><?= $user['last_login'] ? date('d M Y H:i', strtotime($user['last_login'])) : '-' ?></td>
                            <td><?= date('d M Y', strtotime($user['created_at'])) ?></td>
                            <td>
                                <?php if ($user['id'] !== session()->get('user_id')): ?>
                                <form action="<?= site_url('admin/users/toggle-status/' . $user['id']) ?>" method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-<?= $user['is_active'] ? 'warning' : 'success' ?>"
                                            title="<?= $user['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                        <i class="bi bi-<?= $user['is_active'] ? 'slash-circle' : 'check-circle' ?>"></i>
                                    </button>
                                </form>
                                <form action="<?= site_url('admin/users/delete/' . $user['id']) ?>" method="post" class="d-inline"
                                      onsubmit="return confirm('Yakin hapus user <?= esc($user['username']) ?>?')">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-danger" title="Hapus">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                <span class="text-secondary">Anda</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="10" class="text-center text-secondary">Tidak ada data user.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?php $this->section('scripts') ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const select = document.getElementById('user_type_select');
        const identifierField = document.getElementById('identifier_field');
        const nikField = document.getElementById('nik_field');
        const label = document.getElementById('identifier_label');

        function toggle() {
            if (select.value === 'dosen') {
                identifierField.style.display = 'none';
                nikField.style.display = 'block';
            } else {
                identifierField.style.display = 'block';
                nikField.style.display = 'none';
                label.textContent = select.value === 'staff' ? 'NIP' : 'NPM/NIP';
            }
        }

        select.addEventListener('change', toggle);
        toggle();
    });
</script>
<?= $this->endSection() ?>
