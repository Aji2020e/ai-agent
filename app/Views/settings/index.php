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
                                    <label class="form-label fw-semibold">API Keys untuk provider 
                                        <span class="badge text-bg-info fw-normal" id="providerName">
                                            <?= esc(str_replace('https://', '', rtrim(parse_url($openai_base, PHP_URL_HOST) ?: 'api.openai.com', '/'))) ?>
                                        </span>
                                        <small class="text-secondary">(setiap key punya pasangan endpoint sendiri)</small>
                                        <?= $has_openai_key ? '<span class="badge text-bg-success">tersimpan</span>' : '' ?></label>

                                    <!-- Info: ringkasan semua provider yang sudah punya key -->
                                    <?php if (! empty($openai_key_map_all)): ?>
                                    <div class="alert alert-secondary small mb-2 py-1 px-2">
                                        <i class="bi bi-info-circle me-1"></i> Provider yang pernah diset (total <strong><?= count($openai_key_map_all) ?></strong>):
                                        <ul class="mb-0 mt-1 ps-3">
                                            <?php foreach ($openai_key_map_all as $url => $keys): ?>
                                                <li><code><?= esc(rtrim($url, '/')) ?></code> — <strong><?= count($keys) ?></strong> key</li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <?php endif; ?>

                                    <div id="openaiKeyList">
                                        <?php if (empty($openai_keys)): ?>
                                        <div class="input-group mb-2">
                                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                                            <input type="password" name="openai_keys[]" class="form-control" placeholder="sk-..." autocomplete="new-password">
                                            <span class="input-group-text small text-secondary">Endpoint</span>
                                            <input type="url" name="openai_key_bases[]" class="form-control form-control-sm key-base-url" value="<?= esc($openai_base) ?>" placeholder="https://api.openai.com">
                                            <button type="button" class="btn btn-outline-danger btn-remove-key" title="Hapus"><i class="bi bi-trash"></i></button>
                                        </div>
                                        <?php else: ?>
                                            <?php foreach ($openai_keys as $rowData):
                                                $k      = (string) ($rowData['key'] ?? '');
                                                $rowBase = (string) ($rowData['base'] ?? $openai_base);
                                                $prefix = substr($k, 0, 8);
                                            ?>
                                            <div class="input-group mb-2" data-key="<?= esc($prefix) ?>" data-key-base="<?= esc($rowBase) ?>" data-key-type="<?= esc($prefix) ?>">
                                                <span class="input-group-text"><i class="bi bi-key"></i></span>
                                                <!-- Input tersembunyi menyimpan key asli untuk submit form -->
                                                <input type="hidden" name="openai_keys[]" value="<?= esc($k) ?>">
                                                <input type="url" name="openai_key_bases[]" class="form-control form-control-sm key-base-url" value="<?= esc($rowBase) ?>" placeholder="https://api.openai.com">
                                                <!-- Tampilan mask: hanya tampilkan prefix + 4 char terakhir -->
                                                <span class="form-control text-monospace small bg-light" style="cursor:text" title="Key tersimpan (tersembunyi demi keamanan)">
                                                    <code><?= esc(substr($k, 0, 8)) ?></code>••••••<code><?= esc(substr($k, -4)) ?></code>
                                                </span>
                                                <span class="test-status input-group-text" style="display:none;width:140px;min-width:140px">
                                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                                    <small>Menguji...</small>
                                                </span>
                                                <!-- Badge: deteksi jenis key dari prefix -->
                                                <span class="input-group-text fw-bold" style="width:110px;text-align:center;min-width:110px;background:#f0f0f0;border-radius:6px;font-size:.82rem;">
                                                    <?= \App\Controllers\Settings::detectProvider($k) ?>
                                                </span>
                                                <button type="button" class="btn btn-outline-secondary btn-test-single-key" title="Tes koneksi per key" style="width:40px;"><i class="bi bi-lightning"></i></button>
                                                <button type="button" class="btn btn-outline-danger btn-remove-key" title="Hapus dari database" style="width:40px;"><i class="bi bi-trash"></i></button>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary mb-2" id="btnAddKey"><i class="bi bi-plus-lg me-1"></i>Tambah API Key</button>
                                    <div id="keyMsg" class="mt-2" style="display:none"></div>
                                    <div class="form-text">
                                        Key untuk <strong id="currentProvider"><?= esc(str_replace('https://', '', rtrim(parse_url($openai_base, PHP_URL_HOST) ?: 'api.openai.com', '/'))) ?></strong>.
                                        Setiap key ditandai dengan badge jenisnya (GROQ / OPENROUTER / DLL).
                                        Saat tambah key baru, endpoint otomatis terisi dari Base URL di atas dan boleh diubah per key.
                                        Tombol <b>Hapus</b> akan langsung menghapus dari database — bukan hanya dari form.
                                    </div>
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
                                <div class="alert alert-secondary small"><i class="bi bi-info-circle me-2"></i><b>Tab ini khusus <code>opencode serve</code> self-hosted</b> (<code>opencode serve --port 4096</code> di mesin AI; sesi chat dipetakan otomatis).<br><b>Untuk opencode online / Zen (login Google):</b> JANGAN pakai tab ini — pilih provider <b>API Key</b> di atas, Base URL <code>https://opencode.ai/zen/v1</code>, tempel Zen API key, model mis. <code>qwen3-coder</code> (khusus model jalur <code>/chat/completions</code>; model jalur <code>/messages</code> &amp; <code>/responses</code> tidak didukung klien ini).</div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Server URL</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-terminal"></i></span>
                                        <input type="url" name="opencode_url" id="opencodeUrl" class="form-control" value="<?= esc($opencode_url) ?>">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">API Key <small class="text-secondary fw-normal">(opencode online — login Google — prioritas utama)</small> <?= $has_oc_key ? '<span class="badge text-bg-success">tersimpan</span>' : '' ?></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-key"></i></span>
                                        <input type="password" name="opencode_key" id="opencodeKey" class="form-control" placeholder="<?= $has_oc_key ? 'Kosongkan untuk memakai lama' : 'oca_... / tempel API key opencode.ai' ?>" autocomplete="new-password">
                                    </div>
                                    <div class="form-text">Bila diisi, key ini dipakai (Bearer) dan username/password di bawah diabaikan.</div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Username <small class="text-secondary fw-normal">(serve self-hosted + password server)</small></label>
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

