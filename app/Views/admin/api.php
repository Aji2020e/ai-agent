<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php
// Default defensif: view ini bisa dirender tanpa data kebijakan bila tabel
// belum dimigrasi. Jangan sampai halaman admin error hanya karena itu.
$policies       = $policies ?? [];
$violations     = $violations ?? [];
$violationTally = $violationTally ?? [];
?>

<?php if (! empty($newKey)): ?>
<div class="alert alert-warning ai-card">
    <i class="bi bi-key me-2"></i><strong>API Key baru (salin sekarang!):</strong>
    <code id="newKeyText"><?= esc($newKey) ?></code>
    <button class="btn btn-sm btn-outline-dark ms-2" id="btnCopyKey"><i class="bi bi-clipboard"></i></button>
</div>
<?php endif; ?>

<div class="row g-3">
    <!-- Klien API -->
    <div class="col-lg-6">
        <div class="card ai-card h-100">
            <div class="card-body p-4">
                <h5 class="fw-bold"><i class="bi bi-key me-2"></i>Klien API</h5>
                <p class="text-secondary small">Aplikasi akademik memanggil <code>/api/*</code> dengan header <code>X-API-Key</code> (120 req/mnt/key). HMAC opsional anti-replay.</p>
                <form action="<?= site_url('admin/api/clients') ?>" method="post" class="row g-2 mb-3">
                    <?= csrf_field() ?>
                    <div class="col-md-4 col-12">
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="Nama aplikasi (mis. siakad)" required maxlength="100">
                    </div>
                    <div class="col-md-3 col-6">
                        <select name="modules[]" class="form-select form-select-sm" multiple title="Kosongkan = semua modul">
                            <?php foreach ($modules as $m): ?>
                            <option value="<?= $m ?>"><?= ucfirst($m) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 col-6">
                        <select name="skills[]" class="form-select form-select-sm" multiple title="Kosongkan = semua skill">
                            <?php foreach ($skills as $skillKey => $skillLabel): ?>
                            <option value="<?= $skillKey ?>"><?= esc($skillLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 col-6">
                        <input type="number" name="expiry_days" class="form-control form-control-sm" placeholder="Hari" title="Masa berlaku (hari), kosong = selamanya" min="1">
                    </div>
                    <div class="col-md-2 col-6 d-grid">
                        <button class="btn btn-ai btn-sm" title="Buat"><i class="bi bi-plus-lg"></i></button>
                    </div>
                    <div class="col-12">
                        <input type="text" name="ips" class="form-control form-control-sm" placeholder="IP allowlist koma (mis. 172.16.15.10, 10.0.0.0/24) — kosong = semua">
                    </div>
                    <div class="col-12">
                        <select name="model" class="form-select form-select-sm model-select" data-current="">
                            <option value="">Memuat daftar model…</option>
                        </select>
                        <div class="form-text">Model khusus klien ini — kosong = ikut default global. Ketik untuk mencari.</div>
                    </div>
                </form>
                <div class="table-responsive" style="overflow-x: auto;">
                    <table class="table table-sm table-hover align-middle" style="min-width: 1100px; table-layout: auto;">
                        <thead class="table-light"><tr><th>Nama</th><th>Modul</th><th>Skill</th><th>Wewenang</th><th>Keamanan</th><th>Status</th><th>Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($clients as $c): ?>
                            <tr>
                                <td><strong><?= esc($c['name']) ?></strong></td>
                                <td><code><?= esc($c['modules']) ?></code></td>
                                <td><code><?= esc($c['skills'] ?? '*') ?></code><br><small class="text-secondary">model: <code><?= esc($c['model'] ?? '') !== '' ? $c['model'] : 'default' ?></code></small>
                                    <form action="<?= site_url('admin/api/clients/model/' . $c['id']) ?>" method="post" class="d-flex gap-1 mt-1"><?= csrf_field() ?>
                                        <select name="model" class="form-select form-select-sm model-select" style="max-width:170px" data-current="<?= esc($c['model'] ?? '') ?>">
                                            <option value="">Memuat…</option>
                                        </select>
                                        <button class="btn btn-sm btn-outline-primary" title="Simpan model"><i class="bi bi-check-lg"></i></button>
                                    </form>
                                </td>
                                <?php $pol = $policies[$c['id']] ?? null; ?>
                                <td>
                                    <?php if ($pol === null): ?>
                                        <span class="badge text-bg-danger">tanpa kebijakan</span><br>
                                        <small class="text-secondary">semua data ditolak</small>
                                    <?php else: ?>
                                        <?php
                                            $scopeBadge = [
                                                'self' => ['primary', 'diri sendiri'],
                                                'unit' => ['info',    'unit: ' . esc($pol['unit_type'] ?? '') . ' ' . esc($pol['unit_ids'] ?? '')],
                                                'role' => ['secondary','peran: ' . esc($pol['role_scope'] ?? '')],
                                                'all'  => ['dark',    'semua baris'],
                                            ];
                                            [$bc, $bl] = $scopeBadge[$pol['scope']] ?? ['secondary', $pol['scope']];
                                        ?>
                                        <span class="badge text-bg-<?= $bc ?>"><?= $bl ?></span>
                                        <?php if (($pol['on_violation'] ?? '') === 'log_only'): ?>
                                            <span class="badge text-bg-warning" title="Pelanggaran dicatat tapi belum ditolak">pantau</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-success" title="Pelanggaran ditolak">tegak</span>
                                        <?php endif; ?>
                                        <br><small class="text-secondary">
                                            baris <?= (int) ($pol['row_limit'] ?? 0) ?> · query <?= (int) ($pol['query_budget'] ?? 0) ?>
                                            · tool: <code><?= esc($pol['tools_allowed'] ?? '') !== '' ? $pol['tools_allowed'] : 'tidak ada' ?></code>
                                        </small>
                                    <?php endif; ?>
                                    <br>
                                    <button class="btn btn-sm btn-outline-primary mt-1" data-bs-toggle="modal"
                                            data-bs-target="#policyModal<?= (int) $c['id'] ?>">
                                        <i class="bi bi-shield-check me-1"></i>Kebijakan
                                    </button>
                                </td>
                                <td><small>
                                    <?= ! empty($c['require_hmac']) ? '<span class="badge text-bg-success">HMAC</span>' : '<span class="badge text-bg-secondary">-</span>' ?>
                                </small></td>
                                <td><span class="badge text-bg-<?= $c['is_active'] ? 'success' : 'secondary' ?>"><?= $c['is_active'] ? 'Aktif' : 'Mati' ?></span></td>
                                <td class="text-nowrap">
                                    <form action="<?= site_url('admin/api/clients/hmac/' . $c['id']) ?>" method="post" class="d-inline" onsubmit="return confirm('Aktif/matikan HMAC? (secret baru bila diaktifkan)')"><?= csrf_field() ?>
                                        <button class="btn btn-sm btn-info" title="HMAC"><i class="bi bi-shield-lock"></i></button></form>
                                    <form action="<?= site_url('admin/api/clients/toggle/' . $c['id']) ?>" method="post" class="d-inline"><?= csrf_field() ?>
                                        <button class="btn btn-sm btn-warning" title="Aktif/mati"><i class="bi bi-power"></i></button></form>
                                    <form action="<?= site_url('admin/api/clients/delete/' . $c['id']) ?>" method="post" class="d-inline" onsubmit="return confirm('Hapus klien?')"><?= csrf_field() ?>
                                        <button class="btn btn-sm btn-danger" title="Hapus"><i class="bi bi-trash"></i></button></form>
                                </td>
                            </tr>
                            <tr class="table-light">
                                <td colspan="7" class="p-0">
                                    <div class="p-2">
                                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                            <strong class="small"><i class="bi bi-key me-1"></i>API Keys</strong>
                                            <form action="<?= site_url('admin/api/clients/key/' . $c['id']) ?>" method="post" class="d-flex flex-wrap gap-1">
                                                <?= csrf_field() ?>
                                                <input type="number" name="expiry_days" class="form-control form-control-sm" placeholder="Hari" style="width:70px" title="Masa berlaku (hari), kosong = selamanya" min="1">
                                                <input type="text" name="ips" class="form-control form-control-sm" placeholder="IP allowlist" style="width:140px" title="Kosong = semua IP">
                                                <button class="btn btn-sm btn-success"><i class="bi bi-plus me-1"></i>Key</button>
                                            </form>
                                        </div>
                                        <div class="table-responsive" style="overflow-x: auto;">
                                            <table class="table table-sm table-bordered mb-0" style="min-width: 700px;">
                                                <thead class="table-light"><tr><th>Prefix</th><th>Status</th><th>Exp</th><th>IP Allowlist</th><th>Last used</th><th>Aksi</th></tr></thead>
                                                <tbody>
                                                <?php foreach (($keys[$c['id']] ?? []) as $k): ?>
                                                    <tr>
                                                        <td><code><?= esc($k['key_prefix']) ?></code></td>
                                                        <td><span class="badge text-bg-<?= $k['is_active'] ? 'success' : 'secondary' ?>"><?= $k['is_active'] ? 'Aktif' : 'Mati' ?></span></td>
                                                        <td><small><?= esc($k['expires_at'] ?? '-') ?></small></td>
                                                        <td><code><?= esc($k['ip_allowlist'] ?? '-') ?></code></td>
                                                        <td><small><?= $k['last_used'] ?? '-' ?></small></td>
                                                        <td class="text-nowrap">
                                                            <form action="<?= site_url('admin/api/clients/key/toggle/' . $k['id']) ?>" method="post" class="d-inline"><?= csrf_field() ?>
                                                                <button class="btn btn-sm btn-warning" title="Aktif/mati"><i class="bi bi-power"></i></button></form>
                                                            <form action="<?= site_url('admin/api/clients/key/revoke/' . $k['id']) ?>" method="post" class="d-inline" onsubmit="return confirm('Cabut key ini?')"><?= csrf_field() ?>
                                                                <button class="btn btn-sm btn-danger" title="Cabut"><i class="bi bi-trash"></i></button></form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($keys[$c['id']])): ?>
                                                    <tr><td colspan="6" class="text-center text-secondary">Belum ada key.</td></tr>
                                                <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($clients)): ?>
                            <tr><td colspan="7" class="text-center text-secondary">Belum ada klien.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Koneksi akademik -->
    <div class="col-lg-6">
        <div class="card ai-card h-100">
            <div class="card-body p-4">
                <h5 class="fw-bold"><i class="bi bi-database me-2"></i>Database Akademik <span class="badge text-bg-secondary">read-only</span></h5>
                <div id="akadStatus" class="mb-2">
                    <div class="alert alert-secondary py-2 mb-0"><i class="bi bi-hourglass-split me-2"></i>Mengecek koneksi...</div>
                </div>
                <form action="<?= site_url('admin/api/akademik') ?>" method="post" class="row g-2">
                    <?= csrf_field() ?>
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold">Host</label>
                        <input type="text" name="akad_host" class="form-control form-control-sm" value="<?= esc($akad['hostname']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Port</label>
                        <input type="number" name="akad_port" class="form-control form-control-sm" value="<?= esc($akad['port']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Database</label>
                        <input type="text" name="akad_db" class="form-control form-control-sm" value="<?= esc($akad['database']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Driver</label>
                        <select name="akad_driver" class="form-select form-select-sm">
                            <option value="SQLSRV" <?= $akad['DBDriver'] === 'SQLSRV' ? 'selected' : '' ?>>SQL Server</option>
                            <option value="MySQLi" <?= $akad['DBDriver'] === 'MySQLi' ? 'selected' : '' ?>>MySQL/MariaDB</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Username <small class="text-secondary">(hak SELECT saja!)</small></label>
                        <input type="text" name="akad_user" class="form-control form-control-sm" value="<?= esc($akad['username']) ?>" required autocomplete="off">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Password <?= $akad['password'] !== '' ? '<span class="badge text-bg-success">tersimpan</span>' : '' ?></label>
                        <input type="password" name="akad_pass" class="form-control form-control-sm" placeholder="Kosongkan = pakai lama" autocomplete="new-password">
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-ai btn-sm"><i class="bi bi-save me-1"></i>Simpan</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnTestAkad"><i class="bi bi-lightning-charge me-1"></i>Tes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Dokumen panduan -->
    <div class="col-lg-6">
        <div class="card ai-card h-100">
            <div class="card-body p-4">
                <h5 class="fw-bold"><i class="bi bi-file-text me-2"></i>Panduan Akademik</h5>
                <p class="text-secondary small">Teks panduan dibaca AI sebagai konteks (modul + umum).</p>
                <form action="<?= site_url('admin/api/docs') ?>" method="post" class="row g-2 mb-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" id="docId" value="0">
                    <div class="col-md-4">
                        <select name="module" id="docModule" class="form-select form-select-sm">
                            <?php foreach ($docModules as $m): ?>
                            <option value="<?= $m ?>"><?= ucfirst($m) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <input type="text" name="title" id="docTitle" class="form-control form-control-sm" placeholder="Judul (mis. Panduan KRS 2025)" required>
                    </div>
                    <div class="col-12">
                        <textarea name="content" id="docContent" class="form-control form-control-sm" rows="3" placeholder="Isi panduan..." required></textarea>
                    </div>
                    <div class="col-12 d-flex gap-2 align-items-center">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" value="1" id="docActive" class="form-check-input" checked>
                            <label for="docActive" class="form-check-label small">Aktif</label>
                        </div>
                        <button class="btn btn-ai btn-sm"><i class="bi bi-save me-1"></i>Simpan Dokumen</button>
                    </div>
                </form>
                <form action="<?= site_url('admin/api/docs/upload') ?>" method="post" enctype="multipart/form-data" class="row g-2 mb-3">
                    <?= csrf_field() ?>
                    <div class="col-md-3">
                        <select name="module" class="form-select form-select-sm">
                            <?php foreach ($docModules as $m): ?>
                            <option value="<?= $m ?>"><?= ucfirst($m) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="title" class="form-control form-control-sm" placeholder="Judul (opsional untuk file tunggal)">
                    </div>
                    <div class="col-md-4">
                        <input type="file" name="guide_files[]" class="form-control form-control-sm" required multiple accept=".txt,.md,.markdown,.csv,.json,.xml,.html,.htm,.yml,.yaml,.ini,.log,.doc,.docx,.rtf,.pdf,.png,.jpg,.jpeg,.webp,.gif,.bmp,.tif,.tiff">
                    </div>
                    <div class="col-12 d-flex gap-2 align-items-center">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="docUploadActive" checked>
                            <label for="docUploadActive" class="form-check-label small">Aktif</label>
                        </div>
                        <button class="btn btn-outline-primary btn-sm"><i class="bi bi-upload me-1"></i>Upload Panduan</button>
                        <small class="text-secondary">Multi-file. Format: doc/docx/pdf/gambar/txt/md/csv/json/xml/html/yml/rtf (maks 10 MB/file).</small>
                    </div>
                </form>
                <ul class="list-group list-group-flush">
                <?php foreach ($docs as $d): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-start px-0">
                        <div><span class="badge text-bg-primary"><?= esc($d['module']) ?></span>
                            <strong><?= esc($d['title']) ?></strong>
                            <?= $d['is_active'] ? '' : '<span class="badge text-bg-secondary">nonaktif</span>' ?>
                            <br><small class="text-secondary"><?= mb_strlen($d['content']) ?> karakter</small></div>
                        <form action="<?= site_url('admin/api/docs/delete/' . $d['id']) ?>" method="post" onsubmit="return confirm('Hapus?')"><?= csrf_field() ?>
                            <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button></form>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($docs)): ?>
                    <li class="list-group-item text-secondary">Belum ada dokumen.</li>
                <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Log -->
    <div class="col-lg-6">
        <div class="card ai-card h-100">
            <div class="card-body p-4">
                <h5 class="fw-bold"><i class="bi bi-clock-history me-2"></i>Log API (30 terakhir)</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light"><tr><th>Waktu</th><th>Klien</th><th>Endpoint</th><th>Token</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td><small><?= esc($l['created_at']) ?></small></td>
                                <td><?= esc($l['client_name'] ?? '-') ?></td>
                                <td><code><?= esc($l['endpoint']) ?></code></td>
                                <td><?= $l['tokens_used'] ?></td>
                                <td><span class="badge text-bg-<?= $l['status'] === 'ok' ? 'success' : 'danger' ?>"><?= esc($l['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="5" class="text-center text-secondary">Belum ada.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Kamus Data -->
<div class="card ai-card mt-3">
    <div class="card-body p-4">
        <h5 class="fw-bold"><i class="bi bi-book me-2"></i>Kamus Data <small class="text-secondary fw-normal">— tabel/kolom resmi yang boleh dibaca AI (topik <code>*</code> selalu ikut)</small></h5>
        <form action="<?= site_url('admin/api/dict') ?>" method="post" class="row g-2 my-3">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="0">
            <div class="col-md-2">
                <select name="module" class="form-select form-select-sm">
                    <option value="umum">umum</option>
                    <option value="mahasiswa">mahasiswa</option>
                    <option value="dosen">dosen</option>
                    <option value="staff">staff</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" name="topik" class="form-control form-control-sm" placeholder="Topik koma (mis. nilai, KRS) atau *">
            </div>
            <div class="col-md-2">
                <input type="text" name="tabel" class="form-control form-control-sm" placeholder="Tabel (boleh kosong)">
            </div>
            <div class="col-md-5">
                <input type="text" name="kolom" class="form-control form-control-sm" placeholder="Kolom: KODE=kode MK, ...">
            </div>
            <div class="col-12">
                <textarea name="deskripsi" class="form-control form-control-sm" rows="2" placeholder="Deskripsi/aturan (mis. JANGAN pakai tabel X)"></textarea>
            </div>
            <div class="col-12 d-flex gap-2 align-items-center">
                <div class="form-check">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" checked id="dictActive">
                    <label for="dictActive" class="form-check-label small">Aktif</label>
                </div>
                <button class="btn btn-ai btn-sm"><i class="bi bi-save me-1"></i>Simpan Entri</button>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead class="table-light"><tr><th>Modul</th><th>Topik</th><th>Tabel</th><th>Kolom/Deskripsi</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($dict as $d): ?>
                    <tr>
                        <td><span class="badge text-bg-primary"><?= esc($d['module']) ?></span></td>
                        <td><code><?= esc($d['topik']) ?></code></td>
                        <td><code><?= esc($d['tabel'] ?? '-') ?></code></td>
                        <td><small><?= esc(mb_substr(($d['kolom'] ? $d['kolom'] . ' — ' : '') . ($d['deskripsi'] ?? ''), 0, 160)) ?></small>
                            <?= $d['is_active'] ? '' : '<span class="badge text-bg-secondary">nonaktif</span>' ?></td>
                        <td><form action="<?= site_url('admin/api/dict/delete/' . $d['id']) ?>" method="post" onsubmit="return confirm('Hapus?')"><?= csrf_field() ?>
                            <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button></form></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($dict)): ?>
                    <tr><td colspan="5" class="text-center text-secondary">Belum ada entri.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pelanggaran otorisasi -->
<div class="card ai-card mt-3">
    <div class="card-body p-4">
        <h5 class="fw-bold"><i class="bi bi-exclamation-octagon me-2"></i>Pelanggaran Wewenang
            <small class="text-secondary fw-normal">&mdash; 60 menit terakhir, per klien</small></h5>
        <p class="text-secondary small">
            Setiap percobaan melewati batas dicatat di sini, termasuk yang masih diizinkan karena klien
            berada dalam mode <em>pantau</em>. Lonjakan <code>idor</code> dari satu klien adalah tanda
            enumerasi data &mdash; pertimbangkan membekukan klien itu.
        </p>

        <?php if (empty($violationTally)): ?>
            <div class="alert alert-success mb-2"><i class="bi bi-check-circle me-2"></i>Tidak ada pelanggaran dalam satu jam terakhir.</div>
        <?php else: ?>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-hover align-middle">
                    <thead class="table-light"><tr><th>Klien</th><th>Jenis</th><th>Jumlah</th></tr></thead>
                    <tbody>
                    <?php foreach ($violationTally as $t): ?>
                        <tr>
                            <td><?= esc($t['client_name'] ?? '(tanpa klien)') ?></td>
                            <td><span class="badge text-bg-<?= in_array($t['violation'], ['idor','no_policy','module_denied'], true) ? 'danger' : 'warning' ?>"><?= esc($t['violation']) ?></span></td>
                            <td><strong><?= (int) $t['n'] ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if (! empty($violations)): ?>
            <h6 class="fw-bold mt-3">Rincian terbaru</h6>
            <div class="table-responsive" style="max-height:320px; overflow-y:auto">
                <table class="table table-sm table-hover align-middle">
                    <thead class="table-light"><tr><th>Waktu</th><th>Klien</th><th>Jenis</th><th>Modul</th><th>Dicoba</th><th>Seharusnya</th><th>IP</th></tr></thead>
                    <tbody>
                    <?php foreach ($violations as $v): ?>
                        <tr>
                            <td class="text-nowrap"><small><?= esc($v['created_at']) ?></small></td>
                            <td><small><?= esc($v['client_name'] ?? '-') ?></small></td>
                            <td><span class="badge text-bg-danger"><?= esc($v['violation']) ?></span></td>
                            <td><small><code><?= esc($v['module'] ?? '-') ?></code></small></td>
                            <td><small><code><?= esc($v['attempted'] ?? '-') ?></code></small></td>
                            <td><small><code><?= esc($v['allowed'] ?? '-') ?></code></small></td>
                            <td><small><?= esc($v['ip'] ?? '-') ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal kebijakan per klien -->
<?php foreach ($clients as $c): $pol = $policies[$c['id']] ?? null; ?>
    <?php
        $fp = [];
        if ($pol !== null && ! empty($pol['field_policy'])) {
            $decoded = json_decode((string) $pol['field_policy'], true);
            if (is_array($decoded)) { $fp = $decoded; }
        }
        $toolsNow = array_map('trim', explode(',', (string) ($pol['tools_allowed'] ?? '')));
        $csv = static fn ($module, $kind) => esc(implode(', ', $fp[$module][$kind] ?? []));
    ?>
    <div class="modal fade" id="policyModal<?= (int) $c['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form action="<?= site_url('admin/api/policy') ?>" method="post" class="modal-content">
                <?= csrf_field() ?>
                <input type="hidden" name="client_id" value="<?= (int) $c['id'] ?>">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-shield-check me-2"></i>Kebijakan &mdash; <?= esc($c['name']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-secondary small">
                        Kebijakan ini ditetapkan <strong>admin</strong> dan tidak bisa diubah oleh aplikasi pemanggil.
                        Menentukan sejauh mana klien boleh menjangkau data, kolom apa yang boleh keluar,
                        dan tool AI mana yang boleh dipakai.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Cakupan data</label>
                        <select name="scope" class="form-select">
                            <?php foreach ([
                                'self' => 'self — hanya data dirinya sendiri (portal mahasiswa/dosen)',
                                'unit' => 'unit — semua orang dalam fakultas/prodi/bagian tertentu',
                                'role' => 'role — semua orang dengan peran tertentu',
                                'all'  => 'all — tanpa pembatasan baris (super admin; tetap dibatasi daftar modul)',
                            ] as $k => $label): ?>
                                <option value="<?= $k ?>" <?= ($pol['scope'] ?? 'self') === $k ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Wajib kirim subject</label>
                            <select name="subject_required" class="form-select">
                                <option value="1" <?= (int) ($pol['subject_required'] ?? 1) === 1 ? 'selected' : '' ?>>Ya</option>
                                <option value="0" <?= (int) ($pol['subject_required'] ?? 1) === 0 ? 'selected' : '' ?>>Tidak</option>
                            </select>
                            <div class="form-text">Otomatis "Ya" bila scope = self.</div>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label fw-semibold">Tipe subject diizinkan</label>
                            <input type="text" name="subject_types" class="form-control"
                                   value="<?= esc($pol['subject_types'] ?? 'mahasiswa') ?>" placeholder="mahasiswa, dosen, staff">
                            <div class="form-text">Kosongkan = semua tipe diterima.</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Tipe unit</label>
                            <input type="text" name="unit_type" class="form-control"
                                   value="<?= esc($pol['unit_type'] ?? '') ?>" placeholder="fakultas / prodi / bagian">
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label fw-semibold">ID unit diizinkan</label>
                            <input type="text" name="unit_ids" class="form-control"
                                   value="<?= esc($pol['unit_ids'] ?? '') ?>" placeholder="FT, FMIPA">
                            <div class="form-text">Wajib diisi bila scope = unit.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Peran (scope = role)</label>
                        <input type="text" name="role_scope" class="form-control"
                               value="<?= esc($pol['role_scope'] ?? '') ?>" placeholder="dosen / staff">
                    </div>

                    <hr>
                    <h6 class="fw-bold">Kolom per modul</h6>
                    <p class="text-secondary small">
                        <strong>Boleh</strong> = daftar putih (kosongkan berarti semua boleh kecuali yang dilarang).
                        <strong>Dilarang</strong> = daftar hitam. Kolom sensitif seperti <code>pass</code>,
                        <code>foto</code>, <code>penghasilan_ortu</code> selalu ditolak sistem apa pun isian di sini.
                    </p>
                    <?php foreach (['mahasiswa', 'dosen', 'staff'] as $mod): ?>
                        <div class="row mb-2">
                            <div class="col-md-2"><label class="form-label"><code><?= $mod ?></code></label></div>
                            <div class="col-md-5">
                                <input type="text" name="field_allow[<?= $mod ?>]" class="form-control form-control-sm"
                                       value="<?= $csv($mod, 'allow') ?>" placeholder="boleh: npm, nama, nilai">
                            </div>
                            <div class="col-md-5">
                                <input type="text" name="field_deny[<?= $mod ?>]" class="form-control form-control-sm"
                                       value="<?= $csv($mod, 'deny') ?>" placeholder="dilarang: no_hp, alamat">
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <hr>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Maks baris per query</label>
                            <input type="number" name="row_limit" class="form-control" min="1" max="5000"
                                   value="<?= (int) ($pol['row_limit'] ?? 50) ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Anggaran query / request</label>
                            <input type="number" name="query_budget" class="form-control" min="1" max="200"
                                   value="<?= (int) ($pol['query_budget'] ?? 8) ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Mode penegakan</label>
                            <select name="on_violation" class="form-select">
                                <option value="log_only" <?= ($pol['on_violation'] ?? 'log_only') === 'log_only' ? 'selected' : '' ?>>Pantau (catat saja)</option>
                                <option value="deny_and_log" <?= ($pol['on_violation'] ?? '') === 'deny_and_log' ? 'selected' : '' ?>>Tegakkan (tolak + catat)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tool AI yang diizinkan</label>
                        <div>
                            <?php foreach (['db_lookup' => 'Lookup database', 'web_search' => 'Pencarian web', 'file_reader' => 'Baca file'] as $tk => $tl): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="tools_allowed[]"
                                           id="tool<?= $tk ?><?= (int) $c['id'] ?>" value="<?= $tk ?>"
                                           <?= in_array($tk, $toolsNow, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="tool<?= $tk ?><?= (int) $c['id'] ?>"><?= $tl ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text">
                            Tool yang tidak dicentang <strong>tidak terdaftar</strong> untuk klien ini &mdash;
                            AI bahkan tidak tahu tool itu ada. <code>file_reader</code> sebaiknya tetap mati untuk klien eksternal.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Catatan</label>
                        <textarea name="notes" class="form-control" rows="2"
                                  placeholder="Penanggung jawab, tujuan integrasi, tanggal review…"><?= esc($pol['notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Simpan Kebijakan</button>
                </div>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.getElementById('btnCopyKey')?.addEventListener('click', () => {
    navigator.clipboard.writeText(document.getElementById('newKeyText').textContent);
});

function syncCsrf(newHash) {
    if (!newHash) return;
    const metaToken = document.querySelector('meta[name="csrf-token"]');
    const csrfName = document.querySelector('meta[name="csrf-name"]')?.content || 'csrf_test_name';
    metaToken?.setAttribute('content', newHash);

    // Token diregenerasi setiap POST; semua form harus ikut diperbarui.
    document.querySelectorAll(`input[name="${csrfName}"]`).forEach((el) => {
        el.value = newHash;
    });
}

document.getElementById('btnTestAkad').addEventListener('click', async function () {
    const btn = this, box = document.getElementById('akadStatus');
    const meta = document.querySelector('meta[name="csrf-token"]');
    const name = document.querySelector('meta[name="csrf-name"]')?.content || 'csrf_test_name';
    btn.disabled = true;
    const fd = new FormData();
    fd.append(name, meta?.content || '');
    try {
        const res = await fetch('<?= site_url('admin/api/akademik/test') ?>', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.csrf) syncCsrf(data.csrf);
        box.innerHTML = data.success
            ? `<div class="alert alert-success py-2 mb-0"><i class="bi bi-check-circle me-2"></i>Terhubung — ${data.info.tables} tabel di ${data.info.database}.</div>`
            : `<div class="alert alert-danger py-2 mb-0"><i class="bi bi-x-circle me-2"></i>${data.error}</div>`;
    } catch (e) {
        box.innerHTML = '<div class="alert alert-danger py-2 mb-0">Gagal menghubungi aplikasi.</div>';
    }
    btn.disabled = false;
});
// Cek otomatis saat halaman dibuka (async — halaman tidak ikut menunggu).
document.getElementById('btnTestAkad')?.click();

// Isi semua combobox model dari provider aktif (async).
(async function fillModelSelects() {
    const sels = document.querySelectorAll('select.model-select');
    if (! sels.length) return;
    const swapToText = (sel) => {
        const inp = document.createElement('input');
        inp.type = 'text'; inp.name = 'model';
        inp.className = 'form-control form-control-sm';
        inp.maxLength = 100; inp.style.maxWidth = '170px';
        inp.placeholder = 'model (ketik manual)';
        inp.value = sel.dataset.current || '';
        sel.replaceWith(inp);
    };
    let models = [], defLabel = '';
    try {
        const res = await fetch('<?= site_url('admin/api/models') ?>');
        const d = await res.json();
        models = d.models || [];
        if (d.default) defLabel = ' (global: ' + d.default + ')';
    } catch (e) { /* fallback ke input teks */ }
    sels.forEach(sel => {
        const cur = sel.dataset.current || '';
        if (! models.length) { swapToText(sel); return; }
        sel.innerHTML = '';
        const opt0 = document.createElement('option');
        opt0.value = '';
        opt0.textContent = '— Ikut default global' + defLabel + ' —';
        sel.appendChild(opt0);
        models.forEach(m => {
            const o = document.createElement('option');
            o.value = m; o.textContent = m;
            if (m === cur) o.selected = true;
            sel.appendChild(o);
        });
        // Nilai tersimpan yang tidak ada di daftar (mis. model lama): tampilkan juga.
        if (cur !== '' && [...sel.options].every(o => o.value !== cur)) {
            const o = document.createElement('option');
            o.value = cur; o.textContent = cur + ' (tersimpan)';
            o.selected = true;
            sel.appendChild(o);
        }
    });
})();
</script>
<?= $this->endSection() ?>
