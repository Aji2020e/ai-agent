# DB_MAPPING.md — Catatan Pengembangan Database

## AI Coding Assistant

---

## Status Tabel

| Tabel | Status | Keterangan |
|---|---|---|
| `users` | ✅ Selesai | Multi-user, role admin/user |
| `chat_history` | ✅ Selesai | Riwayat chat per user per session |
| `projects` | ✅ Selesai | Data proyek per user |
| `settings` | ✅ Selesai | Pengaturan per user/global |
| `providers` | 🔲 Belum | Provider AI (Ollama / OpenRouter) |
| `agents` | 🔲 Belum | Preset agent (dibuat admin) |
| `skills` | 🔲 Belum | Daftar skill yang tersedia |
| `agent_skills` | 🔲 Belum | Relasi M:N agent ↔ skill |
| `chat_sessions` | 🔲 Belum | Tabel sesi chat (ganti session_id string) |

---

## ERD — Yang Sudah Ada

```
┌─────────────────────┐       ┌─────────────────────────────┐
│       users         │       │       chat_history           │
├─────────────────────┤       ├─────────────────────────────┤
│ id (PK)             │───┐   │ id (PK)                     │
│ username (UNIQUE)   │   │   │ user_id (FK → users)        │
│ email (UNIQUE)      │   ├──▶│ session_id (VARCHAR 36)     │
│ password            │   │   │ title                       │
│ full_name           │   │   │ role (user/assistant/system) │
│ role (admin/user)   │   │   │ content (LONGTEXT)          │
│ avatar              │   │   │ tokens_used                 │
│ is_active           │   │   │ created_at                  │
│ last_login          │   │   └─────────────────────────────┘
│ created_at          │   │
│ updated_at          │   │   ┌─────────────────────────────┐
└─────────────────────┘   │   │       projects               │
                          │   ├─────────────────────────────┤
                          ├──▶│ id (PK)                     │
                          │   │ user_id (FK → users)        │
                          │   │ name                        │
                          │   │ description                 │
                          │   │ path                        │
                          │   │ is_active                   │
                          │   │ created_at                  │
                          │   │ updated_at                  │
                          │   └─────────────────────────────┘
                          │
                          │   ┌─────────────────────────────┐
                          │   │       settings               │
                          │   ├─────────────────────────────┤
                          └──▶│ id (PK)                     │
                              │ user_id (FK → users, NULL)  │
                              │ key                         │
                              │ value                       │
                              │ created_at                  │
                              │ updated_at                  │
                              └─────────────────────────────┘
```

---

## ERD — Yang Direncanakan (Agent System)

```
┌─────────────────────┐       ┌─────────────────────────────┐
│     providers       │       │        agents                │
├─────────────────────┤       ├─────────────────────────────┤
│ id (PK)             │──────▶│ id (PK)                     │
│ name                │       │ name                        │
│  (ollama/openrouter)│       │ description                 │
│ base_url            │       │ provider_id (FK→providers)  │
│ api_key             │       │ model                       │
│ is_active           │       │ system_prompt               │
│ created_at          │       │ is_active                   │
│ updated_at          │       │ created_by (FK→users)       │
└─────────────────────┘       │ created_at                  │
                              │ updated_at                  │
                              └──────────────┬──────────────┘
                                             │
                                             │ 1:N
                                             ▼
                              ┌─────────────────────────────┐
                              │      agent_skills            │
                              ├─────────────────────────────┤
                              │ id (PK)                     │
                              │ agent_id (FK→agents)        │
                              │ skill_id (FK→skills)        │
                              │ config (JSON, optional)     │
                              │ created_at                  │
                              └──────────────┬──────────────┘
                                             │
                                             │ N:1
                                             ▼
┌─────────────────────────────┐
│       chat_sessions          │       ┌─────────────────────────────┐
├─────────────────────────────┤       │        skills                │
│ id (PK)                     │       ├─────────────────────────────┤
│ user_id (FK→users)          │       │ id (PK)                     │
│ agent_id (FK→agents)        │       │ name                        │
│ title                       │       │ description                 │
│ created_at                  │       │ handler_class               │
│ updated_at                  │       │ icon                        │
└──────────────┬──────────────┘       │ is_active                   │
               │                      │ created_at                  │
               │ 1:N                  │ updated_at                  │
               ▼                      └─────────────────────────────┘
┌─────────────────────────────┐
│    chat_history (update)     │
├─────────────────────────────┤
│ id (PK)                     │
│ user_id (FK→users)          │
│ session_id (FK→chat_sessions)│ ← ganti dari VARCHAR ke FK
│ role (user/assistant/system) │
│ content (LONGTEXT)          │
│ tokens_used                 │
│ skill_results (JSON)        │ ← hasil eksekusi skill
│ created_at                  │
└─────────────────────────────┘
```

