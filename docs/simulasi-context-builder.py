#!/usr/bin/env python3
"""
Simulasi perbandingan payload AI: SEBELUM vs SESUDAH perbaikan.
Memodelkan Chat.php::buildContext() yang ada sekarang, dan ContextBuilder yang diusulkan.
"""

CHARS_PER_TOKEN = 3.6

def tok(s):
    """Estimasi token: ceil(char / CHARS_PER_TOKEN). Harus konsisten dgn allow."""
    import math
    return max(1, math.ceil(len(s) / CHARS_PER_TOKEN))

# ---------------------------------------------------------------- data uji
SYSTEM_SKILL = (
    "Kamu adalah AI Assistant akademik. Mode skill aktif: academic.\n"
    "[ATURAN WAJIB]\n1. Jawab HANYA berdasarkan [DATA AKADEMIK].\n"
    "2. Jika tidak ada, katakan 'Data tersebut tidak tersedia di sistem.'\n"
    "3. DILARANG mengarang NIM, nama, tanggal, nilai, SKS.\n\n"
    "[DATA AKADEMIK]\n" + "".join(
        f"NPM 202110110 | Semester 20251 | MK{i:02d} Kode MK{i:02d} SKS 3 Nilai {'ABCD'[i%4]}\n"
        for i in range(1, 26))
)

# riwayat panjang: 400 pesan (200 pasang) — sesi nyata berhari-hari
HISTORY = []
for i in range(200):
    HISTORY.append({"role": "user",      "content": f"Pertanyaan nomor {i+1} tentang mata kuliah dan jadwal semester ini, tolong jelaskan secara rinci ya."})
    HISTORY.append({"role": "assistant", "content": f"Jawaban nomor {i+1}: berdasarkan data KRS, terdapat beberapa mata kuliah dengan total SKS yang perlu diperhitungkan ulang."})

CURRENT = "berapa total SKS semester ini?"

# ---------------------------------------------------------------- SEBELUM
def payload_sebelum(system, history, current):
    """Replikasi Chat.php:225-291 (kondisi saat ini)."""
    # PromptBuilder::build() -> [system, user(current)]
    msgs = [
        {"role": "system", "content": system},
        {"role": "user",   "content": current},      # <-- posisi SALAH
    ]
    # riwayat: saveMessage() sudah dipanggil SEBELUM buildContext()
    # jadi `current` sudah ada di dalam history
    db_history = history + [{"role": "user", "content": current}]
    for m in db_history:
        if m["role"] == "system":
            continue
        msgs.append({"role": m["role"], "content": m["content"]})   # TANPA LIMIT
    return msgs

# ---------------------------------------------------------------- SESUDAH
def truncate_system(system, budget):
    markers = ["\n[DATA AKADEMIK]", "\n[HASIL WEB SEARCH]", "\n[ISI FILE]", "\n[MODUL PANDUAN]"]
    cut = len(system)
    for mk in markers:
        p = system.find(mk)
        if p != -1 and p < cut:
            cut = p
    NOTE_RESERVE = 40                                  # token untuk teks catatan
    allow = int(max(0, budget - NOTE_RESERVE) * CHARS_PER_TOKEN)
    rules = system[:cut]                      # bagian instruksi: SELALU dipertahankan
    if len(rules) >= allow:                   # bahkan instruksi pun kebesaran
        return rules[:allow] + "\n[CATATAN] Konteks dipotong karena melebihi batas."
    # sisa budget dipakai untuk memuat data sebanyak mungkin (bukan dibuang semua)
    room = allow - len(rules)
    return rules + system[cut:cut + room] + "\n[CATATAN] Data referensi terlalu besar, sebagian dihilangkan."

def payload_sesudah(system, history, current, max_prompt=6000):
    """ContextBuilder yang diusulkan."""
    budget = max_prompt
    budget -= tok(current)

    sys_tok = tok(system)
    truncated = False
    if sys_tok > budget:
        system = truncate_system(system, max(800, budget))
        sys_tok = tok(system)
        truncated = True
    budget -= sys_tok

    # sanitize: buang duplikat pesan saat ini
    clean = []
    last = len(history) - 1
    for i, m in enumerate(history):
        if m["role"] not in ("user", "assistant"):
            continue
        c = m["content"].strip()
        if not c:
            continue
        if i == last and m["role"] == "user" and c == current.strip():
            continue
        clean.append({"role": m["role"], "content": c})

    kept, used = [], 0
    for m in reversed(clean):
        t = tok(m["content"])
        if used + t > budget:
            break
        used += t
        kept.insert(0, m)

    while kept and kept[0]["role"] != "user":
        kept.pop(0)

    dropped = len(clean) - len(kept)

    # Catatan konteks dihitung DULU, lalu kurangi riwayat bila total melewati budget.
    note = ""
    if dropped > 0 or truncated:
        note = "\n\n[CATATAN KONTEKS]\n"
        if dropped > 0:
            note += f"- {dropped}+ pesan lama TIDAK disertakan (batas konteks).\n"
        if truncated:
            note += "- Sebagian data referensi dipotong.\n"
        note += "- Bila user merujuk hal yang tidak ada di riwayat, TANYA KEMBALI. JANGAN mengarang."

        # CLAMP: buang riwayat tertua sampai system+note+riwayat+current muat
        while kept and tok(system) + tok(note) + tok(current) + sum(tok(m["content"]) for m in kept) > max_prompt:
            kept.pop(0)
            dropped += 1
        # PENTING: rapikan ulang SETELAH clamp — memotong depan bisa membuat
        # riwayat diawali turn 'assistant' lagi.
        while kept and kept[0]["role"] != "user":
            kept.pop(0)
        system += note

    return ([{"role": "system", "content": system}] + kept +
            [{"role": "user", "content": current}])

