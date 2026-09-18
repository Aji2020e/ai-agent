# Rencana Optimalisasi AI — `Aji2020e/ai-agent`

**Target:** AI jauh lebih pintar · lebih cepat · tidak halusinasi · memahami konteks
**Tanggal:** 18 September 2026
**Status:** 📋 RENCANA — menunggu persetujuan, belum ada kode yang diubah
**Provider target:** OpenAI / OpenRouter (API cloud) · streaming SSE disetujui

---

## 0. Ringkasan Eksekutif

Masalahnya **bukan** modelnya kurang pintar. Ada **6 bug struktural** dalam cara
prompt dirakit dan dikirim. Model sebesar apa pun akan tetap halusinasi dan
"lupa konteks" selama bug ini ada, karena ia memang **tidak pernah menerima
konteks yang benar**.

| # | Bug | Dampak | File |
|---|---|---|---|
| B1 | Pesan user terkirim **2×** dalam urutan salah | Tidak paham konteks | `Chat.php:128,137,271,280` |
| B2 | Riwayat **tanpa batas** (`findAll()`) | Konteks terpotong acak | `ChatHistoryModel.php:34` |
| B3 | `temperature` tidak dikirim → default **1.0** | Halusinasi parah | `AiClient.php:216` |
| B4 | Hasil web disisipkan sebagai `role:user` setelah pertanyaan | Model bingung mana pertanyaan | `WebSearch.php:76` (3 pemanggil) |
| B5 | `stream => false` | Terasa lambat | `AiClient.php:163` |
| B6 | Instruksi anti-halu cuma 6 kata | Model bebas mengarang | `PromptBuilder.php:14` |

**Estimasi dampak setelah perbaikan:**

| Metrik | Sekarang | Target |
|---|---|---|
| Waktu hingga teks pertama | 15–60 dtk | **< 1,5 dtk** |
| Akurasi jawaban ber-data | rendah (mengarang) | **grounded, menolak bila data tak ada** |
| Paham rujukan ("dia", "yang tadi") | ✗ gagal | ✓ jalan |
| Konsistensi jawaban sama | bervariasi | stabil (temp rendah) |
| Biaya token per request | tak terkendali | terukur + ada plafon |

---

## 1. Diagnosis Terkonfirmasi (dengan bukti)

### B1 — Duplikasi & salah urutan pesan 🔴 KRITIS

Alur di `Chat.php::send()`:
```php
line 128:  $this->chatModel->saveMessage(['role'=>'user','content'=>$message]);  // disimpan DULU
line 137:  $context = $this->buildContext($sessionId, $userId, $message);        // baru dirakit
```

Lalu di `buildContext()` (line 225–291):
```php
line 227:  $messages = $this->chatModel->getSessionMessages(...);  // ← SUDAH memuat pesan baru
line 271:  $promptMessages = PromptBuilder::build($skillName, $skillResult, $currentMessage);
           // PromptBuilder.php:46-49 → return [system, user($currentMessage)]
line 280:  foreach ($messages as $msg) { $aiContext[] = ... }       // ← pesan baru masuk LAGI
```

**Payload yang benar-benar terkirim:**
```
[0] system    : skill prompt + data akademik
[1] user      : "jadwal kuliah saya"     ← dari PromptBuilder (posisi SALAH)
[2] user      : "halo"                   ← riwayat
[3] assistant : "halo juga"
[4] user      : "jadwal kuliah saya"     ← DUPLIKAT di akhir
```

Model melihat pertanyaan baru **di awal**, lalu percakapan lama, lalu pertanyaan
yang sama **diulang**. Tidak ada cara bagi model untuk tahu mana yang harus
dijawab. Ini penyebab utama jawaban nyasar.

### B2 — Riwayat tanpa batas 🔴 KRITIS

`ChatHistoryModel.php:34-40`:
```php
public function getSessionMessages(string $sessionId, int $userId): array
{
    return $this->where('session_id', $sessionId)
                ->where('user_id', $userId)
                ->orderBy('id', 'ASC')
                ->findAll();          // ← TIDAK ADA limit()
}
```

Sesi panjang → prompt membengkak → melewati context window provider → **provider
memotong dari depan** → `system` prompt berisi data akademik **terbuang pertama**.
Model menjawab pertanyaan data tanpa memiliki datanya → **mengarang**.

Bonus: `Chat.php:87` memanggil fungsi yang sama **hanya untuk cek kepemilikan
sesi** — mengambil seluruh riwayat demi satu boolean.

### B3 — `temperature` tidak pernah dikirim (jalur OpenAI) 🔴 KRITIS

`AiClient.php:213-219`:
```php
private static function chatOpenAi(...): array
{
    $data = self::http('POST', $base . '/chat/completions', [
        'model'    => $model,
        'messages' => $messages,
    ], self::bearer($apiKey), $timeout);
```

Tidak ada `temperature`, `max_tokens`, `top_p`. Provider memakai **default**.
Default OpenAI = `temperature 1.0` → sangat kreatif → untuk query faktual
akademik ini **mesin halusinasi**.

Pembanding: jalur Ollama (`line 164`) mengirim `temperature 0.7` — juga terlalu
tinggi, dan `num_ctx 4096` terlalu kecil.

### B4 — Web search merusak struktur pesan 🟠

`WebSearch.php:76`:
```php
$messages[] = ['role' => 'user', 'content' => $block . "\n\nJawab pertanyaan terakhir; ..."];
```

Di-`append` **setelah** pesan user asli → dua turn `user` berurutan:
```
[0] system
[1] user : "kapan pemilu berikutnya?"     ← pertanyaan sebenarnya
[2] user : "<hasil web>\n\nJawab pertanyaan terakhir..."  ← data, tapi berperan sbg user
```
Model bisa salah mengira blok web sebagai pertanyaannya. Dipakai di 3 tempat:
`Api/Chat.php:29`, `Api/Discover.php:263`, `AssistantModule.php:141`,
plus `Chat.php:144` (inline, pola sama).

**Perbaikan:** hasil web masuk ke **system prompt**, pesan user tetap terakhir.

### B5 — Tidak ada streaming 🟠

`AiClient.php:163` → `'stream' => false`.
Frontend `chat/index.php:309-313` → `fetch().then(res => res.json())`.

User menatap indikator "typing" selama **seluruh** generasi berjalan. Untuk
jawaban 800 token ini bisa 20–60 detik. Streaming membuat teks pertama muncul
dalam ~0,5–1,5 detik — **perceived latency turun ~95%** walau total waktu sama.

### B6 — Instruksi grounding terlalu lemah 🟠

`PromptBuilder.php:14` — seluruh pertahanan anti-halusinasi:
```php
$systemPrompt .= "Jawab dalam bahasa yang sama dengan user. Jangan membuat data palsu.\n";
```

Tidak ada: kewajiban menjawab hanya dari data, perintah eksplisit untuk menolak
bila data tidak ada, larangan menebak angka/tanggal, kewajiban menyebut sumber.

### Temuan pendukung