---

## Detail Field

### providers

| Field | Tipe | Keterangan |
|---|---|---|
| id | INT PK AI | |
| name | VARCHAR(50) | `ollama`, `openrouter` |
| base_url | VARCHAR(500) | URL endpoint provider |
| api_key | VARCHAR(500) | API key (NULL untuk Ollama) |
| is_active | TINYINT(1) | Default 1 |
| created_at | DATETIME | |
| updated_at | DATETIME | |

### agents

| Field | Tipe | Keterangan |
|---|---|---|
| id | INT PK AI | |
| name | VARCHAR(100) | Nama agent, misal "Coding Expert" |
| description | TEXT | Deskripsi agent |
| provider_id | INT FK | Provider yang dipakai |
| model | VARCHAR(100) | Model AI, misal `qwen2.5-coder:32b` / `anthropic/claude-3.5-sonnet` |
| system_prompt | TEXT | Instruksi sistem untuk agent |
| is_active | TINYINT(1) | Default 1 |
| created_by | INT FK | Admin yang membuat |
| created_at | DATETIME | |
| updated_at | DATETIME | |

### skills

| Field | Tipe | Keterangan |
|---|---|---|
| id | INT PK AI | |
| name | VARCHAR(100) | Nama skill, misal "Web Browsing" |
| description | TEXT | Penjelasan skill |
| handler_class | VARCHAR(255) | Class handler, misal `App\Libraries\Skills\WebSearchSkill` |
| icon | VARCHAR(50) | Icon class (FontAwesome) |
| is_active | TINYINT(1) | Default 1 |
| created_at | DATETIME | |
| updated_at | DATETIME | |

### agent_skills

| Field | Tipe | Keterangan |
|---|---|---|
| id | INT PK AI | |
| agent_id | INT FK | Agent yang diberi skill |
| skill_id | INT FK | Skill yang diberikan |
| config | JSON | Konfigurasi khusus skill untuk agent ini (opsional) |
| created_at | DATETIME | |

### chat_sessions

| Field | Tipe | Keterangan |
|---|---|---|
| id | INT PK AI | |
| user_id | INT FK | User pemilik sesi |
| agent_id | INT FK | Agent yang dipakai di sesi ini |
| title | VARCHAR(255) | Judul sesi |
| created_at | DATETIME | |
| updated_at | DATETIME | |

### chat_history (perubahan)

| Field | Tipe | Keterangan |
|---|---|---|
| session_id | ~~VARCHAR(36)~~ → INT FK | Ubah ke FK `chat_sessions.id` |
| skill_results | JSON | ~~belum ada~~ → Hasil eksekusi skill |

---

## Migration Plan

### Fase 1 — Provider & Agent System
1. Buat tabel `providers` + seed default (Ollama, OpenRouter)
2. Buat tabel `agents` + seed default agents
3. Buat tabel `skills` + seed default skills
4. Buat tabel `agent_skills` + assign skill ke agent
5. Buat tabel `chat_sessions`
6. Migrate `chat_history.session_id` dari VARCHAR ke INT FK
7. Tambah kolom `skill_results` ke `chat_history`

### Fase 2 — Backend Logic
1. Provider adapter (OllamaProvider, OpenRouterProvider)
2. Skill interface & handlers
3. Update Chat controller → support agent selection + skill execution
4. Admin pages untuk CRUD agents, skills, providers

### Fase 3 — UI
1. Agent selector di chat UI
2. Admin panel untuk agents & skills
3. Provider configuration page
4. Tampilkan skill results di chat bubble

---

## Notes

- Semua FK menggunakan `ON DELETE CASCADE ON UPDATE CASCADE`
- `chat_history` yang sudah ada perlu dimigrasi (session_id string → chat_sessions)
- OpenRouter API key disimpan di tabel `providers`, bisa diubah lewat admin panel
- Skill handler bersifat extensible — tinggal buat class baru yang implement `SkillInterface`
- `agent_skills.config` (JSON) untuk konfigurasi per-agent, misal:
  ```json
  { "max_results": 5, "search_engine": "google" }
  ```

---

## Spesifikasi Modul Skill

### 1. Web Scraping Skill

**Provider API gratis yang bisa dipakai:**

| Layanan | URL | Catatan |
|---|---|---|
| **DuckDuckGo HTML** | `https://html.duckduckgo.com/html/` | Parse hasil search |
| **Bing Search** (scraping) | `https://www.bing.com/search?q=` | Butuh header & parsing |
| **SearxNG self-hosted** | `http://localhost:8080/search?q=` | Best untuk privacy & gratis |
| **Wikipedia API** | `https://id.wikipedia.org/w/api.php` | Untuk definisi/artikel |
| **Stack Exchange API** | `https://api.stackexchange.com/2.3/` | Untuk coding Q&A |
| **GitHub API** | `https://api.github.com/search/code` | Untuk pencarian kode |

