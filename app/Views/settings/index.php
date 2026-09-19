<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="row g-3">
    <div class="col-12">
        <div class="card ai-card">
            <div class="card-body p-0">
                <ul class="nav nav-tabs px-3 pt-3" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-provider-tab" data-bs-toggle="tab" data-bs-target="#tab-provider" type="button" role="tab">
                            <i class="bi bi-cpu me-1"></i>Provider AI
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-web-tab" data-bs-toggle="tab" data-bs-target="#tab-web" type="button" role="tab">
                            <i class="bi bi-globe me-1"></i>Web Search
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-tuning-tab" data-bs-toggle="tab" data-bs-target="#tab-tuning" type="button" role="tab">
                            <i class="bi bi-sliders me-1"></i>Parameter
                        </button>
                    </li>
                </ul>

                <div class="tab-content p-4">
                    <!-- ==================== TAB: PROVIDER AI ==================== -->
                    <div class="tab-pane fade show active" id="tab-provider" role="tabpanel">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-3 text-white" style="width:44px;height:44px;background:linear-gradient(135deg,#6366f1,#d946ef);"><i class="bi bi-cpu"></i></span>
                            <div>
                                <h5 class="fw-bold mb-0">Provider AI</h5>
                                <small class="text-secondary">Berlaku untuk semua user · tersimpan di database</small>
                            </div>
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

                        <?php if ($from_env): ?>
                        <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Sedang memakai nilai dari <code>.env</code>. Simpan form ini untuk menimpa via database.</div>
                        <?php endif; ?>

                        <form action="<?= site_url('admin/settings/update') ?>" method="post">
                            <?= csrf_field() ?>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Provider</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="ai_provider" id="provOllama" value="ollama" <?= $ai_provider === 'ollama' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-primary" for="provOllama"><i class="bi bi-hdd-network me-1"></i>Ollama</label>
                                    <input type="radio" class="btn-check" name="ai_provider" id="provOpenai" value="openai" <?= $ai_provider === 'openai' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-primary" for="provOpenai"><i class="bi bi-key me-1"></i>API Key</label>
                                    <input type="radio" class="btn-check" name="ai_provider" id="provOpencode" value="opencode" <?= $ai_provider === 'opencode' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-primary" for="provOpencode"><i class="bi bi-terminal me-1"></i>OpenCode</label>
                                </div>
                            </div>

                            <!-- Ollama -->
                            <div data-prov="ollama">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Ollama URL</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-hdd-network"></i></span>
                                        <input type="url" name="ollama_url" id="ollamaUrl" class="form-control" value="<?= esc($ollama_url) ?>">
                                    </div>
                                    <div class="form-text">Alamat server Ollama <b>dari sisi aplikasi</b> (bukan browser).</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Model <small class="text-secondary fw-normal">(Tes Koneksi untuk daftar)</small></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-cpu"></i></span>
                                        <input type="text" name="ollama_model" id="ollamaModel" list="modelListOllama" class="form-control" value="<?= esc($ollama_model) ?>">
                                        <datalist id="modelListOllama"></datalist>
                                    </div>
                                </div>
                            </div>

                            <!-- OpenAI-compatible -->
                            <div data-prov="openai" class="d-none">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Base URL</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                                        <input type="url" name="openai_base" id="openaiBase" class="form-control" value="<?= esc($openai_base) ?>" placeholder="https://api.openai.com">
                                    </div>
                                    <div class="form-text">Contoh: OpenAI <code>https://api.openai.com</code> · Groq <code>https://api.groq.com/openai</code> · DeepSeek <code>https://api.deepseek.com</code> · OpenRouter <code>https://openrouter.ai/api</code></div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">API Keys untuk 
                                        <span class="badge text-bg-info fw-normal" id="providerName">
                                            <?= esc(str_replace('https://', '', rtrim(parse_url($openai_base, PHP_URL_HOST) ?: 'api.openai.com', '/'))) ?>
                                        </span>
                                        <?= $has_openai_key ? '<span class="badge text-bg-success">tersimpan</span>' : '' ?></label>
                                    <div id="openaiKeyList">
                                        <?php if (empty($openai_keys)): ?>
                                        <div class="input-group mb-2">
                                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                                            <input type="password" name="openai_keys[]" class="form-control" placeholder="sk-..." autocomplete="new-password">
                                            <button type="button" class="btn btn-outline-danger btn-remove-key"><i class="bi bi-trash"></i></button>
                                        </div>
                                        <?php else: ?>
                                            <?php foreach ($openai_keys as $k): ?>
                                            <div class="input-group mb-2" data-key="<?= esc(substr($k, 0, 8)) ?>">
                                                <span class="input-group-text"><i class="bi bi-key"></i></span>
                                                <input type="password" name="openai_keys[]" class="form-control" value="<?= esc($k) ?>" placeholder="sk-..." autocomplete="new-password">
                                                <span class="input-group-text test-status" style="display:none">
                                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                                    <span>Menguji...</span>
                                                </span>
                                                <button type="button" class="btn btn-outline-secondary btn-test-single-key"><i class="bi bi-lightning"></i></button>
                                                <button type="button" class="btn btn-outline-danger btn-remove-key"><i class="bi bi-trash"></i></button>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary mb-2" id="btnAddKey"><i class="bi bi-plus-lg me-1"></i>Tambah API Key</button>
                                    <div class="form-text">Key untuk <strong id="currentProvider"><?= esc(str_replace('https://', '', rtrim(parse_url($openai_base, PHP_URL_HOST) ?: 'api.openai.com', '/'))) ?></strong>. Simpan banyak key untuk rotasi/failover. Kosongkan untuk menghapus. Tersimpan terenkripsi di database.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Model <small class="text-secondary fw-normal">(Tes Koneksi untuk daftar)</small></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-cpu"></i></span>
                                        <input type="text" name="openai_model" id="openaiModel" list="modelListOpenai" class="form-control" value="<?= esc($openai_model) ?>">
                                        <datalist id="modelListOpenai"></datalist>
                                    </div>
                                </div>
                            </div>

                            <!-- OpenCode -->
                            <div data-prov="opencode" class="d-none">
                                <div class="alert alert-secondary small"><i class="bi bi-info-circle me-2"></i>Jalankan dulu di mesin AI: <code>opencode serve --port 4096</code> (tambah <code>--hostname 0.0.0.0</code> bila beda mesin). Butuh login provider di opencode-nya. Sesi chat dipetakan otomatis ke sesi opencode.</div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Server URL</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-terminal"></i></span>
                                        <input type="url" name="opencode_url" id="opencodeUrl" class="form-control" value="<?= esc($opencode_url) ?>">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Username <small class="text-secondary fw-normal">(bila pakai password server)</small></label>
                                        <input type="text" name="opencode_user" id="opencodeUser" class="form-control" value="<?= esc($opencode_user) ?>" placeholder="opencode" autocomplete="username">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Password <?= $has_oc_pass ? '<span class="badge text-bg-success">tersimpan</span>' : '' ?></label>
                                        <input type="password" name="opencode_pass" id="opencodePass" class="form-control" placeholder="<?= $has_oc_pass ? 'Kosongkan untuk memakai lama' : 'OPENCODE_SERVER_PASSWORD' ?>" autocomplete="new-password">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Model <small class="text-secondary fw-normal">(format: provider/model — Tes Koneksi untuk daftar, kosongkan = default opencode)</small></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-cpu"></i></span>
                                        <input type="text" name="opencode_model" id="opencodeModel" list="modelListOpencode" class="form-control" value="<?= esc($opencode_model) ?>" placeholder="anthropic/claude-sonnet-4-5">
                                        <datalist id="modelListOpencode"></datalist>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-ai"><i class="bi bi-save me-2"></i>Simpan</button>
                                <button type="button" class="btn btn-outline-secondary" id="btnTest"><i class="bi bi-lightning-charge me-2"></i>Tes Koneksi</button>
                            </div>
                        </form>

                        <div id="testResult" class="mt-3"></div>
                    </div>

                    <!-- ==================== TAB: WEB SEARCH ==================== -->
                    <div class="tab-pane fade" id="tab-web" role="tabpanel">
                        <h5 class="fw-bold mb-3"><i class="bi bi-globe me-2"></i>Pencarian Internet Cadangan</h5>
                        <p class="text-secondary small">Dipakai otomatis bila topik kekinian atau data lokal kosong. Model lokal tidak belajar sendiri — ini cara ia "tahu" hal baru.</p>

                        <form action="<?= site_url('admin/settings/update') ?>" method="post">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Sumber pencarian</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="search_provider" id="spOff" value="off" <?= $search_provider === 'off' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-secondary" for="spOff">Mati</label>
                                    <input type="radio" class="btn-check" name="search_provider" id="spTavily" value="tavily" <?= $search_provider === 'tavily' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-secondary" for="spTavily">Tavily</label>
                                    <input type="radio" class="btn-check" name="search_provider" id="spDdg" value="ddg" <?= $search_provider === 'ddg' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-secondary" for="spDdg">DuckDuckGo</label>
                                </div>
                                <div class="form-text">Tavily butuh API key (gratis di tavily.com). DuckDuckGo gratis tanpa key, hasil terbatas.</div>
                            </div>
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label class="form-label fw-semibold">Tavily API Key <?= $has_search_key ? '<span class="badge text-bg-success">tersimpan</span>' : '' ?></label>
                                    <input type="password" name="search_key" class="form-control" placeholder="<?= $has_search_key ? 'Kosongkan untuk memakai lama' : 'tvly-...' ?>" autocomplete="new-password">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label fw-semibold">Maks hasil</label>
                                    <input type="number" name="search_max" class="form-control" value="<?= esc($search_max) ?>" min="1" max="8">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-ai"><i class="bi bi-save me-2"></i>Simpan</button>
                        </form>
                    </div>

                    <!-- ==================== TAB: PARAMETER ==================== -->
                    <div class="tab-pane fade" id="tab-tuning" role="tabpanel">
                        <h5 class="fw-bold mb-3"><i class="bi bi-sliders me-2"></i>Kualitas &amp; Kecepatan Jawaban</h5>
                        <p class="text-secondary small">
                            Menentukan seberapa patuh AI pada data dan seberapa panjang jawabannya.
                            Untuk aplikasi yang melaporkan data akademik, biarkan <strong>temperature rendah</strong> —
                            angka tinggi membuat model berkreasi, dan itu artinya mengarang.
                        </p>

                        <form action="<?= site_url('admin/settings/update') ?>" method="post">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Preset cepat</label>
                                <div class="btn-group w-100" role="group">
                                    <button type="button" class="btn btn-outline-secondary" data-preset="faktual"
                                            data-temp="0.1" data-tokens="768">Faktual / Akademik</button>
                                    <button type="button" class="btn btn-outline-secondary" data-preset="seimbang"
                                            data-temp="0.4" data-tokens="1024">Seimbang</button>
                                    <button type="button" class="btn btn-outline-secondary" data-preset="kreatif"
                                            data-temp="0.8" data-tokens="2048">Kreatif / Menulis</button>
                                    <button type="button" class="btn btn-outline-secondary" data-preset="coding"
                                            data-temp="0.2" data-tokens="2048">Coding</button>
                                </div>
                                <div class="form-text">Tombol preset hanya mengisi angka di bawah — masih bisa diubah manual sebelum disimpan.</div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label fw-semibold" for="aiTemperature">Temperature</label>
                                    <input type="number" step="0.1" min="0" max="2" name="ai_temperature" id="aiTemperature"
                                           class="form-control" value="<?= esc($ai_temperature) ?>">
                                    <div class="form-text">0 = sangat pasti, 2 = sangat acak. Disarankan 0.1&ndash;0.3 untuk data.</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label fw-semibold" for="aiTopP">Top-p</label>
                                    <input type="number" step="0.05" min="0.1" max="1" name="ai_top_p" id="aiTopP"
                                           class="form-control" value="<?= esc($ai_top_p) ?>">
                                    <div class="form-text">Batasi keragaman kata. 0.9 umum dipakai.</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label fw-semibold" for="aiMaxTokens">Maks token jawaban</label>
                                    <input type="number" min="64" max="8192" step="64" name="ai_max_tokens" id="aiMaxTokens"
                                           class="form-control" value="<?= esc($ai_max_tokens) ?>">
                                    <div class="form-text">Membatasi panjang jawaban &rarr; lebih cepat dan lebih hemat.</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label fw-semibold" for="aiMaxContext">Anggaran konteks (token)</label>
                                    <input type="number" min="500" max="100000" step="500" name="ai_max_context_tokens" id="aiMaxContext"
                                           class="form-control" value="<?= esc($ai_max_context_tokens) ?>">
                                    <div class="form-text">Total system prompt + riwayat. Bila terlampaui, pesan tertua dipangkas &mdash; system prompt selalu dipertahankan.</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label fw-semibold" for="aiHistoryLimit">Maks pesan riwayat</label>
                                    <input type="number" min="2" max="200" name="ai_history_limit" id="aiHistoryLimit"
                                           class="form-control" value="<?= esc($ai_history_limit) ?>">
                                    <div class="form-text">Jumlah pesan terakhir yang diambil dari database.</div>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label fw-semibold" for="aiNumCtx">num_ctx</label>
                                    <input type="number" min="2048" max="131072" step="1024" name="ai_num_ctx" id="aiNumCtx"
                                           class="form-control" value="<?= esc($ai_num_ctx) ?>">
                                    <div class="form-text">Khusus Ollama.</div>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label fw-semibold" for="aiKeepAlive">keep_alive</label>
                                    <input type="text" name="ai_keep_alive" id="aiKeepAlive"
                                           class="form-control" value="<?= esc($ai_keep_alive) ?>" placeholder="30m">
                                    <div class="form-text">Khusus Ollama: mis. 30m, 1h.</div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-ai"><i class="bi bi-save me-2"></i>Simpan</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const curProv = () => document.querySelector('input[name="ai_provider"]:checked').value;
function syncProv() {
    document.querySelectorAll('[data-prov]').forEach(el => {
        el.classList.toggle('d-none', el.dataset.prov !== curProv());
    });
}
document.querySelectorAll('input[name="ai_provider"]').forEach(r => r.addEventListener('change', syncProv));
syncProv();

// Perbarui nama provider saat Base URL berubah
document.getElementById('openaiBase')?.addEventListener('input', function() {
    const url = this.value.trim() || 'https://api.openai.com';
    try {
        const hostname = new URL(url).hostname.replace('www.', '');
        document.getElementById('providerName').textContent = hostname;
        document.getElementById('currentProvider').textContent = hostname;
    } catch (e) {
        // invalid URL, keep default
    }
});

const modelInput = { ollama: 'ollamaModel', openai: 'openaiModel', opencode: 'opencodeModel' };
const modelLists = { ollama: 'modelListOllama', openai: 'modelListOpenai', opencode: 'modelListOpencode' };

// Tes koneksi SEMUA key
document.getElementById('btnTest')?.addEventListener('click', async function () {
    const btn = this;
    const box = document.getElementById('testResult');
    const meta = document.querySelector('meta[name="csrf-token"]');
    const name = document.querySelector('meta[name="csrf-name"]')?.content || 'csrf_test_name';
    const prov = curProv();
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menguji semua key...';
    box.innerHTML = '';

    const fd = new FormData();
    fd.append('ai_provider', prov);
    fd.append('ollama_url', document.getElementById('ollamaUrl').value);
    fd.append('openai_base', document.getElementById('openaiBase').value);
    document.querySelectorAll('input[name="openai_keys[]"]').forEach(input => {
        if (input.value.trim() !== '') fd.append('openai_keys[]', input.value.trim());
    });
    fd.append('opencode_url', document.getElementById('opencodeUrl').value);
    fd.append('opencode_user', document.getElementById('opencodeUser').value);
    const op = document.getElementById('opencodePass');
    if (op && op.value) fd.append('opencode_pass', op.value);
    fd.append(name, meta?.content || '');

    try {
        const res = await fetch('<?= site_url('admin/settings/test') ?>', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.csrf) meta?.setAttribute('content', data.csrf);
        
        if (data.success) {
            document.getElementById(modelLists[prov]).innerHTML = (data.models || []).map(m => `<option value="${m}">`).join('');
            const list = (data.models || []).map(m => `<li><code>${m}</code></li>`).join('') || '<li class="text-secondary">Tidak ada model.</li>';
            const cur = document.getElementById(modelInput[prov]).value.trim();
            const warn = cur && !(data.models || []).includes(cur)
                ? `<div class="alert alert-warning mt-2 mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Model <code>${cur}</code> tidak ada di server ini.</div>` : '';
            
            // Tampilkan hasil per-key jika tersedia
            let perKeyHtml = '';
            if (data.key_results && Object.keys(data.key_results).length > 0) {
                perKeyHtml = '<div class="mt-3"><h6 class="fw-bold">Status per API Key:</h6><ul class="list-group list-group-flush">';
                for (const [keyPrefix, result] of Object.entries(data.key_results)) {
                    const badge = result.success 
                        ? '<span class="badge text-bg-success">✓ Berhasil</span>' 
                        : '<span class="badge text-bg-danger">✗ Gagal</span>';
                    perKeyHtml += `<li class="list-group-item d-flex justify-content-between align-items-center">
                        <code>${keyPrefix}...</code>
                        ${badge}
                        <small class="text-secondary">${result.message}</small>
                    </li>`;
                }
                perKeyHtml += '</ul></div>';
            }
            
            box.innerHTML = `<div class="alert alert-success mb-0">
                <i class="bi bi-check-circle me-2"></i>Terhubung ke ${document.getElementById('providerName').textContent} (${(data.models || []).length} model):
                <ul class="mb-0 mt-2">${list}</ul>
            </div>${warn}${perKeyHtml}`;
        } else {
            box.innerHTML = `<div class="alert alert-danger mb-0"><i class="bi bi-x-circle me-2"></i>Tidak terhubung: ${data.error || 'unknown'}</div>`;
        }
    } catch (e) {
        box.innerHTML = '<div class="alert alert-danger mb-0"><i class="bi bi-x-circle me-2"></i>Gagal menghubungi aplikasi.</div>';
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-lightning-charge me-2"></i>Tes Semua Koneksi';
});

// Tes koneksi PER KEY
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('btn-test-single-key')) {
        const row = e.target.closest('.input-group');
        const input = row.querySelector('input[name="openai_keys[]"]');
        const key = input.value.trim();
        const prefix = key.substring(0, 8);
        const statusEl = row.querySelector('.test-status');
        
        if (key === '') {
            alert('Masukkan API key terlebih dahulu.');
            return;
        }
        
        statusEl.style.display = 'flex';
        e.target.disabled = true;
        
        const fd = new FormData();
        fd.append('ai_provider', 'openai');
        fd.append('openai_base', document.getElementById('openaiBase').value);
        fd.append('openai_keys[]', key);
        
        fetch('<?= site_url('admin/settings/test') ?>', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    statusEl.innerHTML = `<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Berhasil</span>`;
                } else {
                    statusEl.innerHTML = `<span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>Gagal: ${data.error}</span>`;
                }
                setTimeout(() => {
                    statusEl.style.display = 'none';
                    e.target.disabled = false;
                }, 3000);
            })
            .catch(() => {
                statusEl.innerHTML = `<span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>Error jaringan</span>`;
                setTimeout(() => {
                    statusEl.style.display = 'none';
                    e.target.disabled = false;
                }, 3000);
            });
    }
});