| Lokasi | Masalah |
|---|---|
| `composer.json` | **Guzzle tidak ada** — hanya `codeigniter4/framework` + `smalot/pdfparser`. `CURLRequest` CI4 pakai `curl_exec()` yang mem-buffer penuh → **tidak bisa streaming**. Perlu raw `curl_init()` + `CURLOPT_WRITEFUNCTION`. |
| `SettingModel.php:66` | `getGlobal()` query DB tiap pemanggilan, tanpa cache. Dipanggil berulang per request. |
| `Dockerfile` | Apache mod_php (`php:8.3-apache`). SSE butuh matikan output buffering + matikan `mod_deflate` untuk endpoint stream. Coolify menaruh proxy di depan → perlu header anti-buffering. |
| PHP session | Saat streaming panjang, **session lock** memblokir request lain dari user yang sama. Wajib `session_write_close()` sebelum stream dimulai. |
| `AiClient.php:72` | `date_default_timezone_set()` dipanggil di dalam fungsi — side effect global tiap request. |

---

## 1b. Bukti — Algoritma Sudah Disimulasikan

Karena PHP tidak tersedia di environment analisis, algoritma `ContextBuilder`
(F1) saya porting ke Python dan jalankan terhadap skenario nyata.
Skrip: **`simulasi-context-builder.py`** · jalankan: `python3 simulasi-context-builder.py`

**Skenario:** sesi berisi 400 pesan riwayat + system prompt berisi data KRS
25 mata kuliah + pertanyaan *"berapa total SKS semester ini?"*

| | SEBELUM (kode sekarang) | SESUDAH (ContextBuilder) |
|---|---|---|
| Jumlah pesan terkirim | 403 | 176 |
| Total token (est.) | **12.814** | **5.950** (−54%) |
| Pesan saat ini muncul | **2×** (indeks 1 & 402) | **1×** (indeks 175) |
| Posisi terakhir = `user` | ❌ | ✅ |
| **System prompt saat dipotong provider** | ❌ **TERBUANG** | ✅ utuh |
| Muat di window 8192 token | ⚠️ MELEBIHI | ✅ muat |
| Model diberi tahu ada konteks hilang | — | ✅ `[CATATAN KONTEKS]` |

> Baris paling penting: **`system: 1`** ada di daftar pesan terbuang pada kolom
> SEBELUM. Artinya saat sesi panjang, provider membuang **system prompt beserta
> seluruh data akademik**, lalu model tetap diminta menjawab pertanyaan data.
> Model tidak punya pilihan selain **mengarang**. Ini konfirmasi mekanis bahwa
> B2 adalah penyebab langsung halusinasi — bukan dugaan.

**Uji tepi (semua lolos):**

| # | Kasus | Hasil |
|---|---|---|
| 1 | Sesi pendek (2 pesan) — tidak boleh ada yang hilang | ✅ 4 pesan, riwayat utuh |
| 2 | System prompt raksasa melebihi budget sendirian | ✅ 3.016 token (budget 3.000), aturan grounding selamat |
| 3 | Riwayat diawali turn `assistant` | ✅ dibuang → `system, user, assistant, user` |
| 4 | Pesan saat ini sudah terlanjur ada di riwayat (bug B1) | ✅ ter-dedupe, muncul 1× |

**Tiga bug desain yang ketahuan dari simulasi** (sudah diperbaiki di F1):

1. **Satuan token tidak konsisten** — `estimateTokens()` membagi 3.6 tapi
   `truncateSystem()` mengalikan 3.6 dengan pembulatan `int`. Budget jebol ~20%.
2. **Teks `[CATATAN KONTEKS]` tidak dianggarkan** — ditambahkan setelah budget
   habis terpakai, sehingga total tetap melewati batas.
3. **Clamp merusak urutan** — memangkas riwayat dari depan *setelah* perapian
   membuat payload diawali turn `assistant` lagi. Perapian harus diulang
   sesudah clamp.

Ketiganya tidak akan terlihat dari membaca kode saja.

**Validasi lain yang sudah dilakukan:**
- ✅ Potongan JavaScript SSE (F8) lolos `node --check`
- ✅ Fungsi `parseSSE()` diuji terhadap event `delta`, `meta`, dan komentar
  keep-alive → hasil benar semua
- ✅ Jumlah pemanggil diverifikasi: `PromptBuilder::build` 1 pemanggil (aman
  diubah signature-nya), `AiClient::chat` 4 pemanggil (harus backward-compatible)
- ⚠️ Kode PHP belum bisa di-`lint` (PHP tidak tersedia di environment ini) —
  wajib `php -l` tiap file saat implementasi

---



## 2. Arsitektur Baru

```
                    SEBELUM                              SESUDAH
  ┌──────────────────────────────────┐   ┌────────────────────────────────────────┐
  │ Chat::send()                     │   │ Chat::send() / Chat::stream()          │
  │   saveMessage(user)  ← duluan    │   │   history = getSessionMessages(limit)  │
  │   buildContext()                 │   │   saveMessage(user)                    │
  │     getSessionMessages() ∞       │   │   SkillRouter (lazy, hanya jika perlu) │
  │     PromptBuilder → [sys,user]   │   │   system = PromptBuilder::system()     │
  │     + seluruh riwayat            │   │   messages = ContextBuilder::build(    │
  │   AiClient::chat(stream=false)   │   │       system, history, current)        │
  │   → JSON sekali di akhir         │   │   StreamClient::sse(...)               │
  └──────────────────────────────────┘   │   → chunk demi chunk                   │
                                          └────────────────────────────────────────┘
```

Prinsip: **satu sumber kebenaran** untuk merakit pesan (`ContextBuilder`), dan
**pemisahan** antara "isi system prompt" (`PromptBuilder`) dengan "urutan &
anggaran konteks" (`ContextBuilder`).

Urutan payload final yang dijamin benar:
```
[0]        system    : identitas + ATURAN GROUNDING + data skill + web + waktu
[1..n-1]   riwayat   : user/assistant berselang-seling, di-trim sesuai budget
[n]        user      : pesan saat ini  ← TEPAT SATU KALI, DI POSISI TERAKHIR
```

---

## 3. Rencana Perubahan Per File

### F1 · `app/Libraries/ContextBuilder.php` — **FILE BARU** ⭐

Otak baru: merakit pesan dengan urutan benar + anggaran token.