**Arsitektur:**

```
User input
    ↓
Detect intent butuh web search
    ↓
WebSearchSkill.execute(query)
    ↓
Pilih search engine (default DuckDuckGo / SearxNG)
    ↓
Fetch hasil → extract title + snippet + url
    ↓
Fetch halaman relevan (content extraction)
    ↓
Summarize / inject ke prompt AI
    ↓
AI generate jawaban + sumber
```

**Tabel pendukung:**

```sql
CREATE TABLE web_search_cache (
    id INT PK AUTO_INCREMENT,
    query_hash VARCHAR(64),
    engine VARCHAR(50),
    results JSON,
    created_at DATETIME,
    INDEX (query_hash)
);
```

---

### 2. Document Reader / RAG Skill

**Flow:**

```
Upload dokumen (PDF, DOCX, TXT, MD, PPTX)
    ↓
Simpan ke storage (`writable/uploads/documents/`)
    ↓
Ekstrak teks
    ↓
Chunking (by paragraph / fixed size / semantic)
    ↓
Generate embedding → simpan ke vector store
    ↓
User bertanya
    ↓
Embedding query → similarity search
    ↓
Ambil top-k chunks
    ↓
Jadikan context untuk AI
    ↓
Generate jawaban berdasarkan dokumen
```

**Tabel pendukung:**

```sql
CREATE TABLE documents (
    id INT PK AUTO_INCREMENT,
    user_id INT FK,
    file_name VARCHAR(255),
    file_path VARCHAR(500),
    file_size INT,
    mime_type VARCHAR(100),
    total_chunks INT DEFAULT 0,
    is_indexed TINYINT(1) DEFAULT 0,
    created_at DATETIME
);

CREATE TABLE document_chunks (
    id INT PK AUTO_INCREMENT,
    document_id INT FK,
    chunk_index INT,
    chunk_text TEXT,
    embedding JSON, -- atau BLOB untuk vector
    created_at DATETIME
);
```

**Library PHP untuk ekstraksi:**
- PDF: `smalot/pdfparser`
- DOCX: `phpoffice/phpword`
- TXT/MD: native PHP

**Embedding options:**
- Ollama: `nomic-embed-text` (local)
- OpenRouter: model embedding via API

**Vector store options:**
- Sederhana: SQLite + cosine similarity dengan embedding JSON
- Produksi: ChromaDB, Weaviate, atau Qdrant

---

### 3. Academic Skill — Berdasarkan Smart-Sistem-V2

Modul akademik mengintegrasikan fungsi-fungsi akademik dari proyek **smart-sistem-v2** sebagai agent skill.

**Referensi model di smart-sistem-v2:**

| Model | Fungsi Utama |
|---|---|
| `Mmhs` | Data mahasiswa, biodata, prodi |
| `Mkrs` | KRS, nilai, SKS, kelas |
| `Mtahun_akademik` | Tahun akademik, semester, masa studi |
| `Mpengajuan` | Pengajuan akademik |
| `Mdepartment` | Data prodi/department |

**Fungsi akademik yang perlu dibuat sebagai skill:**

#### A. Biodata & Profil Mahasiswa
- `getBiodataMahasiswa(npm)` → Data diri mahasiswa + prodi
- `cariMahasiswa(nama/npm)` → Pencarian mahasiswa

#### B. KRS & Perkuliahan
- `getKrsMahasiswa(npm, thn_ajaran, semester)` → Daftar mata kuliah yang diambil
- `getMataKuliah(kode)` → Detail mata kuliah
- `getKelasAktif(thn_ajaran, semester)` → Daftar kelas aktif

#### C. Nilai & Transkrip
- `getNilaiMahasiswa(npm, thn_ajaran, semester)` → Nilai per semester
- `hitungNilaiAkhir(...)` → Kalkulasi nilai akhir berdasarkan bobot
- `konversiNilaiHuruf(nilai_angka)` → A/B/C/D/E
- `getTranskrip(npm)` → Nilai akumulasi mahasiswa

#### D. SKS & Masa Studi
- `getSksKumulatif(npm, kd_jur)` → Total SKS yang sudah ditempuh
- `getMasaStudi(npm)` → Lama studi mahasiswa
- `getSksSemester(npm, thn_ajaran, semester)` → SKS per semester

#### E. Tahun Akademik
- `getTahunAkademikAktif()` → Tahun akademik yang sedang aktif
- `getTahunAkademikById(id)` → Detail tahun akademik
- `getDaftarSemester(npm)` → Semester yang pernah diikuti

