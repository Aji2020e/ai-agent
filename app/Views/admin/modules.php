<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php if (! empty($newKey)): ?>
<div class="alert alert-warning ai-card">
    <i class="bi bi-key me-2"></i><strong>Modul <code><?= esc($newSlug) ?></code> jadi!</strong><br>
    Endpoint: <code>POST /api/assistant/<?= esc($newSlug) ?>/analyze</code><br>
    API Key (salin sekarang!): <code id="newKeyText"><?= esc($newKey) ?></code>
    <button class="btn btn-sm btn-outline-dark ms-2" id="btnCopyKey"><i class="bi bi-clipboard"></i></button>
</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card ai-card">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1"><i class="bi bi-magic me-2"></i>Wizard Modul Baru</h5>
                <p class="text-secondary small">1 Info → 2 Sumber data → 3 Panduan → 4 Buat (endpoint + key otomatis).</p>

                <ul class="nav nav-pills gap-2 mb-3" id="wizTabs">
                    <li class="nav-item"><span class="nav-link active" data-wiz="1">1. Info</span></li>
                    <li class="nav-item"><span class="nav-link disabled" data-wiz="2">2. Sumber</span></li>
                    <li class="nav-item"><span class="nav-link disabled" data-wiz="3">3. Panduan</span></li>
                    <li class="nav-item"><span class="nav-link disabled" data-wiz="4">4. Buat</span></li>
                </ul>

                <form action="<?= site_url('admin/api/modules') ?>" method="post" id="wizForm">
                    <?= csrf_field() ?>

                    <!-- STEP 1 -->
                    <div data-step="1">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Judul modul</label>
                                <input type="text" name="name" id="fName" class="form-control" placeholder="mis. Akademik Mahasiswa" required maxlength="100">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Slug (endpoint)</label>
                                <input type="text" name="slug" id="fSlug" class="form-control" placeholder="mis. mhs_reguler" required pattern="[a-z0-9_]+" maxlength="50">
                                <div class="form-text">Huruf kecil, angka, underscore.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Deskripsi</label>
                                <textarea name="description" class="form-control" rows="2" placeholder="Modul ini menjawab tentang..."></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Parameter ID</label>
                                <input type="text" name="id_param" class="form-control" value="nim" required pattern="[a-z0-9_]+" maxlength="30">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Label ID</label>
                                <input type="text" name="id_label" class="form-control" value="NIM" maxlength="50">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Contoh pertanyaan (satu per baris)</label>
                                <textarea name="examples" class="form-control" rows="3" placeholder="MK apa yang sebaiknya saya ambil semester depan?"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2 -->
                    <div data-step="2" class="d-none">
                        <p class="text-secondary small">Baris pertama = <b>profil</b> (dicari by ID). Baris berikut = data terkait (by ID yang sama). Kolom boleh <code>NAMA=keterangan</code>.</p>
                        <div id="sourceList"></div>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddSource"><i class="bi bi-plus-lg"></i> Tambah sumber</button>
                    <div class="mt-2">
                        <label class="form-label fw-semibold small">Lookup bebas (koma) <small class="text-secondary fw-normal">— tabel yang boleh diintip via param <code>tabel</code> (khusus modul berhak, mis. admin)</small></label>
                        <input type="text" name="lookup_tables" class="form-control form-control-sm" placeholder="mis. mhs, KRS, DOSEN">
                    </div>
                    <div class="form-check mt-2">
                        <input type="checkbox" name="profile_optional" value="1" class="form-check-input" id="profOpt">
                        <label for="profOpt" class="form-check-label small">Profil opsional (mis. admin tanpa NIK staff — tetap bisa lookup)</label>
                    </div>
                    </div>

                    <!-- STEP 3 -->
                    <div data-step="3" class="d-none">
                        <p class="text-secondary small">Buku/panduan akademik yang harus dibaca AI (boleh kosong).</p>
                        <div id="docList"></div>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddDoc"><i class="bi bi-plus-lg"></i> Tambah panduan</button>
                    </div>

                    <!-- STEP 4 -->
                    <div data-step="4" class="d-none">
                        <div id="reviewBox" class="border rounded-3 p-3 mb-3 small"></div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nama klien API (pemilik key)</label>
                            <input type="text" name="client_name" class="form-control" placeholder="mis. PORTAL-MHS (kosong = otomatis)">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-3">
                        <button type="button" class="btn btn-outline-secondary" id="btnPrev" disabled>Kembali</button>
                        <button type="button" class="btn btn-ai" id="btnNext">Lanjut</button>
                        <button type="submit" class="btn btn-success d-none" id="btnCreate"><i class="bi bi-check-lg me-1"></i>Buat Modul + Key</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card ai-card mb-3">
            <div class="card-body p-4">
                <h5 class="fw-bold"><i class="bi bi-grid me-2"></i>Modul Aktif</h5>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item px-0"><span class="badge text-bg-secondary">bawaan</span> mahasiswa, dosen, staff</li>
                    <?php foreach ($modules as $m): ?>
                    <li class="list-group-item px-0 d-flex justify-content-between align-items-start">
                        <div><code><?= esc($m['slug']) ?></code> <strong><?= esc($m['name']) ?></strong>
                            <br><small class="text-secondary">POST /api/assistant/<?= esc($m['slug']) ?>/analyze · param <code><?= esc($m['id_param']) ?></code></small></div>
                        <form action="<?= site_url('admin/api/modules/delete/' . $m['id']) ?>" method="post" onsubmit="return confirm('Hapus modul + kamus + panduannya?')"><?= csrf_field() ?>
                            <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button></form>
                    </li>
                    <?php endforeach; ?>
                    <?php if (empty($modules)): ?>
                    <li class="list-group-item px-0 text-secondary">Belum ada modul dinamis.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="card ai-card mb-3">
            <div class="card-body p-4">
                <h5 class="fw-bold"><i class="bi bi-people me-2"></i>Modul untuk Role Apa?</h5>
                <p class="text-secondary small">Centang role yang boleh memakai tiap modul.</p>
                <form action="<?= site_url('admin/api/modroles') ?>" method="post">
                    <?= csrf_field() ?>
                    <?php
                    $allSlugs = ['mahasiswa' => 'Asisten Mahasiswa', 'dosen' => 'Asisten Dosen', 'staff' => 'Asisten Staff'];
                    foreach ($modules as $m) $allSlugs[$m['slug']] = $m['name'];
                    ?>
                    <?php foreach ($allSlugs as $slug => $label): ?>
                    <div class="border rounded-3 p-2 mb-2">
                        <strong class="small"><code><?= esc($slug) ?></code> <?= esc($label) ?></strong>
                        <div class="d-flex flex-wrap gap-2 mt-1">
                            <?php foreach ($knownRoles as $r): ?>
                            <label class="form-check-label small border rounded-2 px-2 py-1">
                                <input type="checkbox" name="modroles[<?= esc($slug) ?>][]" value="<?= esc($r) ?>" class="form-check-input me-1" <?= in_array($r, $modRoles[$slug] ?? [], true) ? 'checked' : '' ?>><?= esc($r) ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="input-group input-group-sm mb-2">
                        <span class="input-group-text">Role baru</span>
                        <input type="text" name="new_role" class="form-control" placeholder="mis. keuangan" pattern="[a-z0-9_]*">
                    </div>
                    <button class="btn btn-ai btn-sm">Simpan Role Modul</button>
                </form>
                <hr>
                <form action="<?= site_url('admin/api/bagian') ?>" method="post">
                    <?= csrf_field() ?>
                    <label class="form-label small fw-semibold">Bagian staff dengan akses penuh <small class="text-secondary">(id koma — mis. 11 = UPT TI)</small></label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="bagian_full" class="form-control" value="<?= esc($bagianFull) ?>">
                        <button class="btn btn-ai">Simpan</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card ai-card">
            <div class="card-body p-4">
                <h5 class="fw-bold"><i class="bi bi-code-slash me-2"></i>Peta JSON (lanjutan)</h5>
                <form action="<?= site_url('admin/api/rolemap') ?>" method="post">
                    <?= csrf_field() ?>
                    <textarea name="role_modules" class="form-control form-control-sm font-monospace" rows="4"><?= esc(json_encode($roleMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></textarea>
                    <button class="btn btn-outline-secondary btn-sm mt-2">Simpan JSON</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
let wizStep = 1, srcIdx = 0, docIdx = 0;
const meta = document.querySelector('meta[name="csrf-token"]');

document.getElementById('fName').addEventListener('input', function () {
    const s = document.getElementById('fSlug');
    if (!s.dataset.touched) s.value = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '').slice(0, 50);
});
document.getElementById('fSlug').addEventListener('input', function () { this.dataset.touched = 1; });

function showStep(n) {
    wizStep = n;
    document.querySelectorAll('[data-step]').forEach(el => el.classList.toggle('d-none', +el.dataset.step !== n));
    document.querySelectorAll('#wizTabs .nav-link').forEach(el => {
        const i = +el.dataset.wiz;
        el.classList.toggle('active', i === n);
        el.classList.toggle('disabled', i > n);
    });
    document.getElementById('btnPrev').disabled = n === 1;
    document.getElementById('btnNext').classList.toggle('d-none', n === 4);
    document.getElementById('btnCreate').classList.toggle('d-none', n !== 4);
    if (n === 4) renderReview();
}
document.getElementById('btnNext').addEventListener('click', () => { if (wizStep < 4) showStep(wizStep + 1); });
document.getElementById('btnPrev').addEventListener('click', () => { if (wizStep > 1) showStep(wizStep - 1); });

function sourceRow(isFirst) {
    const i = srcIdx++;
    const div = document.createElement('div');
    div.className = 'border rounded-3 p-2 mb-2';
    div.innerHTML = `
        <div class="row g-2">
            <div class="col-md-4"><input type="text" name="sources[${i}][tabel]" class="form-control form-control-sm" placeholder="Tabel (mis. mhs)" required></div>
            <div class="col-md-3"><input type="text" name="sources[${i}][id_col]" class="form-control form-control-sm" placeholder="Kolom ID (mis. NPM)"></div>
            <div class="col-md-3"><input type="text" name="sources[${i}][key]" class="form-control form-control-sm" placeholder="Kunci data" value="${isFirst ? 'profil' : ''}" ${isFirst ? 'readonly' : ''}></div>
            <div class="col-md-2"><button type="button" class="btn btn-sm btn-outline-danger w-100 btnDelSrc">Hapus</button></div>
            <div class="col-md-7"><input type="text" name="sources[${i}][cols]" class="form-control form-control-sm" placeholder="Kolom koma: NPM,NAMA,KD_JUR=prodi" required></div>
            <div class="col-md-3"><input type="text" name="sources[${i}][topik]" class="form-control form-control-sm" placeholder="Topik koma"></div>
            <div class="col-md-2"><input type="number" name="sources[${i}][limit]" class="form-control form-control-sm" value="50" title="Limit"></div>
            <div class="col-12"><input type="text" name="sources[${i}][keterangan]" class="form-control form-control-sm" placeholder="Keterangan: tabel ini menyimpan apa"></div>
            <div class="col-12"><button type="button" class="btn btn-sm btn-link p-0 btnCols">Cek kolom tabel ini</button> <small class="colsOut text-secondary"></small></div>
        </div>`;
    div.querySelector('.btnDelSrc').addEventListener('click', () => div.remove());
    div.querySelector('.btnCols').addEventListener('click', async function () {
        const t = div.querySelector('input[name$="[tabel]"]').value.trim();
        const out = div.querySelector('.colsOut');
        if (!t) return;
        out.textContent = 'memuat...';
        try {
            const res = await fetch('<?= site_url('admin/api/columns') ?>?table=' + encodeURIComponent(t));
            const data = await res.json();
            out.textContent = data.success ? data.columns.join(', ') : ('Error: ' + data.error);
        } catch (e) { out.textContent = 'Gagal.'; }
    });
    return div;
}
document.getElementById('btnAddSource').addEventListener('click', () => {
    document.getElementById('sourceList').appendChild(sourceRow(false));
});
document.getElementById('sourceList').appendChild(sourceRow(true));

function docRow() {
    const i = docIdx++;
    const div = document.createElement('div');
    div.className = 'border rounded-3 p-2 mb-2';
    div.innerHTML = `
        <div class="row g-2">
            <div class="col-md-10"><input type="text" name="docs[${i}][title]" class="form-control form-control-sm" placeholder="Judul panduan"></div>
            <div class="col-md-2"><button type="button" class="btn btn-sm btn-outline-danger w-100 btnDelDoc">Hapus</button></div>
            <div class="col-12"><textarea name="docs[${i}][content]" class="form-control form-control-sm" rows="2" placeholder="Isi panduan..."></textarea></div>
        </div>`;
    div.querySelector('.btnDelDoc').addEventListener('click', () => div.remove());
    return div;
}
document.getElementById('btnAddDoc').addEventListener('click', () => {
    document.getElementById('docList').appendChild(docRow());
});

function renderReview() {
    const name = document.getElementById('fName').value || '-';
    const slug = document.getElementById('fSlug').value || '-';
    const nSrc = document.querySelectorAll('#sourceList > div').length;
    const nDoc = [...document.querySelectorAll('#docList textarea')].filter(t => t.value.trim()).length;
    document.getElementById('reviewBox').innerHTML =
        `<b>${name}</b> (<code>${slug}</code>)<br>Sumber: ${nSrc} tabel · Panduan: ${nDoc} dokumen<br>` +
        `Endpoint: <code>POST /api/assistant/${slug}/analyze</code><br>Key API baru otomatis dibuat.`;
}
document.getElementById('btnCopyKey')?.addEventListener('click', () => {
    navigator.clipboard.writeText(document.getElementById('newKeyText').textContent);
});
</script>
<?= $this->endSection() ?>