```php
<?php

namespace App\Libraries;

/**
 * Merakit pesan AI dengan urutan & anggaran token yang benar.
 *
 * Menjamin:
 *  - [0]     = system (identitas + grounding + data)
 *  - [1..n-1]= riwayat, berselang-seling, dipangkas dari yang TERLAMA
 *  - [n]     = pesan user saat ini, TEPAT SATU KALI
 */
class ContextBuilder
{
    /** Perbandingan karakter→token. Bahasa Indonesia ~3.6 char/token (konservatif). */
    private const CHARS_PER_TOKEN = 3.6;

    public function __construct(
        private int $maxPromptTokens = 6000,
        private int $reservedOutput = 1024,
    ) {}

    public static function estimateTokens(string $text): int
    {
        return max(1, (int) ceil(mb_strlen($text) / self::CHARS_PER_TOKEN));
    }

    /**
     * @param string $systemPrompt  system prompt lengkap (dari PromptBuilder)
     * @param array  $history       riwayat DB (TANPA pesan saat ini)
     * @param string $currentMessage pesan user yang sedang dijawab
     * @return array<int, array{role:string, content:string}>
     */
    public function build(string $systemPrompt, array $history, string $currentMessage): array
    {
        $budget = $this->maxPromptTokens;

        // --- 1. Pesan saat ini: prioritas mutlak, selalu utuh ---
        $budget -= self::estimateTokens($currentMessage);

        // --- 2. System prompt: prioritas kedua (berisi data grounding) ---
        $sysTokens = self::estimateTokens($systemPrompt);
        $truncatedData = false;
        if ($sysTokens > $budget) {
            $systemPrompt  = $this->truncateSystem($systemPrompt, max(800, $budget));
            $sysTokens     = self::estimateTokens($systemPrompt);
            $truncatedData = true;
        }
        $budget -= $sysTokens;

        // --- 3. Riwayat: isi sisa budget, dari TERBARU ke terlama ---
        $clean = $this->sanitizeHistory($history, $currentMessage);
        $kept  = [];
        $used  = 0;

        foreach (array_reverse($clean) as $m) {
            $t = self::estimateTokens($m['content']);
            if ($used + $t > $budget) {
                break;                       // berhenti utuh, jangan potong setengah pesan
            }
            $used += $t;
            array_unshift($kept, $m);
        }

        // Selang-seling rapi: riwayat harus diawali turn 'user'
        while ($kept !== [] && $kept[0]['role'] !== 'user') {
            array_shift($kept);
        }

        // --- 4. Beri tahu model bila ada yang hilang (anti-halusinasi) ---
        $dropped = count($clean) - count($kept);

        if ($dropped > 0 || $truncatedData) {
            $note = "\n\n[CATATAN KONTEKS]\n"
                . ($dropped > 0 ? "- {$dropped}+ pesan lama TIDAK disertakan (batas konteks).\n" : '')
                . ($truncatedData ? "- Sebagian data referensi dipotong.\n" : '')
                . "- Bila user merujuk hal yang tidak ada di riwayat maupun data di atas, "
                . "TANYA KEMBALI. JANGAN mengarang atau menebak.";

            // CLAMP: teks catatan juga memakan token. Buang riwayat tertua
            // sampai system + note + riwayat + current benar-benar muat.
            $fixed = self::estimateTokens($systemPrompt)
                   + self::estimateTokens($note)
                   + self::estimateTokens($currentMessage);

            $histTokens = array_sum(array_map(
                static fn ($m) => self::estimateTokens($m['content']), $kept));

            while ($kept !== [] && $fixed + $histTokens > $this->maxPromptTokens) {
                $histTokens -= self::estimateTokens(array_shift($kept)['content']);
                $dropped++;
            }

            // PENTING: rapikan ulang SETELAH clamp. Memotong dari depan bisa
            // membuat riwayat diawali turn 'assistant' lagi.
            while ($kept !== [] && $kept[0]['role'] !== 'user') {
                array_shift($kept);
            }

            $systemPrompt .= $note;
        }

        return array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $kept,
            [['role' => 'user', 'content' => $currentMessage]]
        );
    }

    /**
     * Bersihkan riwayat: buang baris system/meta, dan buang duplikat pesan saat ini
     * (pengaman bila pemanggil terlanjur menyimpan pesan sebelum merakit konteks).
     */
    private function sanitizeHistory(array $history, string $currentMessage): array
    {
        $out     = [];
        $lastIdx = count($history) - 1;

        foreach ($history as $i => $m) {
            $role = (string) ($m['role'] ?? '');
            if (! in_array($role, ['user', 'assistant'], true)) {
                continue;                                  // buang 'system', 'Session started'
            }
            $content = trim((string) ($m['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            if ($i === $lastIdx && $role === 'user' && $content === trim($currentMessage)) {
                continue;                                  // ← PERBAIKAN B1 (dedupe)
            }
            $out[] = ['role' => $role, 'content' => $content];
        }

        return $out;
    }

    /**
     * Potong system prompt saat kebesaran.
     * Bagian ATURAN selalu dipertahankan; sisa budget dipakai memuat data
     * sebanyak mungkin (bukan dibuang seluruhnya).
     */
    private function truncateSystem(string $system, int $budget): string
    {
        $markers = ["\n[DATA AKADEMIK]", "\n[HASIL WEB SEARCH]", "\n[ISI FILE]", "\n[MODUL PANDUAN]"];
        $cutAt   = mb_strlen($system);

        foreach ($markers as $mk) {
            $pos = mb_strpos($system, $mk);
            if ($pos !== false && $pos < $cutAt) {
                $cutAt = $pos;
            }
        }

        $noteReserve = 40;                                   // ruang utk teks catatan
        $allowChars  = (int) (max(0, $budget - $noteReserve) * self::CHARS_PER_TOKEN);
        $rules       = mb_substr($system, 0, $cutAt);        // instruksi: prioritas mutlak

        if (mb_strlen($rules) >= $allowChars) {
            return mb_substr($rules, 0, $allowChars)
                 . "\n[CATATAN] Konteks dipotong karena melebihi batas.";
        }

        // Sisa ruang dipakai memuat sebagian data — jauh lebih baik daripada nol.
        $room = $allowChars - mb_strlen($rules);

        return $rules . mb_substr($system, $cutAt, $room)
             . "\n[CATATAN] Data referensi terlalu besar, sebagian dihilangkan.";
    }
}
```

> ⚠️ **Konsistensi satuan — jebakan yang sudah terbukti:** `estimateTokens()`
> membagi dengan `CHARS_PER_TOKEN` (3.6), sedangkan `truncateSystem()` mengalikan
> dengannya. Kalau salah satunya memakai `int` / pembulatan berbeda, budget bisa
> **jebol ~20%** tanpa terlihat. Bug ini muncul di simulasi dan sudah diperbaiki.
> Gunakan satu konstanta, satu arah, tanpa pembulatan ganda.

**Kenapa `maxPromptTokens = 6000` default?** Cukup untuk ~12–20 turn riwayat +
data akademik, tapi tetap hemat biaya & cepat. Bisa diubah lewat settings (F10).

---

### F2 · `app/Libraries/PromptBuilder.php` — **REWRITE** ⭐

Perbaiki B6. **Perubahan signature**: sekarang hanya mengembalikan *system
string*, tidak lagi menyisipkan pesan user (itu tugas `ContextBuilder`).

> ✅ Aman: hanya 1 pemanggil (`Chat.php:271`).