#### F. Pengajuan Akademik
- `getPengajuanMahasiswa(npm)` → Status pengajuan akademik
- `getPengajuanSp(npm)` → Studi pendek / perbaikan

**Tabel pendukung (jika integrasi langsung ke DB smart-sistem-v2):**

| Tabel Eksternal | Keterangan |
|---|---|
| `mhs` | Master data mahasiswa |
| `department` | Master prodi/jurusan |
| `krs` | KRS dan nilai mahasiswa |
| `kuliah` | Master mata kuliah |
| `tahun_akademik` | Master tahun akademik |
| `tahun_aktif` | Tahun akademik yang sedang aktif |
| `akademik_statusmhs` | Status akademik mahasiswa |
| `PENGAJUAN_SP` | Pengajuan studi pendek |

**Implementasi sebagai Skill:**

```php
namespace App\Libraries\Skills;

class AcademicSkill implements SkillInterface
{
    // Bisa pakai DB connection ke smart-sistem-v2
    // atau replicate data via sync/API

    public function execute(array $params): array
    {
        $intent = $params['intent']; // biodata | krs | nilai | sks | pengajuan
        $npm    = $params['npm'];

        return match($intent) {
            'biodata' => $this->getBiodata($npm),
            'krs'     => $this->getKrs($npm, $params['thn_ajaran'], $params['semester']),
            'nilai'   => $this->getNilai($npm, $params['thn_ajaran'], $params['semester']),
            'sks'     => $this->getSks($npm),
            'pengajuan' => $this->getPengajuan($npm),
            default   => ['error' => 'Intent tidak dikenali'],
        };
    }
}
```

**Integrasi database:**
- Bisa pakai multi-DB connection di CI4 (`DBGroup` terpisah untuk smart-sistem-v2)
- Atau sync data penting ke tabel lokal via scheduled job
- Atau expose API endpoint dari smart-sistem-v2 yang di-consume oleh AI agent

**Tabel lokal jika data di-sync:**

```sql
CREATE TABLE academic_students (
    id INT PK AUTO_INCREMENT,
    npm VARCHAR(20) UNIQUE,
    nama VARCHAR(100),
    kd_jur VARCHAR(20),
    nama_dept VARCHAR(100),
    tha VARCHAR(10),
    status VARCHAR(20),
    -- data akademik lainnya
    last_sync DATETIME
);

CREATE TABLE academic_krs (
    id INT PK AUTO_INCREMENT,
    npm VARCHAR(20),
    kode VARCHAR(20),
    mata_kuliah VARCHAR(100),
    sks INT,
    nilai VARCHAR(5),
    thn_ajaran VARCHAR(10),
    semester INT,
    INDEX (npm)
);
```

---

## Skill Routing & Prompt Engineering

### Contoh Router Prompt

```
User input: "berapa nilai saya semester lalu?"

Router AI akan classify: intent = academic, sub_intent = nilai
Ekstrak parameter: npm = <npm user>, semester = sebelumnya
Panggil AcademicSkill dengan parameter tersebut

User input: "cari artikel tentang Laravel terbaru"

Router AI classify: intent = web_search
Panggil WebSearchSkill

User input: "jelaskan teorema Bayes berdasarkan dokumen yang saya upload"

Router classify: intent = document_rag
Panggil DocumentReaderSkill dengan document_id dan query
```

### Prompt Template Setelah Skill Execution

```
Kamu adalah AI Coding & Academic Assistant.
Berikut adalah konteks yang diperoleh dari tools:

[WEB SEARCH RESULTS]
{web_results}

[DOCUMENT CONTEXT]
{relevant_chunks}

[ACADEMIC DATA]
{academic_data}

User question: {user_question}

Jawab dengan akurat, sertakan sumber jika ada. Jika data akademik tidak ditemukan, minta NPM atau informasi tambahan.
```

---

## Performance Optimization

### Untuk Kecepatan

| Teknik | Implementasi |
|---|---|
| Async skill execution | Parallel execution untuk multi-skill |
| Caching hasil web search | Redis / database cache |
| Caching embedding | Jangan re-generate embedding dokumen yang sama |
| Context pruning | Hanya kirim chunk relevan, bukan seluruh dokumen |
| Streaming response | Tampilkan jawaban AI karakter demi karakter |
| Smart model selection | Skill sederhana pakai model kecil; complex reasoning pakai model besar |

### Untuk Smartness