const openaiBaseEl = document.getElementById('openaiBase');
const keyListEl = document.getElementById('openaiKeyList');

function normalizeBaseUrl(url) {
    const v = (url || '').trim();
    return v.endsWith('/') ? v.slice(0, -1) : v;
}

function blankKeyRowHtml() {
    return `
        <span class="input-group-text"><i class="bi bi-key"></i></span>
        <input type="password" name="openai_keys[]" class="form-control" placeholder="sk-..." autocomplete="new-password">
        <span class="input-group-text small text-secondary">Endpoint</span>
        <input type="url" name="openai_key_bases[]" class="form-control form-control-sm key-base-url" value="${normalizeBaseUrl(openaiBaseEl?.value || 'https://api.openai.com')}" placeholder="https://api.openai.com">
        <button type="button" class="btn btn-outline-secondary btn-test-single-key" title="Tes koneksi per key" style="width:40px;"><i class="bi bi-lightning"></i></button>
        <button type="button" class="btn btn-outline-danger btn-remove-key" title="Hapus baris ini (belum tersimpan)" style="width:40px;"><i class="bi bi-trash"></i></button>
    `;
}

if (openaiBaseEl && keyListEl) {
    openaiBaseEl.addEventListener('change', function () {
        const newBase = normalizeBaseUrl(this.value);
        if (newBase === '') {
            return;
        }

        // Hanya update endpoint untuk row key BARU (yang punya input key terlihat).
        keyListEl.querySelectorAll('.input-group').forEach(row => {
            const keyInput = row.querySelector('input[type="password"][name="openai_keys[]"]');
            const baseInput = row.querySelector('input[name="openai_key_bases[]"]');
            if (keyInput && baseInput) {
                baseInput.value = newBase;
            }
        });
    });
}

const modelInput = { ollama: 'ollamaModel', openai: 'openaiModel', opencode: 'opencodeModel' };
const modelLists = { ollama: 'modelListOllama', openai: 'modelListOpenai', opencode: 'modelListOpencode' };