```php
<?php

namespace App\Libraries;

use App\Libraries\Skills\SkillResult;

class PromptBuilder
{
    /**
     * Bangun SYSTEM PROMPT saja. Pesan user & riwayat dirakit oleh ContextBuilder.
     */
    public static function build(string $skillName, SkillResult $result): string
    {
        $data = $result->data;
        $s    = '';

        // ---- 1. Identitas ----
        $s .= "Kamu adalah AI Assistant akademik. Mode skill aktif: {$skillName}.\n";
        $s .= "Jawab dalam bahasa yang sama dengan yang dipakai user.\n\n";

        // ---- 2. ATURAN GROUNDING (inti anti-halusinasi) ----
        $s .= self::groundingRules($data !== []);

        // ---- 3. Data referensi ----
        if (! empty($data['guide'])) {
            $s .= "\n[PANDUAN SKILL]\n" . $data['guide'] . "\n";
        }
        if (! empty($data['modules'])) {
            $s .= "\n[MODUL PANDUAN]\n";
            foreach ($data['modules'] as $m) {
                $s .= '- ' . ($m['module_name'] ?? '') . ': ' . ($m['description'] ?? '') . "\n";
            }
        }
        if (! empty($data['db_data'])) {
            $s .= "\n[DATA AKADEMIK]\n" . $data['db_data'] . "\n";
        }
        if (! empty($data['web_results'])) {
            $s .= "\n[HASIL WEB SEARCH]\n" . $data['web_results'] . "\n";
        }
        if (! empty($data['file_content'])) {
            $s .= "\n[ISI FILE]\n" . $data['file_content'] . "\n";
        }

        // ---- 4. Kegagalan skill: larang mengarang ----
        if (! $result->success && $result->error) {
            $s .= "\n[STATUS PENCARIAN DATA]\nGAGAL mengambil data. Alasan: " . $result->error . "\n";
            $s .= "WAJIB: sampaikan bahwa data tidak dapat diambil. "
                . "JANGAN menjawab dari ingatan atau mengarang angka/tanggal/nama apa pun.\n";
            if ($result->suggestion) {
                $s .= 'Saran untuk user: ' . $result->suggestion . "\n";
            }
        }

        // ---- 5. Konteks waktu ----
        $s .= "\n" . AiClient::timeContext() . "\n";

        return $s;
    }

    /** System prompt untuk obrolan umum tanpa data (dipakai Api/Chat, Api/Discover). */
    public static function general(string $role = 'asisten kampus yang ramah'): string
    {
        return "Kamu adalah {$role}.\n"
            . "Jawab dalam bahasa Indonesia yang santai dan tidak kaku.\n"
            . "Jangan membuka, menebak, atau mengarang data akademik internal.\n"
            . "Bila tidak tahu, katakan tidak tahu.\n\n"
            . AiClient::timeContext();
    }

    private static function groundingRules(bool $hasData): string
    {
        $r  = "[ATURAN WAJIB — PATUHI TANPA PENGECUALIAN]\n";
        $r .= "1. Jawab HANYA berdasarkan bagian [DATA AKADEMIK], [PANDUAN SKILL], "
            . "[MODUL PANDUAN], [HASIL WEB SEARCH], [ISI FILE], dan riwayat percakapan di bawah.\n";
        $r .= "2. Jika jawaban TIDAK ADA di bagian-bagian itu, balas persis: "
            . "\"Data tersebut tidak tersedia di sistem.\" Lalu tawarkan bantuan lain. "
            . "DILARANG menebak, memperkirakan, atau mengisi dari pengetahuan umum.\n";
        $r .= "3. DILARANG KERAS mengarang: NIM/NIDN, nama orang, nama mata kuliah, "
            . "tanggal, jadwal, nilai, SKS, kode, nominal uang, atau nomor apa pun.\n";
        $r .= "4. Saat menyebut angka/tanggal/nama dari data, tunjukkan asalnya, "
            . "mis. \"(sumber: data KRS)\".\n";
        $r .= "5. Bila data tidak lengkap, ambigu, atau saling bertentangan, "
            . "katakan apa adanya dan sebutkan bagian mana yang meragukan.\n";
        $r .= "6. Bedakan dengan tegas antara FAKTA dari data dan PENJELASAN umummu. "
            . "Jangan menyajikan penjelasan umum seolah-olah itu data resmi.\n";

        if (! $hasData) {
            $r .= "7. Saat ini TIDAK ADA data yang berhasil diambil. "
                . "Jadi jawabanmu harus berupa penjelasan umum atau permintaan klarifikasi — "
                . "BUKAN data spesifik.\n";
        }

        return $r . "\n";
    }
}
```

---

### F3 · `app/Libraries/WebSearch.php` — **PERBAIKI B4**

Ganti `withWebContext()` supaya menyuntik ke **system**, bukan menambah turn user.

```php
// SEBELUM (line 66-81) — merusak struktur pesan
public static function withWebContext(array $messages, string $question): array
{
    ...
    $messages[] = ['role' => 'user', 'content' => $block . "\n\nJawab pertanyaan terakhir; ..."];
    ...
}

// SESUDAH — suntik ke system, struktur tetap [system, ..., user]
/**
 * Suntikkan hasil web ke pesan SYSTEM (bukan sebagai turn user baru).
 * Struktur & urutan pesan TIDAK diubah; pesan user tetap terakhir.
 */
public static function withWebContext(array $messages, string $question): array
{
    try {
        if (! self::needsWeb($question)) {
            return $messages;
        }
        $block = self::contextBlock(self::search($question));
        if ($block === '') {
            return $messages;
        }

        $inject = "\n[HASIL WEB SEARCH]\n" . $block . "\n"
                . "Gunakan sumber di atas hanya bila relevan, dan sebutkan bahwa "
                . "jawaban memakai sumber web. Utamakan data internal bila keduanya ada.\n";

        foreach ($messages as $i => $m) {
            if (($m['role'] ?? '') === 'system') {
                $messages[$i]['content'] .= $inject;
                return $messages;
            }
        }
        // tidak ada system → sisipkan di depan (bukan di belakang)
        array_unshift($messages, ['role' => 'system', 'content' => trim($inject)]);
    } catch (\Throwable) {
    }

    return $messages;
}
```

Otomatis memperbaiki 3 pemanggil: `Api/Chat.php:29`, `Api/Discover.php:263`,
`AssistantModule.php:141`.

Lalu **hapus** blok inline duplikat di `Chat.php:140-148` (pakai fungsi yang sama).

---

### F4 · `app/Libraries/StreamClient.php` — **FILE BARU** ⭐

Perbaiki B5. **Guzzle tidak tersedia**, dan `CURLRequest` CI4 mem-buffer penuh,
jadi pakai raw cURL + `CURLOPT_WRITEFUNCTION` agar chunk langsung diteruskan.