| Teknik | Implementasi |
|---|---|
| Intent classification | AI router paham mau pakai skill apa |
| Entity extraction | Ekstrak NPM, kode mata kuliah, tahun akademik dari input user |
| Multi-hop reasoning | Jika butuh 2+ skill, jalankan secara berurutan |
| Source attribution | Selalu sertakan sumber data |
| Fallback ke general AI | Jika tidak ada skill cocok, jawab dari pengetahuan umum |

---

## Kasus: AI "Kurang Pintar" untuk Obrolan Random Akademik

### Masalah dari Screenshot Portal Akademik

User bertanya secara acak (random conversation):
1. **"Sebutkan nama, jabatan dan email saya"** → AI jawab data mahasiswa: `MUHAMAD RAHMAT SANTOSO (22SA11A001), INFORMATIKA-S1 (55201), angkatan 2022, status Aktif`
2. **"ipk berapa"** → AI jawab: `Data AKM untuk 2018.10.1.035 tidak ditemukan.`
3. **"Sebutkan nama, jabatan dan email saya"** → AI jawab: `Mahasiswa 2018.10.1.035 tidak ditemukan.`

### Analisis Root Cause

| No | Masalah | Penjelasan |
|---|---|---|
| 1 | **Identitas user tidak konsisten** | User login sebagai pegawai/staff (`2018.10.1.035`) tapi AI mencari data sebagai mahasiswa (`NPM`). |
| 2 | **Tidak ada context memory** | AI tidak mengingat jawaban sebelumnya atau identitas user di sesi yang sama. |
| 3 | **Skill routing salah** | Pertanyaan tentang "jabatan dan email" seharusnya ke HR/kepegawaian, bukan ke data akademik mahasiswa. |
| 4 | **Error handling buruk** | Saat data tidak ditemukan, AI tidak menjelaskan apa yang salah atau bertanya kembali. |
| 5 | **Data lookup tidak deterministic** | Query yang sama memberikan hasil berbeda pada pertanyaan berulang. |
| 6 | **No fallback/disambiguation** | AI tidak meminta klarifikasi, langsung mengembalikan error. |

### Solusi yang Harus Diimplementasikan

#### A. User Identity Binding

Saat user login, tentukan jenis identitas:

```php
enum UserType: string
{
    case STAFF   = 'staff';    // NIP / ID pegawai
    case STUDENT = 'student';  // NPM
    case GUEST   = 'guest';
}
```

Simpan di session/profile:

```php
session()->set([
    'user_id'      => 123,
    'user_type'    => 'staff',   // atau 'student'
    'identifier'   => '2018.10.1.035', // NIP atau NPM
    'profile_data' => [...],     // cache data profil
]);
```

#### B. Context Memory per Sesi

Tabel `chat_context` untuk menyimpan state percakapan:

```sql
CREATE TABLE chat_context (
    id INT PK AUTO_INCREMENT,
    session_id INT FK,
    user_id INT FK,
    context JSON, -- { "identified_as": "staff", "last_topic": "biodata", "verified_data": {...} }
    updated_at DATETIME
);
```

Setiap kali AI mendapat informasi baru (misal: ini adalah staff, bukan mahasiswa), update context.

#### C. Skill Router yang Lebih Cerdas

Sebelum panggil skill academic, cek dulu:

```php
function routeSkill(string $userInput, array $context): array
{
    if (str_contains($userInput, 'jabatan') || str_contains($userInput, 'email')) {
        if ($context['user_type'] === 'staff') {
            return ['skill' => 'StaffProfileSkill', 'intent' => 'biodata_pegawai'];
        }
        return ['skill' => 'StudentProfileSkill', 'intent' => 'biodata_mahasiswa'];
    }

    if (str_contains($userInput, 'ipk') || str_contains($userInput, 'nilai')) {
        return ['skill' => 'AcademicSkill', 'intent' => 'nilai_ipk'];
    }

    return ['skill' => 'GeneralAI', 'intent' => 'general'];
}
```

#### D. Response Policy untuk Data Tidak Ditemukan

Jangan biarkan AI hanya mengatakan "tidak ditemukan". Gunakan template:

```
"Maaf, saya tidak menemukan data IPK untuk NPM {npm} pada semester aktif.
Mungkin karena:
1. NPM belum memiliki riwayat nilai
2. Semester yang dimaksud belum diisi
3. NPM salah atau berbeda

Apakah Anda ingin mencoba semester lain?"
```

#### E. Cache & Determinism

- Cache hasil lookup akademik selama 5 menit untuk session yang sama
- Selalu gunakan parameter terdefinisi (tahun akademik aktif, semester aktif)
- Jangan ubah identifier (NIP/NPM) tanpa validasi

#### F. Prompt Template untuk Sistem Akademik