// Tes SEMUA koneksi: Ollama + OpenAI (per endpoint) + OpenCode sekaligus.
// Memakai kredensial TERSIMPAN (bukan isi form) agar hasilnya konsisten.
document.getElementById('btnTest')?.addEventListener('click', async function () {
    const btn = this;
    const box = document.getElementById('testResult');
    const meta = document.querySelector('meta[name="csrf-token"]');
    const name = document.querySelector('meta[name="csrf-name"]')?.content || 'csrf_test_name';
    const prov = curProv();
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menguji semua koneksi...';
    box.innerHTML = '';

    const escHtml2 = s => String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
    const fd = new FormData();
    fd.append(name, meta?.content || '');

    try {
        const res = await fetch('<?= site_url('admin/settings/test-all') ?>', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.csrf) meta?.setAttribute('content', data.csrf);

        const results = data.results || {};
        let html = '';
        const provNames = { ollama: 'Ollama', openai: 'API Key (OpenAI-compatible)', opencode: 'OpenCode' };

        for (const p of ['ollama', 'openai', 'opencode']) {
            const r = results[p];
            if (!r) continue;
            if (p === 'openai' && r.buckets) {
                html += `<div class="alert alert-secondary mb-2"><b>API Key</b><ul class="mb-0 mt-1">`;
                for (const b of r.buckets) {
                    html += `<li><code>${escHtml2(b.base)}</code><ul class="mb-0">`;
                    for (const [prefix, kr] of Object.entries(b.keys || {})) {
                        html += `<li><code>${escHtml2(prefix)}</code> ` +
                            (kr.success
                                ? `<span class="badge text-bg-success">✓ Berhasil</span> <small class="text-secondary">${escHtml2(kr.message || '')}</small>`
                                : `<span class="badge text-bg-danger">✗ Gagal</span> <small class="text-secondary">${escHtml2(kr.message || '')}</small>`) + `</li>`;
                    }
                    html += `</ul></li>`;
                }
                html += `</ul></div>`;
                continue;
            }
            if (r.success) {
                const models = r.models || [];
                html += `<div class="alert alert-success mb-2"><i class="bi bi-check-circle me-2"></i><b>${provNames[p]}</b> terhubung (${models.length} model)` +
                    (models.length ? `<ul class="mb-0 mt-2">` + models.map(m => `<li><code>${escHtml2(m)}</code></li>`).join('') + `</ul>` : '') + `</div>`;
                if (p === prov) {
                    document.getElementById(modelLists[prov]).innerHTML = models.map(m => `<option value="${escHtml2(m)}">`).join('');
                    const cur = document.getElementById(modelInput[prov]).value.trim();
                    if (cur && !models.includes(cur)) {
                        html += `<div class="alert alert-warning mt-2 mb-2"><i class="bi bi-exclamation-triangle me-2"></i>Model <code>${escHtml2(cur)}</code> tidak ada di server ini.</div>`;
                    }
                }
            } else {
                html += `<div class="alert alert-danger mb-2"><i class="bi bi-x-circle me-2"></i><b>${provNames[p]}</b>: ${escHtml2(r.error || 'unknown')}</div>`;
            }
        }

        box.innerHTML = html || '<div class="alert alert-danger mb-0">Tidak ada hasil.</div>';

        // Isi datalist + peringatan model untuk tab yang sedang aktif
        const fillModels = models => {
            if (!models || !models.length) return;
            document.getElementById(modelLists[prov]).innerHTML = models.map(m => `<option value="${escHtml2(m)}">`).join('');
            const cur = document.getElementById(modelInput[prov]).value.trim();
            if (cur && !models.includes(cur)) {
                box.innerHTML += `<div class="alert alert-warning mt-2 mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Model <code>${escHtml2(cur)}</code> tidak ada di server ini.</div>`;
            }
        };
        const active = (results[prov] || {});
        if (prov === 'openai' && active.buckets) {
            const topBase = document.getElementById('openaiBase').value.trim();
            const bucket = active.buckets.find(b => b.base === topBase && (b.models || []).length)
                || active.buckets.find(b => (b.models || []).length);
            if (bucket) fillModels(bucket.models);
        } else if (active.success) {
            fillModels(active.models);
        }
    } catch (e) {
        box.innerHTML = '<div class="alert alert-danger mb-0"><i class="bi bi-x-circle me-2"></i>Gagal menghubungi aplikasi.</div>';
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-lightning-charge me-2"></i>Tes Semua Koneksi';
});

// Tes koneksi PER KEY
document.addEventListener('click', function(e) {
    const testBtn = e.target.closest('.btn-test-single-key');
    if (testBtn) {
        const row = testBtn.closest('.input-group');
        const input = row.querySelector('input[name="openai_keys[]"]');
        const baseInput = row.querySelector('input[name="openai_key_bases[]"]');
        const key = input.value.trim();
        const statusEl = row.querySelector('.test-status');
        const rowBase = (baseInput?.value || document.getElementById('openaiBase').value || '').trim();

        if (key === '') {
            alert('Masukkan API key terlebih dahulu.');
            return;
        }

        if (rowBase === '') {
            alert('Endpoint/Base URL belum diisi.');
            return;
        }

        testBtn.disabled = true;

        const meta = document.querySelector('meta[name="csrf-token"]');
        const csrfName = document.querySelector('meta[name="csrf-name"]')?.content || 'csrf_test_name';
        let status = statusEl;
        if (!status) {
            // Baris baru belum punya area status — buatkan sementara
            status = document.createElement('span');
            status.className = 'test-status input-group-text';
            status.style.cssText = 'width:140px;min-width:140px';
            testBtn.before(status);
        }
        status.style.display = 'flex';

        const escHtml = s => String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
        const fd = new FormData();
        fd.append('ai_provider', 'openai');
        fd.append('openai_base', rowBase);
        fd.append('openai_keys[]', key);
        fd.append(csrfName, meta?.content || '');

        const done = () => {
            setTimeout(() => {
                if (!statusEl) status.remove();
                else status.style.display = 'none';
                testBtn.disabled = false;
            }, 3000);
        };

        fetch('<?= site_url('admin/settings/test') ?>', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.csrf) meta?.setAttribute('content', data.csrf);
                if (data.success) {
                    const n = (data.models || []).length;
                    status.innerHTML = `<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Berhasil (${n})</span>`;
                } else {
                    status.innerHTML = `<span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>Gagal: ${escHtml(data.error || 'unknown')}</span>`;
                }
                done();
            })
            .catch(() => {
                status.innerHTML = `<span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>Error jaringan</span>`;
                done();
            });
    }
});