```php
<?php

namespace App\Libraries;

use RuntimeException;
use Throwable;

/**
 * Streaming SSE untuk provider OpenAI-compatible & Ollama, memakai raw cURL.
 *
 * Alasan tidak pakai CURLRequest CI4: ia memanggil curl_exec() yang mem-buffer
 * seluruh body — mustahil meneruskan chunk secara real-time.
 */
class StreamClient
{
    /**
     * @param callable(string $delta): void $onDelta  dipanggil tiap potongan teks
     * @param callable(array $meta): void   $onDone   dipanggil sekali di akhir
     */
    public static function stream(
        string $provider,
        string $baseUrl,
        string $model,
        array $messages,
        array $opt,
        callable $onDelta,
        callable $onDone
    ): void {
        if ($provider === 'opencode') {
            // opencode punya model sesi sendiri; streaming-nya beda skema.
            // Sementara fallback ke non-stream agar tidak rusak.
            $r = AiClient::chat($provider, $baseUrl, $model, $messages, $opt);
            $onDelta($r['content']);
            $onDone(['tokens' => $r['tokens'], 'session_id' => $r['session_id'], 'model' => $model]);
            return;
        }

        [$url, $payload, $headers] = self::prepare($provider, $baseUrl, $model, $messages, $opt);

        $buffer = '';
        $full   = '';
        $usage  = ['tokens' => 0];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT        => (int) ($opt['timeout'] ?? 300),
            CURLOPT_CONNECTTIMEOUT => 15,
            // Kunci streaming: proses body SEBELUM koneksi ditutup
            CURLOPT_WRITEFUNCTION  => function ($ch, string $chunk) use (&$buffer, &$full, &$usage, $onDelta, $provider) {
                $buffer .= $chunk;

                while (($nl = strpos($buffer, "\n")) !== false) {
                    $line   = trim(substr($buffer, 0, $nl));
                    $buffer = substr($buffer, $nl + 1);

                    if ($line === '' || ! str_starts_with($line, 'data:')) {
                        continue;
                    }
                    $data = trim(substr($line, 5));
                    if ($data === '[DONE]') {
                        continue;
                    }

                    $json = json_decode($data, true);
                    if (! is_array($json)) {
                        continue;
                    }

                    if (isset($json['error'])) {
                        throw new RuntimeException(self::errMsg($json));
                    }

                    // OpenAI: choices[0].delta.content | Ollama: message.content
                    $delta = $provider === 'openai'
                        ? ($json['choices'][0]['delta']['content'] ?? '')
                        : ($json['message']['content'] ?? '');

                    if ($delta !== '') {
                        $full .= $delta;
                        $onDelta($delta);
                    }

                    if (isset($json['usage']['total_tokens'])) {
                        $usage['tokens'] = (int) $json['usage']['total_tokens'];
                    }
                    if (isset($json['eval_count'])) {
                        $usage['tokens'] = (int) $json['eval_count'] + (int) ($json['prompt_eval_count'] ?? 0);
                    }
                }

                return strlen($chunk);   // WAJIB: kembalikan jumlah byte, kalau tidak cURL membatalkan transfer
            },
        ]);

        try {
            $ok   = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err  = curl_error($ch);
        } finally {
            curl_close($ch);
        }

        if ($ok === false) {
            throw new RuntimeException("Streaming gagal: {$err}");
        }
        if ($code >= 400) {
            throw new RuntimeException("Provider HTTP {$code} saat streaming.");
        }
        if ($full === '') {
            throw new RuntimeException('Provider tidak mengembalikan teks apa pun.');
        }

        $usage['content'] = $full;
        $onDone($usage);
    }

    private static function prepare(string $provider, string $baseUrl, string $model, array $messages, array $opt): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $temp    = (float) ($opt['temperature'] ?? 0.2);
        $maxTok  = (int) ($opt['max_tokens'] ?? 1024);

        if ($provider === 'openai') {
            $url     = AiClient::openAiBasePublic($baseUrl) . '/chat/completions';
            $payload = [
                'model'             => $model,
                'messages'          => $messages,
                'stream'            => true,
                'stream_options'    => ['include_usage' => true],
                'temperature'       => $temp,
                'top_p'             => (float) ($opt['top_p'] ?? 0.9),
                'max_tokens'        => $maxTok,
                'frequency_penalty' => 0.1,
            ];
            $headers = ['Content-Type: application/json', 'Accept: text/event-stream'];
            if (! empty($opt['apiKey'])) {
                $headers[] = 'Authorization: Bearer ' . $opt['apiKey'];
            }
            return [$url, $payload, $headers];
        }

        // ollama
        return [
            $baseUrl . '/api/chat',
            [
                'model'     => $model,
                'messages'  => $messages,
                'stream'    => true,
                'keep_alive'=> (string) ($opt['keep_alive'] ?? '30m'),
                'options'   => [
                    'temperature'    => $temp,
                    'top_p'          => (float) ($opt['top_p'] ?? 0.9),
                    'num_ctx'        => (int) ($opt['num_ctx'] ?? 8192),
                    'num_predict'    => $maxTok,
                    'repeat_penalty' => 1.1,
                ],
            ],
            ['Content-Type: application/json', 'Accept: application/x-ndjson'],
        ];
    }

    private static function errMsg(array $json): string
    {
        $m = $json['error']['message'] ?? $json['error'] ?? $json['message'] ?? 'unknown';
        return is_array($m) ? json_encode($m) : (string) $m;
    }
}
```

> ⚠️ **Titik rawan saat implementasi:** callback `CURLOPT_WRITEFUNCTION`
> **wajib** `return strlen($chunk)`. Jika mengembalikan nilai lain (atau lupa),
> cURL membatalkan transfer secara diam-diam — streaming berhenti tanpa error.
> Ini penyebab bug SSE paling umum di PHP.

Perlu ditambah di `AiClient`: expose `openAiBase()` sebagai
`openAiBasePublic()` (public static) agar bisa dipakai `StreamClient`.

---

### F5 · `app/Libraries/AiClient.php` — **TUNING (B3)**

Tetap **backward-compatible**: 4 pemanggil lama tidak berubah perilakunya
kecuali jadi lebih baik.

```php
// 1) currentConfig() — tambahkan parameter tuning dari settings
return ['openai',
    rtrim($s->getGlobal('openai_base', 'https://api.openai.com') ?: 'https://api.openai.com', '/'),
    $s->getGlobal('openai_model', 'gpt-4o-mini') ?: 'gpt-4o-mini',
    [
        'apiKey'      => (string) $s->getSecret('openai_key', ''),
        'timeout'     => 300,
        // ↓ BARU
        'temperature' => (float) $s->getGlobal('ai_temperature', '0.2'),
        'top_p'       => (float) $s->getGlobal('ai_top_p', '0.9'),
        'max_tokens'  => (int)   $s->getGlobal('ai_max_tokens', '1024'),
    ],
];
```

```php
// 2) chatOpenAi() — KIRIM parameter sampling (perbaikan B3)
private static function chatOpenAi(string $baseUrl, string $model, array $messages, string $apiKey, int $timeout, array $opt = []): array
{
    $base = self::openAiBase($baseUrl);

    $body = [
        'model'    => $model,
        'messages' => $messages,
        // ↓ BARU — tanpa ini provider pakai default temperature 1.0
        'temperature'       => (float) ($opt['temperature'] ?? 0.2),
        'top_p'             => (float) ($opt['top_p'] ?? 0.9),
        'max_tokens'        => (int)   ($opt['max_tokens'] ?? 1024),
        'frequency_penalty' => 0.1,
    ];

    $data = self::http('POST', $base . '/chat/completions', $body, self::bearer($apiKey), $timeout);
    ...
}
```