```
Konteks user saat ini:
- Jenis user: {staff/mahasiswa}
- Identifier: {NIP/NPM}
- Tahun akademik aktif: {tha}
- Semester aktif: {semester}
- Data yang sudah diketahui dari percakapan sebelumnya: {context}

Aturan:
1. Jika ditanya "siapa saya", gunakan data profil yang tersimpan.
2. Jika data belum ada, minta identifikasi (NPM/NIP) sebelum melanjutkan.
3. Jangan asumsikan identitas user dari pertanyaan sebelumnya.
4. Jika lookup akademik gagal, jelaskan kemungkinan penyebab dan tawarkan solusi.
5. Selalu jawab dengan bahasa yang sama dengan user.
```

### Checklist Perbaikan

- [ ] Bind user identity saat login (staff vs student)
- [ ] Cache profil user di session/context
- [ ] Router paham beda pertanyaan HR vs akademik
- [ ] Skill academic punya parameter default (tahun akademik aktif)
- [ ] Response policy untuk "data tidak ditemukan"
- [ ] Context memory antar pesan dalam satu sesi
- [x] Bind user identity saat login (staff vs student)
- [x] Cache profil user di session/context
- [x] Router paham beda pertanyaan HR vs akademik
- [x] Skill academic punya parameter default (tahun akademik aktif)
- [x] Response policy untuk "data tidak ditemukan"
- [x] Context memory antar pesan dalam satu sesi
- [x] Logging untuk debug lookup yang gagal

## Status Implementasi

Implementasi perbaikan sudah selesai. Detail:

### Migrasi Database (berhasil dijalankan)
- `AddIdentityFieldsToUsers` — menambah `user_type`, `identifier`, `profile_data`
- `CreateChatSessionsTable` — tabel sesi chat
- `CreateChatContextTable` — tabel context memory

### Koneksi Database Akademik
- Ditambahkan database group `smartSistemV2` ke `app/Config/Database.php`
- Menggunakan SQLSRV driver ke SQL Server `172.16.15.37:1433`
- Database: `DB_AMIKOMS`
- Sudah terverifikasi bisa query tabel `mhs`, `krs`, `kuliah`, `department`

### Model & Skill
- `App\Models\AcademicModel` — query biodata, KRS, nilai, SKS, masa studi
- `App\Libraries\Skills\SkillRouter` — routing intent akademik/profil/umum
- `App\Libraries\Skills\StudentProfileSkill` — biodata mahasiswa
- `App\Libraries\Skills\StaffProfileSkill` — placeholder untuk data pegawai
- `App\Libraries\Skills\AcademicSkill` — lookup data akademik

### Controller & View
- `Chat.php` — `buildContext()` sekarang memuat context memory dan menjalankan skill sebelum kirim ke AI
- `Auth.php` — menyimpan `user_type`, `identifier`, `profile_data` ke session
- `Admin.php` & `admin/users.php` — admin bisa set jenis user dan NPM/NIP saat membuat user

### Testing CLI (tersedia di `app/Commands/`)
- `app:test-smart-sistem` — cek koneksi SQL Server
- `app:test-academic-skill <npm>` — test lookup data akademik
- `app:test-skill-router` — test routing intent

### Perintah Test yang Sudah Dicoba
```bash
php spark app:test-smart-sistem           # ✓ Connected to smart-sistem-v2
php spark app:test-academic-skill S.F202100014  # ✓ Biodata berhasil diambil
php spark app:test-skill-router           # ✓ Routing intent berfungsi
php spark app:test-modular-skills         # ✓ Modular skills berfungsi
```

---

## Hak Akses Skill per API Client

Setiap klien API hanya boleh menggunakan skill yang diizinkan admin.

### Implementasi

- Kolom `api_clients.skills` menyimpan daftar skill yang diizinkan (comma-separated)
- `*` berarti semua skill diizinkan
- `SkillRouter::applyClientSkillRestriction()` mengecek izin sebelum mengeksekusi skill
- Jika skill tidak diizinkan, fallback ke skill `general`

### Contoh Izin

| API Client | Skill Diizinkan | Keterangan |
|---|---|---|
| PORTAL-SMART | `student_profile,academic` | Mahasiswa hanya bisa cek biodata dan nilai |
| smart-sistemv2 | `academic,writing,coding` | Staff akademik bisa akses akademik + writing |
| UJI-ADMIN | `*` | Admin bisa akses semua skill |

### Cara Seting

1. Buka **API & Integrasi** di admin panel
2. Saat membuat/edit klien API, pilih skill yang diizinkan dari multi-select
3. Simpan

---

## Modular Skill System (Implemented)

Sistem skill modular telah diimplementasikan. Setiap skill memiliki handler sendiri, panduan (guide), dan modul yang bisa dikelola admin.

### Struktur File

