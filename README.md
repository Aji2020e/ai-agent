# AI Coding Assistant

Aplikasi web AI Coding Assistant dengan dukungan **multi-user** dan riwayat chat terpisah per user.

## Tech Stack

| Komponen | Teknologi |
|---|---|
| Backend | CodeIgniter 4 (PHP 8.1+) |
| Frontend | AdminLTE 3 (Bootstrap 4) |
| AI Engine | Ollama (qwen2.5-coder:32b) |
| Database | MySQL/MariaDB |
| Deployment | Docker + Coolify |

## Fitur

- **Multi-user** — setiap user memiliki chat history terpisah
- **Multi-session chat** — buat banyak sesi percakapan
- **Admin panel** — kelola user (aktifkan/nonaktifkan/hapus)
- **Profile management** — edit profil dan ubah password
- **Markdown rendering** — respon AI ditampilkan dengan format markdown + syntax highlighting
- **Token tracking** — pantau penggunaan token per user
- **Responsive UI** — AdminLTE 3 dashboard

## Quick Setup (Laragon / Local)

### 1. Buat Database

```sql
CREATE DATABASE ai_agent CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
```

Atau import file `database.sql`:

```bash
mysql -u root < database.sql
```

### 2. Konfigurasi .env

File `.env` sudah tersedia. Sesuaikan jika perlu:

```env
database.default.hostname = localhost
database.default.database = ai_agent
database.default.username = root
database.default.password =

app.ollamaUrl = 'http://localhost:11434'
app.ollamaModel = 'qwen2.5-coder:32b'
```

### 3. Jalankan Migrasi & Seeder (opsional, jika tidak pakai database.sql)

```bash
php spark migrate --all
php spark db:seed DefaultUserSeeder
```

### 4. Akses Aplikasi

Buka browser: `http://localhost/ai-agent/public/`

### 5. Login Default

| Field | Value |
|---|---|
| Username | `admin` |
| Password | `admin123` |

## Setup dengan Docker

```bash
docker-compose up -d
```

Akses: `http://localhost:8080`

### Pull model Ollama di container:

```bash
docker exec -it ai-agent-ollama ollama pull qwen2.5-coder:32b
```

## Deploy ke Coolify

1. Buat **Aplikasi → Dockerfile**, arahkan ke repo ini.
2. Tambahkan database MySQL (atau pakai yang sudah ada).
3. Isi **Environment Variables**:

| Key | Contoh |
|---|---|
| `CI_ENVIRONMENT` | `production` |
| `app.baseURL` | `https://ai.domainmu.id/` |
| `database.default.hostname` | host mysql |
| `database.default.database` | `ai_agent` |
| `database.default.username` | `ai_agent` |
| `database.default.password` | `***` |
| `encryption.key` | hasil `php spark key:generate --show` |
| `app.ollamaUrl` | `http://<host-ollama>:11434` |
| `app.ollamaModel` | `qwen2.5-coder:7b` |

4. Deploy — entrypoint otomatis: tunggu MySQL → buat DB → `migrate --all` → seed admin → nyalakan Apache. Healthcheck `/` aktif (start-period 120 dtk).
5. Opsional (integrasi akademik): isi juga `database.akademik.*` — atau lewat menu **API & Integrasi** setelah login. Image sudah berisi driver **SQL Server** (`sqlsrv`/`pdo_sqlsrv`) + MySQL.

## Struktur Proyek

```
app/
├── Config/           # Konfigurasi CI4
├── Controllers/
│   ├── Auth.php      # Login, register, logout
│   ├── Chat.php      # AI chat (kirim, hapus sesi, rename)
│   ├── Dashboard.php # Dashboard statistik
│   ├── Admin.php     # Manajemen user (admin only)
│   ├── Profile.php   # Edit profil & password
│   └── Home.php      # Redirect ke dashboard/login
├── Database/
│   ├── Migrations/   # Schema database
│   └── Seeds/        # Data awal (admin user)
├── Filters/
│   ├── AuthFilter.php  # Proteksi route (harus login)
│   └── AdminFilter.php # Proteksi route admin
├── Helpers/
│   └── chat_helper.php # Helper render pesan chat
├── Models/
│   ├── UserModel.php        # CRUD user
│   ├── ChatHistoryModel.php # CRUD chat history (per user)
│   ├── ProjectModel.php     # CRUD project
│   └── SettingModel.php     # CRUD settings
└── Views/
    ├── layouts/      # Base template (main + auth)
    ├── auth/         # Login & Register
    ├── chat/         # Halaman chat AI
    ├── dashboard/    # Dashboard
    ├── admin/        # Panel admin
    └── profile/      # Profil user
```