```php
// 3) chatOllama() — perbaiki num_ctx + tambah keep_alive (line 156-186)
'options' => [
    'temperature'    => (float) ($opt['temperature'] ?? 0.2),   // was 0.7
    'top_p'          => 0.9,
    'num_ctx'        => (int) ($opt['num_ctx'] ?? 8192),        // was 4096
    'num_predict'    => (int) ($opt['max_tokens'] ?? 1024),     // BARU
    'repeat_penalty' => 1.1,
],
'keep_alive' => (string) ($opt['keep_alive'] ?? '30m'),          // BARU — hindari reload model
```

```php
// 4) timeContext() — buang side-effect global (line 68-74)
public static function timeContext(): string
{
    // SEBELUM: date_default_timezone_set('Asia/Jakarta');  ← side effect tiap request
    $tz   = new \DateTimeZone(config('App')->appTimezone ?: 'Asia/Jakarta');
    $now  = new \DateTimeImmutable('now', $tz);

    return 'Konteks waktu: hari ini ' . $now->format('l, d F Y H:i') . ' WIB. '
         . 'Jangan pernah mengaku pengetahuanmu mutakhir; bila ragu soal peristiwa terkini, katakan terus terang.';
}
```

---

### F6 · `app/Controllers/Chat.php` — **PERBAIKI B1 + endpoint stream**

```php
// --- PERBAIKAN B1: ambil riwayat SEBELUM menyimpan pesan baru ---
// (pindahkan pengambilan riwayat ke atas, sebelum line 128)
$history = $this->chatModel->getSessionMessages($sessionId, $userId, 40);  // ← F9: ada limit

$this->chatModel->saveMessage([
    'user_id'         => $userId,
    'session_id'      => $sessionId,
    'role'            => 'user',
    'content'         => $message,
    'attachment_name' => $attach['name'],
    'attachment_path' => $attach['path'],
]);

$context = $this->buildContext($history, $sessionId, $userId, $message);   // ← signature baru
```

```php
// --- buildContext versi baru ---
protected function buildContext(array $history, string $sessionId, int $userId, string $currentMessage = ''): array
{
    $contextModel = new ChatContextModel();
    $context      = $contextModel->getContext($sessionId, $userId);
    // ... (logika identifier/user_type/role TETAP seperti sekarang, line 233-243)

    $skillName   = 'general';
    $skillResult = null;

    if ($currentMessage !== '') {
        $router      = new SkillRouter();
        $route       = $router->route($currentMessage, $context);
        $skillName   = $route['skill'];
        $context['last_intent'] = $route['intent'];

        $skillResult = $router->execute($skillName, [
            'intent'     => $route['intent'],
            'identifier' => $identifier,
            'user_type'  => $userType,
        ]);

        if ($skillResult->success && ! empty($skillResult->data['npm'])) {
            $context['identifier'] = $skillResult->data['npm'];
        }
        $contextModel->saveContext($sessionId, $userId, $context);
    }

    // ---- PromptBuilder sekarang hanya menghasilkan SYSTEM string ----
    $system = $skillResult !== null
        ? PromptBuilder::build($skillName, $skillResult)
        : PromptBuilder::general('AI Coding & Academic Assistant');

    // ---- Web search disuntik ke SYSTEM, bukan sebagai turn user (B4) ----
    if (\App\Libraries\WebSearch::needsWeb($currentMessage)) {
        $tmp    = [['role' => 'system', 'content' => $system]];
        $tmp    = \App\Libraries\WebSearch::withWebContext($tmp, $currentMessage);
        $system = $tmp[0]['content'];
    }

    // ---- ContextBuilder menjamin urutan & budget ----
    return (new \App\Libraries\ContextBuilder(
        (int) $this->settings->getGlobal('ai_max_context_tokens', '6000'),
        (int) $this->settings->getGlobal('ai_max_tokens', '1024'),
    ))->build($system, $history, $currentMessage);
}
```

**Endpoint streaming baru:**
```php
/** SSE — jawaban dikirim bertahap. Dipanggil via fetch()+ReadableStream (bukan EventSource). */
public function stream()
{
    // ... validasi user_id, session_id, message, CSRF — SAMA PERSIS dengan send() ...

    $history = $this->chatModel->getSessionMessages($sessionId, $userId, 40);
    $context = $this->buildContext($history, $sessionId, $userId, $message);

    // Header SSE
    while (ob_get_level() > 0) { ob_end_flush(); }
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-transform');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');      // proxy (Traefik/nginx di Coolify)

    // PENTING: lepas session lock agar request lain dari user ini tidak terblokir
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }

    $sse = static function (string $event, array $data): void {
        echo "event: {$event}\ndata: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
    };

    $sse('meta', ['session_id' => $sessionId, 'model' => $model, 'csrf' => csrf_hash()]);

    try {
        $tokens = 0;
        \App\Libraries\StreamClient::stream(
            $this->provider, $this->aiUrl, $model, $context,
            ['apiKey' => $this->apiKey, 'username' => $this->ocUser, 'password' => $this->ocPass,
             'temperature' => 0.2, 'max_tokens' => 1024],
            function (string $delta) use ($sse) { $sse('delta', ['t' => $delta]); },
            function (array $meta) use (&$tokens) { $tokens = (int) ($meta['tokens'] ?? 0); }
        );

        $this->chatModel->saveMessage([
            'user_id' => $userId, 'session_id' => $sessionId,
            'role' => 'assistant', 'content' => $meta['content'] ?? '', 'tokens_used' => $tokens,
        ]);
        $sse('done', ['tokens' => $tokens]);
    } catch (\Throwable $e) {
        $sse('error', ['message' => $e->getMessage()]);
    }
    exit;
}
```

**Perbaikan kecil lain:**
- `Chat.php:87` — cek kepemilikan sesi jangan ambil seluruh riwayat:
  ```php
  $owned = $this->chatModel->sessionExists($sessionId, $userId);  // method baru, SELECT id LIMIT 1
  ```
- `Chat.php:140-148` — hapus, sudah ditangani di `buildContext`.

---

### F7 · `app/Config/Routes.php`

```php
$routes->group('', ['filter' => 'auth'], static function ($routes) {
    ...
    $routes->post('chat/send', 'Chat::send');
    $routes->post('chat/stream', 'Chat::stream');   // ← BARU (tetap POST: butuh CSRF + upload)
    ...
});
```

> `EventSource` bawaan browser **hanya mendukung GET** → tidak bisa kirim CSRF
> token & file. Karena itu frontend memakai `fetch()` + `ReadableStream`.

---

### F8 · `app/Views/chat/index.php` — **frontend streaming**

Ganti blok `fetch(...).then(res => res.json())` (line 309–340):