```
app/
├── Libraries/
│   ├── PromptBuilder.php
│   ├── Skills/
│   │   ├── SkillInterface.php
│   │   ├── BaseSkill.php
│   │   ├── SkillRouter.php
│   │   ├── AcademicSkill.php
│   │   ├── StudentProfileSkill.php
│   │   ├── StaffProfileSkill.php
│   │   ├── WritingSkill.php
│   │   ├── CodingSkill.php
│   │   └── GeneralSkill.php
│   └── Tools/
│       ├── ToolInterface.php
│       ├── ToolRegistry.php
│       ├── ToolResult.php
│       ├── WebSearchTool.php
│       ├── FileReaderTool.php
│       └── DatabaseLookupTool.php
├── Models/
│   ├── SkillModel.php
│   ├── SkillGuideModel.php
│   └── SkillModuleModel.php
└── Controllers/
    └── Skills.php (admin skill management)
```

### Tabel Database

| Tabel | Fungsi |
|---|---|
| `skills` | Daftar skill (academic, writing, coding, general, dll.) |
| `skill_guides` | Prompt/system prompt untuk setiap skill |
| `skill_modules` | Modul panduan yang terhubung dengan skill |

### Skill yang Sudah Ada

| Skill | Deskripsi | Tools |
|---|---|---|
| `academic` | Data akademik dari smart-sistem-v2 | db_lookup |
| `student_profile` | Biodata mahasiswa | db_lookup |
| `writing` | Asisten skripsi/penulisan akademik | file_reader, web_search |
| `coding` | Coding assistant | web_search, file_reader |
| `general` | Obrolan umum | - |

### Alur Kerja

1. User kirim pesan
2. `SkillRouter` menentukan skill berdasarkan keyword
3. `BaseSkill` memuat guide dari `skill_guides`
4. Skill menjalankan tools jika diperlukan
5. `PromptBuilder` menggabungkan hasil menjadi system prompt
6. AI model menghasilkan jawaban final

### Admin Panel

Admin bisa mengelola skill di: `/admin/skills`

- Lihat daftar skill
- Kelola panduan (system prompt) per skill
- Kelola modul panduan per skill
- Aktifkan/nonaktifkan skill

### Perintah Test

```bash
php spark app:test-modular-skills
```

---

## Role-Based Skill Access (RBAC) — Implemented

Setiap role user web (guest/student/dosen/staff/admin) hanya boleh mengakses skill sesuai wewenangnya.

### Migrasi & Seeder

- `CreateRoleSkillsTable` — tabel `role_skills(role, skill_name)`
- `AddDosenUserTypeAndNik` — menambah enum `dosen` di `users.user_type` dan kolom `nik`
- `RoleSkillsSeeder` — memasukkan skill dosen/staff dan mapping default

### Mapping Default

| Role | Skill yang diizinkan |
|---|---|
| `student` | `student_profile`, `academic`, `writing` |
| `dosen` | `dosen_profile`, `dosen_kelas`, `dosen_rps`, `writing_artikel` |
| `staff` | `staff_profile`, `general` |
| `admin` | Semua skill (bypass) |

### Cara Kerja

- `SkillRouter::applyRoleSkillRestriction()` memeriksa `user_type` dari session/context terhadap tabel `role_skills`
- Jika skill tidak diizinkan, fallback ke skill `general` dengan intent `role_restricted`
- Admin memiliki akses penuh tanpa perlu entry di `role_skills`

### Skill Dosen (baru)

| Skill | Fungsi | Sumber Data |
|---|---|---|
| `dosen_profile` | Biodata dosen berdasarkan NIK | `DOSEN` (smart-sistem-v2) |
| `dosen_kelas` | Daftar kelas & mata kuliah yang diampu | `KULIAHTP` + `kuliah` + `tahun_akademik` |
| `dosen_rps` | Asisten pembuatan RPS | `skill_guides` |
| `writing_artikel` | Asisten penulisan artikel ilmiah | `skill_guides` |

### Admin UI

- **Role Skill Mapping**: `/admin/skills/roles` — centang skill yang boleh diakses tiap role
- **Manajemen User**: `/admin/users` — tambah user dengan jenis user **Dosen** dan kolom **NIK**

### Perintah Test

```bash
# Test routing per role
php spark app:test-skill-router student
php spark app:test-skill-router dosen <NIK>
php spark app:test-skill-router staff
```

---

## Multi API Key per Client

Setiap klien API (`api_clients`) sekarang bisa memiliki banyak API key di tabel terpisah `api_keys`. Key bisa ditambah, dinonaktifkan, atau dicabut satu per satu tanpa memengaruhi key lain.

### Skema Baru

