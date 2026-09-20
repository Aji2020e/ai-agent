<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
<style>
    .chat-layout { display: flex; gap: 1rem; height: calc(100vh - 220px); min-height: 480px; }
    .chat-sidebar { width: 280px; flex-shrink: 0; background: linear-gradient(170deg, #1a1440 0%, #241d5e 50%, #3b1d6e 100%); border-radius: 1rem; display: flex; flex-direction: column; overflow: hidden; color: #c9cdf7; box-shadow: 0 10px 30px rgba(35,30,90,.18); }
    .chat-sidebar-header { padding: 1rem; border-bottom: 1px solid rgba(255,255,255,.12); }
    .chat-sidebar-body { flex: 1; overflow-y: auto; padding: .6rem; }
    .chat-main { flex: 1; display: flex; flex-direction: column; background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); border-radius: 1rem; overflow: hidden; box-shadow: 0 10px 30px rgba(35,30,90,.08); }
    .chat-messages { flex: 1; overflow-y: auto; padding: 1.5rem; }
    .chat-input-area { padding: 1rem 1.25rem; border-top: 1px solid var(--bs-border-color); background: var(--bs-body-bg); }
    .message-row { display: flex; margin-bottom: 1rem; }
    .message-row.user { justify-content: flex-end; }
    .message-row.assistant { justify-content: flex-start; }
    .message-avatar { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: .9rem; flex-shrink: 0; color: #fff; }
    .message-avatar.user-avatar { background: linear-gradient(135deg, #6366f1, #8b5cf6); margin-left: .5rem; }
    .message-avatar.ai-avatar { background: linear-gradient(135deg, #10b981, #0ea5e9); margin-right: .5rem; }
    .message-content { max-width: 78%; }
    .message-bubble { padding: .75rem 1rem; border-radius: 1rem; line-height: 1.55; font-size: .92rem; }
    .message-row.user .message-bubble { background: linear-gradient(135deg, #6366f1, #8b5cf6 60%, #d946ef); color: #fff; border-bottom-right-radius: .25rem; }
    .message-row.assistant .message-bubble { background: var(--bs-tertiary-bg); color: var(--bs-body-color); border: 1px solid var(--bs-border-color); border-bottom-left-radius: .25rem; }
    .message-bubble pre { background: #0d1117 !important; color: #c9d1d9; padding: 1rem; border-radius: .6rem; overflow-x: auto; margin: .5rem 0; font-size: .83rem; }
    .message-bubble code:not(pre code) { background: rgba(120,120,180,.15); padding: .125rem .375rem; border-radius: .3rem; font-size: .85em; }
    .message-row.user .message-bubble code:not(pre code) { background: rgba(255,255,255,.25); }
    .message-time { font-size: .7rem; color: var(--bs-secondary-color); margin-top: .25rem; }
    .message-row.user .message-time { text-align: right; }
    .session-item { display: flex; align-items: center; gap: .5rem; padding: .55rem .7rem; border-radius: .7rem; margin-bottom: .25rem; cursor: pointer; color: #c9cdf7; font-size: .86rem; border: 1px solid transparent; }
    .session-item:hover { background: rgba(255,255,255,.08); }
    .session-item.active { background: rgba(255,255,255,.15); color: #fff; border-color: rgba(255,255,255,.14); }
    .session-item .session-title { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .session-item .session-actions { opacity: 0; transition: opacity .15s; }
    .session-item:hover .session-actions { opacity: 1; }
    /* Layar sentuh (tanpa hover): tombol hapus hanya di sesi aktif agar tidak berantakan */
    @media (hover: none) {
        .session-item .session-actions { opacity: 0; }
        .session-item.active .session-actions { opacity: 1; }
    }
    .session-actions button { background: none; border: none; color: #adb5bd; padding: .125rem .25rem; cursor: pointer; }
    .session-actions button:hover { color: #fff; }
    .typing-indicator { display: inline-flex; gap: 4px; padding: .75rem 1rem; }
    .typing-indicator span { width: 8px; height: 8px; background: #adb5bd; border-radius: 50%; animation: typingBounce 1.4s infinite ease-in-out; }
    .typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
    .typing-indicator span:nth-child(2) { animation-delay: -0.16s; }
    @keyframes typingBounce { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1); } }
    #chatTextarea { resize: none; min-height: 44px; max-height: 200px; }
    .suggest-card { cursor: pointer; transition: transform .12s ease, box-shadow .12s ease; }
    .suggest-card:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(99,102,241,.18); }
    @media (max-width: 768px) {
        .chat-layout { flex-direction: column; height: auto; }
        .chat-sidebar { width: 100%; max-height: 220px; }
        .chat-messages { max-height: 60vh; }
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="chat-layout">
    <!-- Sidebar sesi -->
    <div class="chat-sidebar" id="chatSidebar">
        <div class="chat-sidebar-header">
            <button class="btn btn-ai w-100" id="btnNewChat" style="background:linear-gradient(135deg,#6366f1,#8b5cf6 55%,#d946ef);border:0;color:#fff;font-weight:600;">
                <i class="bi bi-plus-lg"></i> Chat Baru
            </button>
        </div>
        <div class="chat-sidebar-body" id="sessionList">
            <?php if (! empty($sessions)): ?>
                <?php foreach ($sessions as $s): ?>
                <div class="session-item <?= (isset($current_session) && $current_session === $s['session_id']) ? 'active' : '' ?>"
                     data-session="<?= esc($s['session_id']) ?>"
                     onclick="loadSession('<?= esc($s['session_id']) ?>')">
                    <i class="bi bi-chat-dots"></i>
                    <span class="session-title"><?= esc($s['title'] ?? 'Chat Baru') ?></span>
                    <?php if (! empty($s['title'])): ?>
                    <div class="session-actions">
                        <button onclick="event.stopPropagation(); deleteSession('<?= esc($s['session_id']) ?>')" title="Hapus">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center mt-3 px-2 opacity-75" style="font-size: 0.85rem;">Belum ada riwayat chat.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Chat -->
    <div class="chat-main">
        <div class="chat-messages" id="chatMessages">
            <?php if (! empty($messages)): ?>
                <?php foreach ($messages as $msg): ?>
                    <?php if ($msg['role'] === 'system') continue; ?>
                    <?= renderMessage($msg['role'], $msg['content'], $msg['created_at'], $msg['attachment_name'] ?? null) ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center mt-4" id="welcomeScreen">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-4 text-white mb-3" style="width:72px;height:72px;background:linear-gradient(135deg,#6366f1,#8b5cf6 55%,#d946ef);font-size:2rem;"><i class="bi bi-cpu"></i></span>
                    <h4 class="fw-bold">AI Coding Assistant</h4>
                    <p class="text-secondary">Tanyakan sesuatu tentang coding, debugging, atau arsitektur software.</p>
                    <div class="row justify-content-center mt-4 g-3">
                        <div class="col-md-4">
                            <div class="card h-100 suggest-card" onclick="fillSuggest('Buatkan fungsi untuk sorting array di PHP')">
                                <div class="card-body p-3 text-start">
                                    <h6 class="fw-bold"><i class="bi bi-code-slash me-1"></i>Bantuan Coding</h6>
                                    <small class="text-secondary">"Buatkan fungsi untuk sorting array di PHP"</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100 suggest-card" onclick="fillSuggest('Kenapa query SQL ini lambat dan bagaimana memperbaikinya?')">
                                <div class="card-body p-3 text-start">
                                    <h6 class="fw-bold"><i class="bi bi-bug me-1"></i>Debugging</h6>
                                    <small class="text-secondary">"Kenapa query SQL ini lambat?"</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100 suggest-card" onclick="fillSuggest('Bagaimana merancang REST API yang scalable?')">
                                <div class="card-body p-3 text-start">
                                    <h6 class="fw-bold"><i class="bi bi-diagram-3 me-1"></i>Arsitektur</h6>
                                    <small class="text-secondary">"Bagaimana merancang REST API yang scalable?"</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="chat-input-area">
            <form id="chatForm" onsubmit="sendMessage(event)">
                <?= csrf_field() ?>
                <input type="hidden" id="sessionId" value="<?= esc($current_session ?? '') ?>">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-cpu text-secondary"></i>
                    <select id="modelSelect" class="form-select form-select-sm" style="max-width:280px;" title="Model AI yang dipakai">
                        <option value="">Memuat model...</option>
                    </select>
                </div>
                <div id="fileChip" class="d-none align-items-center gap-2 mb-2">
                    <span class="badge text-bg-secondary"><i class="bi bi-paperclip"></i> <span id="fileChipName"></span></span>
                    <button type="button" class="btn btn-sm btn-link text-danger p-0" id="btnRemoveFile"><i class="bi bi-x-circle"></i></button>
                </div>
                <div class="input-group">
                    <button type="button" class="btn btn-outline-secondary" id="btnAttach" title="Lampirkan file teks/kode (maks 2 MB)">
                        <i class="bi bi-paperclip"></i>
                    </button>
                    <input type="file" id="fileInput" class="d-none" accept=".txt,.md,.markdown,.csv,.json,.xml,.yml,.yaml,.ini,.log,.php,.js,.ts,.jsx,.tsx,.vue,.py,.java,.c,.cpp,.h,.go,.rs,.rb,.kt,.swift,.html,.css,.scss,.sql,.sh,.pdf">
                    <textarea id="chatTextarea" class="form-control" placeholder="Ketik pesan Anda di sini... (Shift+Enter untuk baris baru)" rows="1"></textarea>
                    <button type="submit" class="btn btn-ai" id="btnSend" style="background:linear-gradient(135deg,#6366f1,#8b5cf6 55%,#d946ef);border:0;color:#fff;">
                        <i class="bi bi-send"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/marked/12.0.0/marked.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.9/purify.min.js"></script>
<script>
const BASE_URL = '<?= rtrim(site_url(), '/') ?>/';
const CSRF_NAME = document.querySelector('meta[name="csrf-name"]')?.content || 'csrf_test_name';
function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}
function updateCsrfToken(newHash) {
    if (! newHash) return;
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) meta.setAttribute('content', newHash);
    document.querySelectorAll('input[name="' + CSRF_NAME + '"]').forEach(el => { el.value = newHash; });
}
function fillSuggest(text) {
    const ta = document.getElementById('chatTextarea');
    ta.value = text;
    ta.focus();
}

function addModelOption(parent, value, label, base, selected) {
    const o = document.createElement('option');
    o.value = value;
    o.textContent = label;
    if (base) o.dataset.base = base;
    if (selected) o.selected = true;
    parent.appendChild(o);
    return o;
}
async function loadModels() {
    const sel = document.getElementById('modelSelect');
    const saved = localStorage.getItem('ai-model') || '';
    const savedBase = localStorage.getItem('ai-model-base') || '';
    try {
        const res = await fetch(BASE_URL + 'chat/models');
        const data = await res.json();
        const def = saved || data.default || '';
        sel.innerHTML = '';
        // Format grup (OpenAI multi-bucket): tiap endpoint jadi optgroup
        if (Array.isArray(data.groups) && data.groups.length) {
            let anySelected = false;
            data.groups.forEach(g => {
                const host = g.host || g.base;
                if (!g.models || !g.models.length) {
                    const o = document.createElement('option');
                    o.value = ''; o.textContent = `— ${host}: tak terjangkau —`; o.disabled = true;
                    sel.appendChild(o);
                    return;
                }
                const og = document.createElement('optgroup');
                og.label = host;
                g.models.forEach(m => {
                    const sel2 = def && m === def && (!savedBase || savedBase === g.base);
                    if (sel2) anySelected = true;
                    addModelOption(og, m, m, g.base, sel2);
                });
                sel.appendChild(og);
            });
            if (def && !anySelected) {
                addModelOption(sel, def, def + ' (default)', savedBase, true);
            }
            return;
        }
        // Format datar (Ollama / OpenCode / kompatibilitas lama)
        const list = data.models || [];
        if (!list.length) {
            addModelOption(sel, def, def || 'Model default', '', true);
            return;
        }
        list.forEach(m => {
            addModelOption(sel, m, m, '', def && m === def);
        });
        if (def && !list.includes(def)) {
            addModelOption(sel, def, def + ' (default)', '', true);
        }
    } catch (e) {
        sel.innerHTML = '';
        addModelOption(sel, saved, saved || 'Model default', savedBase, true);
    }
}
function selectedBase() {
    const sel = document.getElementById('modelSelect');
    const o = sel.options[sel.selectedIndex];
    return (o && o.dataset.base) || '';
}
document.getElementById('modelSelect').addEventListener('change', function () {
    localStorage.setItem('ai-model', this.value);
    localStorage.setItem('ai-model-base', selectedBase());
});
loadModels();

document.getElementById('btnAttach').addEventListener('click', () => document.getElementById('fileInput').click());
document.getElementById('fileInput').addEventListener('change', function () {
    const chip = document.getElementById('fileChip');
    if (this.files.length) {
        document.getElementById('fileChipName').textContent = this.files[0].name;
        chip.classList.remove('d-none');
        chip.classList.add('d-flex');
    } else {
        chip.classList.add('d-none');
        chip.classList.remove('d-flex');
    }
});
document.getElementById('btnRemoveFile').addEventListener('click', () => {
    document.getElementById('fileInput').value = '';
    document.getElementById('fileChip').classList.add('d-none');
    document.getElementById('fileChip').classList.remove('d-flex');
});
function clearAttachment() {
    document.getElementById('fileInput').value = '';
    document.getElementById('fileChip').classList.add('d-none');
    document.getElementById('fileChip').classList.remove('d-flex');
}

marked.setOptions({
    highlight: function(code, lang) {
        if (lang && hljs.getLanguage(lang)) {
            return hljs.highlight(code, { language: lang }).value;
        }
        return hljs.highlightAuto(code).value;
    },
    breaks: true
});

document.getElementById('btnNewChat').addEventListener('click', function() {
    document.getElementById('sessionId').value = '';
    document.getElementById('chatMessages').innerHTML =
        '<div class="text-center mt-4" id="welcomeScreen">' +
        '<h4 class="fw-bold">Chat Baru</h4>' +
        '<p class="text-secondary">Mulai percakapan baru dengan AI.</p></div>';
    document.querySelectorAll('.session-item').forEach(el => el.classList.remove('active'));
    document.getElementById('chatTextarea').focus();
});

document.getElementById('chatTextarea').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && ! e.shiftKey) {
        e.preventDefault();
        document.getElementById('chatForm').dispatchEvent(new Event('submit'));
    }
});

document.getElementById('chatTextarea').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 200) + 'px';
});

let sending = false; // cegah double-submit yang membuat sesi ganda
function sendMessage(e) {
    e.preventDefault();
    if (sending) return;
    const textarea = document.getElementById('chatTextarea');
    const message = textarea.value.trim();
    if (! message) return;
    sending = true;

    const welcome = document.getElementById('welcomeScreen');
    if (welcome) welcome.remove();

    const attachedName = document.getElementById('fileInput').files[0]?.name || null;
    appendMessage('user', message, null, attachedName);
    textarea.value = '';
    textarea.style.height = 'auto';

    const typingId = showTyping();
    document.getElementById('btnSend').disabled = true;

    const formData = new FormData();
    formData.append('session_id', document.getElementById('sessionId').value);
    formData.append('message', message);
    formData.append('model', document.getElementById('modelSelect').value);
    formData.append('model_base', selectedBase());
    const fileInput = document.getElementById('fileInput');
    if (fileInput.files[0]) formData.append('attachment', fileInput.files[0]);
    clearAttachment();
    formData.append(CSRF_NAME, getCsrfToken());

    fetch(BASE_URL + 'chat/send', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        removeTyping(typingId);
        document.getElementById('btnSend').disabled = false;
        sending = false;
        if (data.csrf) updateCsrfToken(data.csrf);

        if (data.error) {
            appendMessage('assistant', 'Error: ' + data.error);
            return;
        }

        if (data.session_id) {
            document.getElementById('sessionId').value = data.session_id;
            if (window.history.pushState) {
                window.history.pushState({}, '', BASE_URL + 'chat/' + data.session_id);
            }
            addSessionToSidebar(data.session_id, message.substring(0, 50));
        }

        appendMessage('assistant', data.reply);
    })
    .catch(err => {
        removeTyping(typingId);
        document.getElementById('btnSend').disabled = false;
        sending = false;
        appendMessage('assistant', 'Terjadi kesalahan koneksi. Silakan coba lagi.');
    });
}

function appendMessage(role, content, timestamp, attachment) {
    const messagesDiv = document.getElementById('chatMessages');
    const row = document.createElement('div');
    row.className = 'message-row ' + role;

    const avatarIcon = role === 'user' ? 'bi-person' : 'bi-cpu';
    const avatarClass = role === 'user' ? 'user-avatar' : 'ai-avatar';
    const rawHtml = role === 'assistant' ? marked.parse(content) : escapeHtml(content).replace(/\n/g, '<br>');
    const rendered = role === 'assistant' ? DOMPurify.sanitize(rawHtml) : rawHtml;
    const time = timestamp || new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    const badge = attachment ? `<div class="mb-2"><span class="badge text-bg-secondary"><i class="bi bi-paperclip"></i> ${escapeHtml(attachment)}</span></div>` : '';

    row.innerHTML = `
        ${role === 'assistant' ? `<div class="message-avatar ${avatarClass}"><i class="bi ${avatarIcon}"></i></div>` : ''}
        <div class="message-content">
            <div class="message-bubble">${badge}${rendered}</div>
            <div class="message-time">${time}</div>
        </div>
        ${role === 'user' ? `<div class="message-avatar ${avatarClass}"><i class="bi ${avatarIcon}"></i></div>` : ''}
    `;

    messagesDiv.appendChild(row);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;

    row.querySelectorAll('pre code').forEach(block => {
        hljs.highlightElement(block);
    });
}

function showTyping() {
    const messagesDiv = document.getElementById('chatMessages');
    const row = document.createElement('div');
    const id = 'typing-' + Date.now();
    row.className = 'message-row assistant';
    row.id = id;
    row.innerHTML = `
        <div class="message-avatar ai-avatar"><i class="bi bi-cpu"></i></div>
        <div class="message-content">
            <div class="typing-indicator"><span></span><span></span><span></span></div>
        </div>
    `;
    messagesDiv.appendChild(row);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
    return id;
}

function removeTyping(id) {
    const el = document.getElementById(id);
    if (el) el.remove();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function loadSession(sessionId) {
    window.location.href = BASE_URL + 'chat/' + sessionId;
}

function deleteSession(sessionId) {
    if (! confirm('Hapus sesi chat ini?')) return;

    const formData = new FormData();
    formData.append('session_id', sessionId);
    formData.append(CSRF_NAME, getCsrfToken());

    fetch(BASE_URL + 'chat/delete', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.csrf) updateCsrfToken(data.csrf);
        if (data.success) {
            const item = document.querySelector(`[data-session="${sessionId}"]`);
            if (item) item.remove();

            if (document.getElementById('sessionId').value === sessionId) {
                document.getElementById('sessionId').value = '';
                document.getElementById('chatMessages').innerHTML =
                    '<div class="text-center mt-4"><h4 class="fw-bold">AI Coding Assistant</h4>' +
                    '<p class="text-secondary">Tanyakan sesuatu tentang coding.</p></div>';
            }
        }
    });
}

function addSessionToSidebar(sessionId, title) {
    const existing = document.querySelector(`[data-session="${sessionId}"]`);
    if (existing) return;

    document.querySelectorAll('.session-item').forEach(el => el.classList.remove('active'));

    const list = document.getElementById('sessionList');
    const placeholder = list.querySelector('.text-center');
    if (placeholder) placeholder.remove();

    const div = document.createElement('div');
    div.className = 'session-item active';
    div.setAttribute('data-session', sessionId);
    div.onclick = function() { loadSession(sessionId); };
    div.innerHTML = `
        <i class="bi bi-chat-dots"></i>
        <span class="session-title">${escapeHtml(title)}</span>
        <div class="session-actions">
            <button onclick="event.stopPropagation(); deleteSession('${sessionId}')" title="Hapus">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    list.prepend(div);
}

// Scroll to bottom on load
document.addEventListener('DOMContentLoaded', function() {
    const msgs = document.getElementById('chatMessages');
    msgs.scrollTop = msgs.scrollHeight;
});
</script>
<?= $this->endSection() ?>