```javascript
async function sendStreaming(formData, message) {
    const bubble = appendStreamingBubble();      // bubble kosong + kursor berkedip
    let acc = '';
    let lastRender = 0;

    const res = await fetch(BASE_URL + 'chat/stream', { method: 'POST', body: formData });
    if (!res.ok || !res.body) throw new Error('HTTP ' + res.status);

    const reader  = res.body.getReader();
    const decoder = new TextDecoder();
    let buf = '';

    while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        buf += decoder.decode(value, { stream: true });

        // Parse SSE manual: event dipisah baris kosong
        let idx;
        while ((idx = buf.indexOf('\n\n')) !== -1) {
            const raw   = buf.slice(0, idx); buf = buf.slice(idx + 2);
            const ev    = parseSSE(raw);
            if (!ev) continue;

            if (ev.event === 'meta') {
                if (ev.data.session_id) setSessionId(ev.data.session_id, message);
                if (ev.data.csrf) updateCsrfToken(ev.data.csrf);
            } else if (ev.event === 'delta') {
                acc += ev.data.t;
                // Re-render penuh (bukan append) — markdown parsial bisa rusak.
                // Debounce ~60ms agar tidak membebani CPU.
                const now = performance.now();
                if (now - lastRender > 60) { lastRender = now; renderInto(bubble, acc); }
            } else if (ev.event === 'done') {
                renderInto(bubble, acc);
                showTokens(bubble, ev.data.tokens);
            } else if (ev.event === 'error') {
                renderInto(bubble, '⚠️ ' + ev.data.message);
            }
        }
    }
    finalize(bubble);   // highlight syntax sekali di akhir
}

function parseSSE(raw) {
    let event = 'message', data = '';
    for (const line of raw.split('\n')) {
        if (line.startsWith('event:')) event = line.slice(6).trim();
        else if (line.startsWith('data:')) data += line.slice(5).trim();
    }
    if (!data) return null;
    try { return { event, data: JSON.parse(data) }; } catch { return null; }
}

function renderInto(bubble, text) {
    // pakai marked + DOMPurify yang SUDAH ADA di proyek (line 350-351)
    bubble.innerHTML = DOMPurify.sanitize(marked.parse(text));
    bubble.scrollIntoView({ block: 'end' });
}
```

**Catatan implementasi penting:**
1. **Re-render penuh, jangan append HTML.** `marked.parse()` atas markdown
   separuh jadi (mis. ` ```php ` belum ditutup) menghasilkan HTML rusak.
2. **Debounce ~60 ms** — tanpa ini, ratusan render/detik membuat UI lag.
3. **`hljs.highlightElement` hanya di `finalize()`** — mahal, jangan tiap chunk.
4. **Fallback**: simpan `sendStreaming()` dan `sendLegacy()` (kode lama). Kalau
   `res.body` null atau browser tua → pakai legacy. Aman untuk rollout.
5. `showTyping()`/`removeTyping()` diganti bubble streaming.

---

### F9 · `app/Models/ChatHistoryModel.php`

```php
// Perbaikan B2: tambah limit + method cek keberadaan yang murah
public function getSessionMessages(string $sessionId, int $userId, int $limit = 0): array
{
    $q = $this->where('session_id', $sessionId)
              ->where('user_id', $userId)
              ->orderBy('id', 'ASC');

    if ($limit > 0) {
        // Ambil N TERBARU, lalu balikkan ke urutan kronologis.
        $rows = $this->where('session_id', $sessionId)
                     ->where('user_id', $userId)
                     ->orderBy('id', 'DESC')
                     ->limit($limit)
                     ->findAll();
        return array_reverse($rows);
    }

    return $q->findAll();
}

/** Cek kepemilikan sesi tanpa menarik seluruh riwayat (dipakai Chat.php:87). */
public function sessionExists(string $sessionId, int $userId): bool
{
    return $this->select('id')
                ->where('session_id', $sessionId)
                ->where('user_id', $userId)
                ->limit(1)
                ->first() !== null;
}
```

> `Chat.php:58` (tampilan riwayat di UI) tetap panggil tanpa limit — user memang
> harus melihat seluruh percakapan. Limit hanya untuk konteks AI.

---

### F10 · `app/Models/SettingModel.php` — cache konfigurasi

```php
// SEBELUM: 1 query DB per pemanggilan
public function getGlobal(string $key, ?string $default = null): ?string
{
    $row = $this->where('user_id', null)->where('key', $key)->first();
    return $row['value'] ?? $default;
}

// SESUDAH: seluruh settings global diambil SEKALI per request
private static ?array $globalCache = null;

public function getGlobal(string $key, ?string $default = null): ?string
{
    if (self::$globalCache === null) {
        $rows = $this->where('user_id', null)->findAll();
        self::$globalCache = array_column($rows, 'value', 'key');
    }
    return self::$globalCache[$key] ?? $default;
}

/** Wajib dipanggil setelah setGlobal() agar cache tidak basi. */
public static function flushCache(): void { self::$globalCache = null; }
```
Tambahkan `self::flushCache()` di akhir `setGlobal()` dan `Settings::update()`.

---

### F11 · Settings baru (tabel `settings`, `user_id = NULL`)

| Key | Default | Fungsi |
|---|---|---|
| `ai_temperature` | `0.2` | Anti-halusinasi. 0.0–0.3 faktual, 0.7+ kreatif |
| `ai_top_p` | `0.9` | Nucleus sampling |
| `ai_max_tokens` | `1024` | Plafon panjang jawaban → lebih cepat & hemat |
| `ai_max_context_tokens` | `6000` | Anggaran prompt (riwayat + data) |
| `ai_history_limit` | `40` | Jumlah pesan riwayat maksimum dari DB |
| `ai_stream_enabled` | `1` | Saklar streaming (fallback mudah) |
| `ai_num_ctx` | `8192` | Khusus Ollama |
| `ai_keep_alive` | `30m` | Khusus Ollama, hindari reload model |

Tambahkan field-nya di `app/Views/settings/index.php` + simpan di
`Settings::update()`. Beri preset di UI:

| Preset | temperature | max_tokens | Untuk |
|---|---|---|---|
| **Faktual / Akademik** | 0.1 | 768 | Data mahasiswa, KRS, jadwal — **default** |
| Seimbang | 0.4 | 1024 | Campuran |
| Kreatif / Menulis | 0.8 | 2048 | `WritingSkill`, `WritingArtikelSkill` |
| Coding | 0.2 | 2048 | `CodingSkill` |

> 💡 Bonus: `SkillRouter` sudah tahu skill apa yang aktif → temperature bisa
> dipilih **otomatis per skill**, bukan satu angka global. Skill menulis dapat
> 0.8, skill data akademik dapat 0.1.

---

### F12 · Apache / Coolify — agar SSE tidak di-buffer

Proyek pakai `php:8.3-apache-bookworm` (bukan nginx). Tambahkan ke
`public/.htaccess`:

```apache
# Matikan kompresi untuk endpoint streaming (gzip mem-buffer sampai selesai)
<IfModule mod_env.c>
    SetEnvIf Request_URI "^/chat/stream" no-gzip=1 dont-vary=1
</IfModule>
<IfModule mod_deflate.c>
    SetOutputFilter DEFLATE
    BrowserMatch ^Mozilla/4 gzip-only-text/html
</IfModule>
```

Dan pastikan di `env` / `.env`:
```ini
; output_buffering harus OFF agar flush() langsung keluar
```
(tambahkan `php_flag output_buffering Off` di `.htaccess`, atau set
`output_buffering=0` lewat `Dockerfile` conf.d).

**Coolify** biasanya menaruh **Traefik** di depan. Traefik meneruskan SSE dengan
baik asal tidak ada gzip; header `X-Accel-Buffering: no` sudah dikirim dari PHP
sebagai pengaman. **Wajib diuji langsung di server Coolify**, bukan hanya lokal.

---

## 4. Urutan Implementasi (bertahap, tiap fase bisa dites sendiri)

| Fase | Isi | File | Risiko | Verifikasi |
|---|---|---|---|---|
| **1** | Perbaiki urutan & duplikasi pesan | F1, F2, F6 (buildContext), F9 | 🟢 Rendah | Tanya 3 hal berurutan yang saling merujuk |
| **2** | Tuning sampling (temperature dll.) | F5, F11 | 🟢 Rendah | Bandingkan jawaban sebelum/sesudah |
| **3** | Perbaiki injeksi web search | F3, F6 | 🟢 Rendah | Tanya hal "terbaru" |
| **4** | Cache settings | F10 | 🟢 Rendah | Cek jumlah query (debug bar) |
| **5** | **Streaming backend** | F4, F6 (stream), F7, F12 | 🟡 Sedang | `curl -N` ke endpoint |
| **6** | **Streaming frontend** | F8 | 🟡 Sedang | Uji di browser + fallback |

Fase 1–4 **sudah menyelesaikan "halu" dan "tidak paham konteks"** dan aman
di-deploy tanpa menyentuh UI. Fase 5–6 baru soal kecepatan yang terasa.

> Rekomendasi: deploy & uji fase 1–4 dulu. Kalau stabil, lanjut 5–6.

---

## 5. Rencana Verifikasi

### 5.1 Uji konteks (B1) — wajib lolos
```
Turn 1: "NPM saya 202110110, tolong cek"
Turn 2: "berapa SKS semester ini?"        ← harus pakai NPM dari turn 1
Turn 3: "mata kuliah apa yang nilainya terburuk?"  ← harus nyambung ke turn 2
Turn 4: "jelaskan maksud jawabanmu tadi"   ← harus merujuk turn 3
```
**Sebelum:** turn 2–4 kemungkinan besar gagal/mengarang. **Sesudah:** harus nyambung.

### 5.2 Uji anti-halusinasi (B3, B6) — wajib lolos
```
a) "Berapa IPK saya?"            (tanpa identifier) → harus MENOLAK, bukan mengarang angka
b) "Sebutkan 5 mahasiswa terbaik" (data tak ada)    → harus bilang data tidak tersedia
c) "Siapa dosen pengampu X?"     (data ada)         → jawab + sebut sumber
d) Ulangi pertanyaan (c) 3×                          → jawaban harus konsisten
```

### 5.3 Uji batas konteks (B2)
Isi satu sesi dengan 60+ pesan, lalu tanya hal dari pesan ke-5.
Harus: tidak error, tidak diam-diam kehilangan system prompt, dan model memberi
tahu bila memang sudah tidak terlihat.

### 5.4 Uji kecepatan (B5)
Ukur dengan DevTools → Network → **TTFB** dan "waktu sampai karakter pertama":
```bash
# non-streaming (baseline)
time curl -s -X POST https://<domain>/chat/send -d "..." -o /dev/null