- `api_clients` — data klien: nama, modul, skill, model, HMAC.
- `api_keys` — banyak baris per `client_id` dengan kolom:
  - `api_key_hash`
  - `key_prefix`
  - `expires_at`
  - `ip_allowlist`
  - `is_active`
  - `last_used`

### Migrasi

Migration `CreateApiKeysTable` membuat tabel `api_keys` dan memindahkan key lama dari `api_clients`. Key lama tetap berfungsi tanpa perubahan di sisi consumer.

### Model

- `ApiClientModel` — mengelola data klien, otomatis membuat key pertama saat `createClient()`.
- `ApiKeyModel` — mengelola key:
  - `createKey(clientId, options)`
  - `findByKey(plainKey)` — autentikasi lookup
  - `forClient(clientId)` — daftar key per klien
  - `toggle(id)`, `revoke(id)`

### Autentikasi

`ApiKeyFilter` sekarang mencocokkan header `X-API-Key` / `Authorization: Bearer` ke tabel `api_keys`:

1. Lookup key → gabung data `api_clients`.
2. Cek `api_keys.is_active` dan `api_clients.is_active`.
3. Cek expiry & IP allowlist per key.
4. Rate limit per key (`apikey_<key_id>`).
5. Update `last_used` di `api_keys`.

### Admin UI

Di `/admin/api`, setiap baris klien sekarang bisa di-expand untuk menampilkan daftar key:

- Tambah key baru (dengan expiry & IP allowlist).
- Toggle aktif/nonaktif per key.
- Revoke (hapus) per key.
- HMAC, toggle, dan hapus klien tetap di level klien.

### Perintah Test

```bash
php spark app:test-api-keys
```

---

## Multi API Key untuk Provider AI

Provider AI bertipe *OpenAI-compatible* (OpenAI, OpenRouter, Groq, DeepSeek, dll.) sekarang bisa menyimpan **banyak API key** di setting `openai_key_map`. Setiap Base URL (provider) punya bucket kunci terpisah — jadi key dari Groq (`gsk_...`) tidak tercampur dengan key dari OpenAI (`sk-...`).

### Skema Penyimpanan

- Setting `openai_key_map` menyimpan JSON terenkripsi berbentuk peta:
  ```json
  {
    "https://api.openai.com/v1":          ["sk-key-openai-1", "sk-key-openai-2"],
    "https://openrouter.ai/api/v1":       ["sk-or-key-1"],
    "https://api.groq.com/openai":        ["gsk_key-1", "gsk_key-2"]
  }
  ```
- `SettingModel::getSecretMap('openai_key_map')` → return array `[base_url => [keys]]`
- `SettingModel::setSecretMap('openai_key_map', $map)` → simpan seluruh peta
- Tiap Base URL punya daftar kunci sendiri yang hanya aktif saat provider tersebut dipilih.

### Cara Kerja

- `AiClient::currentConfig()` membaca kunci sesuai Base URL yang sedang aktif.
- Saat chat OpenAI gagal (HTTP 401/429/5xx atau error jaringan), `AiClient::chatOpenAi()` otomatis mencoba key berikutnya dalam bucket yang sama.
- Backward compatibility: bila `openai_keys` (struktur lama) ada tapi `openai_key_map` kosong, sistem akan migrasi otomatis.

### Admin UI

Di halaman **Pengaturan AI → API Key**:

- Nama provider ditampilkan dari hostname Base URL (misal: `api.openai.com`, `openrouter.ai`).
- Ringkasan semua provider yang pernah diset ditampilkan di atas daftar key.
- Tombol *Tambah API Key* menambah input key baru untuk provider yang sedang aktif.
- Tombol *hapus* (tong sampah) menghapus baris key.
- **Tombol petir** di setiap baris key menguji koneksi per key secara individual.

### Penggunaan

1. Pilih provider **API Key**.
2. Isi Base URL dan model.
3. Tambahkan satu atau beberapa API key.
4. Klik tombol **petir** di samping key untuk menguji koneksi individual.
5. Klik **Simpan** untuk menyimpan semua perubahan.

### Tes Koneksi

Terdapat dua jenis tes koneksi:

1. **Tes Semua Key** (tombol utama): Menguji SEMUA key yang ada di bucket provider saat ini dan menampilkan hasil per key:
   - ✓ Berhasil: key valid dan dapat terhubung
   - ✗ Gagal: key invalid, expired, atau tidak dapat terhubung
   - Daftar model diambil dari key pertama yang berhasil.

2. **Tes Per Key** (tombol petir individual): Menguji satu key spesifik tanpa menyimpan perubahan.

**Catatan**: *Tes Semua Key* akan menguji setiap key yang tersedia di bucket provider yang sama dan mengembalikan hasil detail untuk masing-masing key.