// ---- Hapus KEY via AJAX (bukan hapus dari form HTML biasa) ----
document.addEventListener('click', function(e) {
    const removeBtn = e.target.closest('.btn-remove-key');
    if (!removeBtn) {
        return;
    }
    e.preventDefault();

    const row     = removeBtn.closest('.input-group');
    const prefix  = row.dataset.key || '';
    const fullKey = (row.querySelector('input[name="openai_keys[]"]')?.value || '').trim();
    const rowBaseInput = row.querySelector('input[name="openai_key_bases[]"]');
    const rowBase = (rowBaseInput?.value || row.dataset.keyBase || document.getElementById('openaiBase').value || '').trim();
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfName = document.querySelector('meta[name="csrf-name"]')?.content || 'csrf_test_name';
    const csrfToken = csrfMeta?.content || '';

    // Jika key BARU (belum tersimpan, tidak ada prefix), cukup hapus dari DOM
    if (prefix === '') {
        row.remove();
        return;
    }

    const baseUrl = rowBase || 'https://api.openai.com';

    // Konfirmasi sebelum hapus
    if (!confirm('Hapus API key dengan prefix "' + prefix + '" dari database?')) {
        return;
    }

    // Disable tombol selama proses
    removeBtn.disabled  = true;
    removeBtn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:1rem;height:1rem;"></span>';

    const fd = new FormData();
    fd.append('provider',   'openai');
    fd.append('base_url',   baseUrl);
    fd.append('key_prefix', prefix);
    if (fullKey !== '') fd.append('api_key', fullKey);
    fd.append(csrfName,     csrfToken);

    fetch('<?= site_url('admin/settings/remove-key') ?>', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.csrf) csrfMeta?.setAttribute('content', data.csrf);
            if (data.success) {
                // Hapus baris dari DOM setelah server konfirmasi
                row.remove();
                showMsg('success', 'API key berhasil dihapus dari database.');
            } else {
                showMsg('danger', 'Gagal menghapus: ' + (data.message || 'error'));
                removeBtn.innerHTML = '<i class="bi bi-trash"></i>';
                removeBtn.disabled  = false;
            }
        })
        .catch(err => {
            showMsg('danger', 'Error jaringan: ' + err.message);
            removeBtn.innerHTML = '<i class="bi bi-trash"></i>';
            removeBtn.disabled  = false;
        });
});

function showMsg(type, text) {
    const el = document.getElementById('keyMsg');
    el.className = `alert alert-${type} mb-0 py-1`;
    el.style.display = 'block';
    el.textContent = text;
    setTimeout(() => { el.style.display = 'none'; }, 4000);
}

// ---- Tambah API Key baru ke form ----
document.getElementById('btnAddKey')?.addEventListener('click', function () {
    const list = document.getElementById('openaiKeyList');
    const row = document.createElement('div');
    row.className = 'input-group mb-2';
    row.innerHTML = blankKeyRowHtml();
    list.appendChild(row);
});

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