## Multi-User Architecture

Setiap pesan chat disimpan dengan `user_id` sehingga:
- User A hanya bisa melihat chat history miliknya sendiri
- User B memiliki sesi dan riwayat terpisah
- Admin dapat mengelola semua user melalui panel admin
- Foreign key `CASCADE` — menghapus user otomatis menghapus semua chat-nya

## Integrasi Akademik (API)

Aplikasi akademik (siakad dsb.) membuka koneksi **ke** aplikasi ini via REST API.
Aplikasi ini membaca DB akademik **read-only** (hanya SELECT, tidak ada tulis).

**Kontrak portal (JSON):** `{id, role, reason}` — alias didukung
(`npm/nim/nik`, `role: mhs/dosen/staff`, `module:` eksplisit, `reason/pertanyaan`).

```bash
# 1. Portal cek modul yang boleh diakses peran ini
curl -H "X-API-Key: <key>" .../api/modules?role=mhs

# 2. Callback satu pintu (rute otomatis by peran + topik)
curl -H "X-API-Key: <key>" -H "Content-Type: application/json" \
  -d '{"npm":"17.11.0212","role":"mhs","reason":"MK apa yang diambil semester depan?"}' \
  .../api/ask

# 3. Langsung ke modul (bawaan atau hasil wizard)
curl -H "X-API-Key: <key>" \
  -d "nim=17.11.0212" -d "reason=Apakah saya sudah lulus MK IF113?" \
  .../api/mahasiswa/analyze
```

| Endpoint | Fungsi |
|---|---|
| `GET /api/status` | Kesehatan + provider & DB akademik |
| `GET /api/modules` | Modul + contoh request per peran |
| `POST /api/ask` | Router peran/topik (atau klarifikasi opsi) |
| `POST /api/{mahasiswa,dosen,staff}/analyze` | Modul bawaan |
| `POST /api/assistant/{slug}/analyze` | Modul hasil wizard |

**Keamanan API:** key 256-bit (hash SHA-256) + expiry + IP allowlist +
rate-limit 120/mnt + anti brute-force 30 gagal/mnt + HMAC-SHA256 opsional
(`X-Timestamp` ±5 mnt, anti-replay). Atur di menu **API & Integrasi**.

**Modul & pengetahuan:** menu **Modul Asisten** (wizard: info → sumber DB +
panduan → endpoint + key otomatis), **Kamus Data** (tabel/kolom resmi per
topik agar AI tidak salah baca dari 800+ tabel), **Panduan Akademik**,
dan **Peta Peran → Modul**. AI bekerja 2 tahap: analis data → penyusun
bahasa akademik yang hangat (gaya bisa ditimpa via settings `answer_style`).

### Upload Panduan Multi-Format

Di menu **Admin → API & Integrasi → Panduan Akademik**, Anda bisa upload
beberapa file sekaligus untuk dijadikan konteks AI.

- Format didukung: `doc`, `docx`, `pdf`, `png/jpg/jpeg/webp/gif/bmp/tif/tiff`, `txt`, `md`, `csv`, `json`, `xml`, `html`, `yml`, `rtf`
- Batas ukuran: **10 MB per file**
- Hasil upload disimpan ke `api_docs` dan otomatis dibaca oleh modul sesuai `module` + `umum`

Catatan server:
- OCR gambar butuh `tesseract`
- PDF berbasis teks bisa dibaca via `pdftotext` (poppler)
- File `.doc` butuh `antiword`

Image Docker proyek ini sudah disiapkan untuk tool tersebut (`poppler-utils`,
`tesseract-ocr`, `antiword`).

## Provider AI

Menu admin **Pengaturan AI** — tanpa edit file:

| Provider | Keterangan |
|---|---|
| Ollama | Lokal/server sendiri (`/api/chat`, `/api/tags`) |
| API Key | OpenAI-compatible: OpenAI, Groq, DeepSeek, OpenRouter (`/chat/completions` + Bearer) |
| OpenCode | `opencode serve` (`/session`, `/config/providers`, basic auth opsional) |

API key & password tersimpan terenkripsi. Tombol **Tes Koneksi** menampilkan
model terinstal sekaligus mengisi dropdown model (juga di halaman chat).