// ---- Multi API Key untuk provider AI ----
document.getElementById('btnAddKey')?.addEventListener('click', function () {
    const list = document.getElementById('openaiKeyList');
    const row = document.createElement('div');
    row.className = 'input-group mb-2';
    row.innerHTML = `
        <span class="input-group-text"><i class="bi bi-key"></i></span>
        <input type="password" name="openai_keys[]" class="form-control" placeholder="sk-..." autocomplete="new-password">
        <span class="input-group-text test-status" style="display:none">
            <span class="spinner-border spinner-border-sm me-1" role="status"></span>
            <span>Menguji...</span>
        </span>
        <button type="button" class="btn btn-outline-secondary btn-test-single-key"><i class="bi bi-lightning"></i></button>
        <button type="button" class="btn btn-outline-danger btn-remove-key"><i class="bi bi-trash"></i></button>
    `;
    list.appendChild(row);
    bindRemoveKeys();
});

function bindRemoveKeys() {
    document.querySelectorAll('.btn-remove-key').forEach(btn => {
        btn.onclick = function () {
            this.closest('.input-group').remove();
        };
    });
}
bindRemoveKeys();

// ---- Preset tuning: isi angka, tidak langsung menyimpan ----
document.querySelectorAll('[data-preset]').forEach(btn => {
    btn.addEventListener('click', function () {
        document.getElementById('aiTemperature').value = this.dataset.temp;
        document.getElementById('aiMaxTokens').value   = this.dataset.tokens;

        document.querySelectorAll('[data-preset]').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
    });
});
</script>
<?= $this->endSection() ?>