# streaming — hitung waktu sampai event pertama
curl -N -X POST https://<domain>/chat/stream -d "..." | ts '%.s' | head -3
```
Target: teks pertama **< 1,5 detik**.

### 5.5 Regresi
- `php spark test` (ada `tests/unit/UserModelRememberTokenTest.php`)
- Endpoint API publik: `/api/ask`, `/api/chat`, `/api/mahasiswa/analyze`,
  `/api/dosen/analyze`, `/api/staff/analyze` — semua memakai `AiClient::chat`
  dan `WebSearch::withWebContext`, **harus tetap 200 OK**.
- Upload lampiran (PDF/txt) masih jalan.
- Provider `opencode` tidak rusak (fallback non-stream).

---

## 6. Risiko & Rollback

| Risiko | Mitigasi |
|---|---|
| Perubahan signature `PromptBuilder::build()` | Hanya 1 pemanggil — sudah diverifikasi. Update bersamaan. |
| SSE ter-buffer di proxy Coolify | Header `X-Accel-Buffering: no` + `.htaccess` no-gzip + uji langsung di server. Saklar `ai_stream_enabled=0` untuk fallback instan. |
| Session lock memblokir UI saat streaming | `session_write_close()` sebelum stream (sudah ada di F6). |
| Markdown parsial merusak tampilan | Re-render penuh + debounce 60 ms + sanitize DOMPurify (F8). |
| `temperature` terlalu rendah → jawaban kaku | Preset per skill + bisa diubah di admin tanpa deploy. |
| Estimasi token meleset (heuristic char/3.6) | Sengaja konservatif. Kalibrasi ulang dari `usage.total_tokens` nyata bila perlu. |
| Riwayat terpangkas → model "lupa" | `[CATATAN KONTEKS]` memberi tahu model secara eksplisit agar bertanya, bukan mengarang. |

**Rollback:** semua perubahan di commit terpisah per fase → `git revert <fase>`.
Streaming punya saklar settings, jadi bisa dimatikan tanpa deploy.

---

## 7. Yang Perlu Anda Konfirmasi

1. **Model OpenAI/OpenRouter mana** yang dipakai? Ini menentukan plafon
   `ai_max_context_tokens` yang aman (mis. `gpt-4o-mini` 128k, tapi model kecil
   OpenRouter ada yang cuma 8k). Saya set default 6000 — konservatif.
2. **Boleh saya tambah kolom di UI Settings** untuk 8 parameter baru (F11)?
   Atau cukup hardcode default + ubah manual lewat DB?
3. **`temperature` per skill** (bagian akhir F11) — mau saya sekalian, atau
   tunda ke fase berikutnya?
4. Folder `bri-snap` + laporan keamanannya masih di workspace. **Hapus?**

---

## 8. Catatan di Luar Scope (ditemukan, belum dikerjakan)

Bukan bagian permintaan ini, tapi layak dicatat:

- `DB_MAPPING.md` ketinggalan dari kode: tabel `skills`, `agents`, `providers`,
  `chat_sessions` ditandai "🔲 Belum" padahal modelnya sudah ada
  (`SkillModel`, `ApiClientModel`, `ApiModuleModel`, ...).
- Kredensial default `admin`/`admin123` terdokumentasi di README dan hash
  bcrypt-nya ada di `database.sql` — wajib diganti sebelum produksi.
- `AdminApi.php` (806 baris) dan `Api/Discover.php` (577 baris) layak dipecah.
- `SkillRouter::detectRoute()` memakai pencocokan kata kunci statis — bisa
  ditingkatkan ke klasifikasi intent berbasis embedding bila akurasi routing
  jadi masalah berikutnya.