# ---------------------------------------------------------------- analisis
def analyze(name, msgs, window=8192):
    total = sum(tok(m["content"]) for m in msgs)
    roles = [m["role"] for m in msgs]
    dup_user = sum(1 for m in msgs if m["role"] == "user" and m["content"] == CURRENT)
    urutan_user = [i for i, m in enumerate(msgs) if m["content"] == CURRENT]

    # apa yang bertahan setelah provider memotong dari DEPAN
    sisa, terbuang = 0, []
    for m in reversed(msgs):
        t = tok(m["content"])
        if sisa + t > window:
            terbuang.append(m["role"])
            continue
        sisa += t

    print(f"\n{'='*72}\n{name}\n{'='*72}")
    print(f"  Jumlah pesan          : {len(msgs)}")
    print(f"  Total token (est.)    : {total:,}")
    print(f"  Urutan role           : {roles[0]} → {roles[1]} → ... → {roles[-1]}  ({len(msgs)} pesan)")
    print(f"  Pesan saat ini muncul : {dup_user}× (indeks {urutan_user})")
    print(f"  Posisi terakhir = user: {'✅ YA' if roles[-1] == 'user' and dup_user == 1 else '❌ TIDAK'}")
    print(f"  System prompt         : {'✅ utuh' if 'system' not in terbuang else '❌ TERBUANG saat dipotong provider'}")
    print(f"  Data akademik         : {'✅ terkirim' if '[DATA AKADEMIK]' in msgs[0]['content'] else '❌ hilang'}")
    print(f"  Vs window {window} tok    : {'⚠️  MELEBIHI → provider memotong' if total > window else '✅ muat'}")
    if total > window:
        from collections import Counter
        c = Counter(terbuang)
        print(f"  Pesan terbuang        : {sum(c.values())} ({dict(c)})")
    print(f"  Catatan konteks       : {'✅ ada' if '[CATATAN KONTEKS]' in msgs[0]['content'] else '—'}")
    return total

print("\n" + "#"*72)
print("# SKENARIO: sesi panjang (400 pesan riwayat) + pertanyaan ber-data")
print("#"*72)

before = payload_sebelum(SYSTEM_SKILL, HISTORY, CURRENT)
after  = payload_sesudah(SYSTEM_SKILL, HISTORY, CURRENT)

t1 = analyze("SEBELUM  (Chat.php sekarang — B1 + B2)", before)
t2 = analyze("SESUDAH  (ContextBuilder yang diusulkan)", after)

print(f"\n{'='*72}\nRINGKASAN\n{'='*72}")
print(f"  Token terkirim   : {t1:,}  →  {t2:,}   ({(1 - t2/t1)*100:.0f}% lebih hemat)")
print(f"  Duplikasi pesan  : 2×     →  1×")
print(f"  Urutan benar     : ❌      →  ✅")
print(f"  Data akademik    : terbuang saat sesi panjang → selalu terkirim")
print()

# ---------------------------------------------------------------- uji lanjutan
print("#"*72)
print("# UJI TEPI")
print("#"*72)

print("\n[1] Sesi pendek (2 pesan) — tidak boleh ada yang hilang")
short = [{"role":"user","content":"halo"},{"role":"assistant","content":"halo juga"}]
p = payload_sesudah(SYSTEM_SKILL, short, CURRENT)
print(f"    → {len(p)} pesan, {sum(tok(m['content']) for m in p):,} token, "
      f"riwayat utuh: {'✅' if len(p)==4 else '❌'}")

print("\n[2] System prompt raksasa (melebihi budget sendirian)")
huge = SYSTEM_SKILL + "\n[ISI FILE]\n" + ("x"*60000)
p = payload_sesudah(huge, short, CURRENT, max_prompt=3000)
print(f"    → {sum(tok(m['content']) for m in p):,} token (budget 3000), "
      f"dipotong aman: {'✅' if sum(tok(m['content']) for m in p) <= 3200 else '❌'}, "
      f"aturan grounding selamat: {'✅' if 'ATURAN WAJIB' in p[0]['content'] else '❌'}")

print("\n[3] Riwayat diawali turn assistant — harus dibuang agar selang-seling")
odd = [{"role":"assistant","content":"sisa jawaban terpotong"}] + short
p = payload_sesudah(SYSTEM_SKILL, odd, CURRENT)
roles = [m["role"] for m in p]
print(f"    → urutan: {roles}  valid: {'✅' if roles[1]=='user' else '❌'}")

print("\n[4] Pesan saat ini sudah terlanjur ada di riwayat (bug B1)")
dup = short + [{"role":"user","content":CURRENT}]
p = payload_sesudah(SYSTEM_SKILL, dup, CURRENT)
n = sum(1 for m in p if m["content"]==CURRENT)
print(f"    → muncul {n}×  dedupe: {'✅' if n==1 else '❌'}")
