<?php

namespace App\Controllers\Api;

use App\Libraries\AiClient;
use App\Libraries\Academic\DynamicAssistant;
use App\Libraries\Academic\ModuleRegistry;
use App\Models\ApiDictionaryModel as DictModel;

/**
 * Satu pintu callback. Kontrak portal: {id|npm|nim|nik, role|peran, reason|pertanyaan}
 *  GET  /api/modules?role=mhs      → modul yang boleh diakses + contoh pertanyaan
 *  POST /api/ask {role,module?,id,reason} → rute otomatis ke modul
 */
class Discover extends BaseApi
{
    public function modules()
    {
        $client = $this->client();
        // PERBAIKAN H2: peran berasal dari kebijakan klien, bukan dari request.
        $peran  = $this->resolvedRole();

        $list = [];
        foreach (ModuleRegistry::accessible($client, $peran) as $slug => $info) {
            $list[] = [
                'slug'        => $slug,
                'nama'        => $info['name'],
                'deskripsi'   => $info['description'] ?? '',
                'id_param'    => $info['id_param'],
                'id_label'    => $info['id_label'],
                'endpoint'    => $info['source'] === 'dinamis' ? "assistant/{$slug}/analyze" : "{$slug}/analyze",
                'pertanyaan_contoh' => $info['examples'],
                'contoh_request'    => [
                    'role'   => ModuleRegistry::normalizeRole($peran) ?: $slug,
                    $info['id_param'] => '<' . strtoupper($info['id_param']) . '>',
                    'reason' => $info['examples'][0] ?? '<pertanyaan>',
                ],
            ];
        }

        // Sengaja tidak di-log agar polling saran tidak membanjiri api_logs.

        return $this->response->setJSON([
            'success' => true,
            'peran'   => $peran ?: null,
            'modules' => $list,
        ]);
    }

    public function ask()
    {
        $client   = $this->client();
        // PERBAIKAN H2: peran dari kebijakan, bukan deklarasi pemanggil.
        $peran    = $this->resolvedRole();
        $forced   = $this->moduleParam();
        // PERBAIKAN H1: untuk klien ber-scope 'self', subject terverifikasi
        // menimpa ID apa pun yang dikirim. Klien scope unit/role/all tetap
        // boleh menyebut ID, dan pembatasannya diterapkan di lapisan data.
        $id       = $this->verifiedSubject() ?? $this->idParam();
        $question = $this->reasonParam();

        if ($question === null || mb_strlen($question) > 2000) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'error'   => "Parameter 'reason' (pertanyaan) wajib (maks 2000 karakter).",
            ]);
        }

        // Obrolan umum: jawab cepat tanpa membuka data akademik.
        // PERBAIKAN: tetap berlaku meski modul dipaksa dari sisi klien/widget.
        // Tanpa ini, sapaan ringan seperti "selamat sore" masuk pipeline analis
        // data dan cenderung menghasilkan jawaban template berulang.
        if ($this->isGeneralChat($question, $id !== null && $id !== '')) {
            return $this->quickChat($client, $question);
        }

        $candidates = ModuleRegistry::accessible($client, $peran);
        $akses      = 'terbatas';

        // Staff dicek BAGIAN-nya: IT (atau daftar admin) = akses penuh semua modul
        if ($id !== null && ModuleRegistry::normalizeRole($peran) === 'staff') {
            $bag = ModuleRegistry::staffBagian($id);
            if ($bag !== null && in_array((string) $bag['id_bagian'], ModuleRegistry::fullAccessBagians(), true)) {
                $candidates = ModuleRegistry::accessible($client, 'admin_akademik');
                $akses      = 'penuh (' . ($bag['nama'] !== '' ? $bag['nama'] : 'bagian ' . $bag['id_bagian']) . ')';
            }
        }

        // Modul eksplisit mengalahkan routing peran
        if ($forced !== '') {
            if (! isset($candidates[$forced])) {
                return $this->response->setStatusCode(403)->setJSON([
                    'success' => false,
                    'error'   => "Modul '{$forced}' tidak tersedia untuk peran/kunci ini.",
                ]);
            }
            $candidates = [$forced => $candidates[$forced]];
        }

        if ($candidates === []) {
            $this->log($client ? (int) $client['id'] : null, '-', 'ask', 0, 'error');

            return $this->response->setStatusCode(403)->setJSON([
                'success'      => false,
                'data_refused' => true,
                'error'        => $peran !== ''
                    ? "Modul data untuk peran '{$peran}' belum diaktifkan. Data akademik tidak dibuka — tapi kamu tetap bisa mengobrol umum via endpoint chat."
                    : 'Tidak ada modul data yang bisa diakses. Kamu tetap bisa mengobrol umum via endpoint chat.',
                'fallback'     => 'chat',
            ]);
        }

        // Prioritas: kandidat dinamis yang mendukung tabel lookup yang diminta
        $slug      = null;
        $wantTable = $this->param('tabel', 'table') ?? '';
        if ($wantTable !== '') {
            foreach ($candidates as $s => $info) {
                if (($info['source'] ?? '') !== 'dinamis' || empty($info['row']['query_config'])) {
                    continue;
                }
                $cfg = json_decode($info['row']['query_config'], true);
                if (is_array($cfg) && in_array($wantTable, $cfg['lookup_tables'] ?? [], true)) {
                    $slug = $s;
                    break;
                }
            }
        }

        // Bedah pertanyaan per kata dulu agar tidak salah tangkap.
        // Tafsir hanya diteruskan ke AI, TIDAK ikut skor routing.
        $parsed     = \App\Libraries\Academic\QueryParser::parse($question);
        $questionAi = $question;
        if ($parsed['tafsir'] !== '') {
            $questionAi .= "\n\n[TAFSIR SISTEM — " . $parsed['tafsir'] . ']';
        }

        // Urutan: (1) orang yang DISEBUT, (2) ID penanya sendiri,
        // (3) skor kata kunci. ID disebut selalu menang atas ID penanya.
        $matchedId = null;
        if ($slug === null && count($candidates) > 1) {
            $hit = $this->matchIdOwner($candidates, $parsed);
            if ($hit !== null && $hit[0] === '__ambig') {
                $opts = [];
                foreach ($hit[2] as $p) {
                    $opts[] = [
                        'slug'     => $p['slug'],
                        'nama'     => $p['nama'] . ' (' . $p['id'] . ')',
                        'id'       => $p['id'],
                        'id_param' => $candidates[$p['slug']]['id_param'] ?? 'id',
                    ];
                }

                return $this->response->setJSON([
                    'success' => true,
                    'clarify' => true,
                    'message' => 'Ada beberapa orang bernama mirip. Pilih yang dimaksud:',
                    'options' => $opts,
                ]);
            }
            if ($hit !== null) {
                [$slug, $matchedId] = $hit;
            }
        }
        if ($slug === null && count($candidates) > 1 && $id !== null && $id !== '') {
            $own = $this->ownerOfCaller($candidates, $id);
            if ($own !== null) {
                $slug      = $own;
                $matchedId = $id;
            }
        }
        if ($slug === null) {
            if (count($candidates) === 1) {
                $slug = array_key_first($candidates);
            } else {
                $slug = $this->pickByKeywords($candidates, $question);
            }
        }

        if ($slug === null) {
            $options = [];
            foreach ($candidates as $s => $info) {
                $options[] = ['slug' => $s, 'nama' => $info['name'], 'pertanyaan_contoh' => $info['examples']];
            }

            return $this->response->setJSON([
                'success'  => true,
                'clarify'  => true,
                'message'  => 'Pertanyaanmu bisa dijawab beberapa modul. Pilih salah satu atau perjelas.',
                'options'  => $options,
            ]);
        }

        $info   = $candidates[$slug];
        $params = [
            $info['id_param'] => $matchedId ?? $id ?? $this->param($info['id_param']),
            'pertanyaan'      => $questionAi,
            'tabel'           => $this->param('tabel', 'table'),
            'kolom'           => $this->param('kolom', 'columns'),
            'filter_kolom'    => $this->param('filter_kolom', 'filter_col'),
            'filter_nilai'    => $this->param('filter_nilai', 'filter_value'),
            'limit'           => $this->param('limit'),
        ];

        // Mode streaming jejak realtime
        if ($this->request->getGet('stream') === '1'
            || str_contains($this->request->getHeaderLine('Accept'), 'text/event-stream')) {
            $this->runStream($slug, $info, $params, $akses);
        }

        // Delegasi ke analis modul (sekaligus logging di sana)
        if (($info['source'] ?? '') === 'dinamis') {
            return $this->runDynamic($slug, $info['row'], $params, $akses);
        }

        $class = $info['class'];

        return $this->runModule($slug, $class, $params, $akses !== 'terbatas' ? ['akses' => $akses] : []);
    }

    /** Heuristik ringan agar sapaan/obrolan tidak masuk pipeline analisis data. */
    private function isGeneralChat(string $question, bool $hasId = false): bool
    {
        if ($this->param('tabel', 'table') !== null) {
            return false;
        }

        $q = mb_strtolower(trim($question));
        if ($q === '') {
            return false;
        }

        // Kata akademik → selalu jalur data (prioritas tertinggi)
        $academic = [
            'krs', 'khs', 'ipk', 'ips', 'transkrip', 'matkul', 'mk', 'nim', 'npm', 'nik', 'nidn',
            'dosen', 'mahasiswa', 'staff', 'tendik', 'semester', 'nilai', 'jadwal', 'presensi', 'absen',
            'skripsi', 'yudisium', 'wisuda', 'prasyarat', 'kurikulum', 'akademik', 'siakad', 'kampus',
            'ukt', 'tagihan', 'registrasi', 'her', 'beasiswa', 'pembimbing', 'cuti', 'kalender',
        ];
        $words = self::words($q);
        foreach ($academic as $kw) {
            if (in_array($kw, $words, true)) {
                return false;
            }
        }

        $smallTalk = [
            'hai', 'halo', 'pagi', 'siang', 'sore', 'malam', 'apa kabar', 'terima kasih', 'makasih',
            'siapa kamu', 'bantu saya', 'tolong bantu', 'ok', 'oke', 'sip', 'selamat', 'thanks', 'p',
        ];
        $joined = ' ' . implode(' ', $words) . ' ';
        foreach ($smallTalk as $kw) {
            if (str_contains($joined, ' ' . $kw . ' ')) {
                return true;
            }
        }

        // Pertanyaan umum pendek tanpa ID lebih baik dijawab cepat.
        // (Ber-ID dilewatkan agar tetap bisa membuka data profil.)
        return ! $hasId && mb_strlen($q) <= 120;
    }

    /** Jalur jawaban cepat untuk obrolan umum. */
    private function quickChat(?array $client, string $question)
    {
        $small = $this->smallTalkReply($question);
        if ($small !== null) {
            $this->log($client ? (int) $client['id'] : null, '-', 'ask/obrolan', 0);

            return $this->response->setJSON([
                'success'  => true,
                'mode'     => 'obrolan',
                'analysis' => $small,
                'tokens'   => 0,
            ]);
        }

        try {
            [$provider, $url, $model, $opt] = AiClient::currentConfig();
            $model = AiClient::clientModel($client, $model);
            $opt['timeout'] = 120;
            $reply = AiClient::chat($provider, $url, $model, \App\Libraries\WebSearch::withWebContext([
                ['role' => 'system', 'content' => \App\Libraries\PromptBuilder::general(
                    'asisten kampus yang ramah. Ini obrolan umum: jawab cepat, jelas, santai, maksimal 120 kata, dan jangan membuka atau menebak data akademik internal'
                )],
                ['role' => 'user', 'content' => $question],
            ], $question), $opt);
        } catch (\RuntimeException $e) {
            $this->log($client ? (int) $client['id'] : null, '-', 'ask/obrolan', 0, 'error');

            return $this->response->setStatusCode(502)->setJSON(['success' => false, 'error' => $e->getMessage()]);
        }

        $this->log($client ? (int) $client['id'] : null, '-', 'ask/obrolan', (int) ($reply['tokens'] ?? 0));

        return $this->response->setJSON([
            'success'  => true,
            'mode'     => 'obrolan',
            'analysis' => $reply['content'],
            'tokens'   => (int) ($reply['tokens'] ?? 0),
        ]);
    }

    /** Balasan obrolan ringan yang deterministic (anti-template model). */
    private function smallTalkReply(string $question): ?string
    {
        $q = mb_strtolower(trim($question));
        if ($q === '') {
            return null;
        }

        if (str_contains($q, 'terima kasih') || str_contains($q, 'makasih') || str_contains($q, 'thanks')) {
            return 'Sama-sama. Kalau ada yang ingin ditanyakan, tinggal tulis saja.';
        }
        if (str_contains($q, 'selamat pagi') || $q === 'pagi') {
            return 'Selamat pagi juga. Semoga harimu lancar. Mau bahas apa dulu?';
        }
        if (str_contains($q, 'selamat siang') || $q === 'siang') {
            return 'Selamat siang juga. Siap, saya bantu sesuai pertanyaanmu.';
        }
        if (str_contains($q, 'selamat sore') || $q === 'sore') {
            return 'Selamat sore juga. Lanjut, pertanyaanmu apa?';
        }
        if (str_contains($q, 'selamat malam') || $q === 'malam') {
            return 'Selamat malam juga. Kalau ada yang ingin ditanyakan, langsung saja.';
        }
        if (str_contains($q, 'apa kabar')) {
            return 'Baik, terima kasih. Semoga kamu juga baik. Ada yang ingin kamu bahas?';
        }
        if ($q === 'halo' || $q === 'hai' || $q === 'hallo') {
            return 'Halo. Saya siap bantu sesuai pertanyaanmu.';
        }
        if ($q === 'ok' || $q === 'oke' || $q === 'sip') {
            return 'Siap. Lanjut saja, saya ikuti pertanyaanmu.';
        }

        return null;
    }

    private function runDynamic(string $slug, array $row, array $params, string $akses = 'terbatas', bool $asArray = false)
    {
        $client = $this->client();
        $t0 = microtime(true);

        try {
            $result = DynamicAssistant::analyzeModule($row, $params);
        } catch (\RuntimeException $e) {
            $this->log($client ? (int) $client['id'] : null, $slug, 'assistant/' . $slug . '/analyze', 0, 'error');

            return $asArray
                ? ['success' => false, 'module' => $slug, 'error' => $e->getMessage()]
                : $this->response->setStatusCode(422)->setJSON(['success' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable) {
            $this->log($client ? (int) $client['id'] : null, $slug, 'assistant/' . $slug . '/analyze', 0, 'error');

            return $asArray
                ? ['success' => false, 'module' => $slug, 'error' => 'Kesalahan internal.']
                : $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => 'Kesalahan internal.']);
        }

        $this->log($client ? (int) $client['id'] : null, $slug, 'assistant/' . $slug . '/analyze', (int) ($result['tokens'] ?? 0));
        log_message('info', '[ask] modul=' . $slug . ' durasi=' . round(microtime(true) - $t0, 1) . 's');

        $out = [
            'success'  => true,
            'module'   => $slug,
            'data'     => $result['data'],
            'analysis' => $result['analysis'],
            'tokens'   => $result['tokens'] ?? 0,
            'jejak'    => $result['jejak'] ?? [],
            'evidence' => $result['evidence'] ?? null,
            'confidence' => $result['confidence'] ?? 'sedang',
        ];
        if ($akses !== 'terbatas') {
            $out['akses'] = $akses;
        }

        return $asArray ? $out : $this->response->setJSON($out);
    }

    /**
     * Mode SSE: streaming jejak sub-agen realtime + hasil akhir.
     * Aktif bila ?stream=1 atau Accept: text/event-stream.
     */
    private function runStream(string $slug, array $info, array $params, string $akses)
    {
        $t0 = microtime(true);
        @set_time_limit(600);
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');

        $send = static function (string $event, array $data) {
            echo 'event: ' . $event . "\n";
            echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
            @ob_flush();
            flush();
        };

        $send('mulai', ['module' => $slug]);
        $params['__step'] = static function (string $tahap, string $pesan) use ($send) {
            $send('jejak', ['tahap' => $tahap, 'pesan' => $pesan, 'jam' => date('H:i:s')]);
        };

        try {
            if (($info['source'] ?? '') === 'dinamis') {
                $out = $this->runDynamic($slug, $info['row'], $params, $akses, true);
            } else {
                $out = $this->runModule(
                    $slug, $info['class'], $params,
                    $akses !== 'terbatas' ? ['akses' => $akses] : [], true
                );
            }
        } catch (\Throwable $e) {
            $out = ['success' => false, 'error' => 'Kesalahan internal.'];
        }

        log_message('info', '[ask-stream] modul=' . $slug . ' durasi=' . round(microtime(true) - $t0, 1) . 's sukses=' . (! empty($out['success']) ? '1' : '0'));
        $send('selesai', $out);
        exit;
    }

    /**
     * Pilih modul: kata kunci kamus + kemiripan contoh. Null bila seri/tidak yakin.
     * (Pencocokan ID/nama sudah dicoba sebelum fungsi ini dipanggil.)
     */
    private function pickByKeywords(array $candidates, string $question): ?string
    {
        $q     = mb_strtolower($question);
        $best  = null;
        $score = -1;

        foreach ($candidates as $slug => $info) {
            // Hitung entri kamus NON-'*' yang cocok (aturan '*' tidak dihitung agar adil)
            $hits = 0;
            $len  = 0;
            try {
                $all = (new DictModel())->where('is_active', 1)->whereIn('module', ['umum', $slug])->orderBy('id', 'ASC')->findAll();
                foreach ($all as $row) {
                    $topik = trim((string) $row['topik']);
                    if ($topik === '*') {
                        continue;
                    }
                    foreach (explode(',', $topik) as $kw) {
                        $kw = trim(mb_strtolower($kw));
                        if ($kw !== '' && mb_strpos($q, $kw) !== false) {
                            $hits++;
                            $len += mb_strlen($row['tabel'] . $row['kolom'] . $row['deskripsi']);
                            break;
                        }
                    }
                }
            } catch (\Throwable) {
            }

            $s = $hits * 1000 + $len;
            // Bonus bila nama modul disebut eksplisit
            if (mb_strpos($q, $slug) !== false || mb_strpos($q, mb_strtolower($info['name'])) !== false) {
                $s += 5000;
            }
            // Kemiripan dengan contoh pertanyaan modul (kata bermakna sama)
            $s += 300 * self::wordOverlap($q, $info['examples'] ?? []);

            if ($s > $score) {
                $score = $s;
                $best  = $slug;
            } elseif ($s === $score) {
                $best = null; // seri → minta klarifikasi
            }
        }

        return $score > 0 ? $best : null;
    }

    /**
     * Cari SIAPA yang dimaksud (ID lalu nama, dari hasil bedah QueryParser).
     * Return [slug, idAsli, people[]]; people terisi bila nama ambigu.
     */
    private function matchIdOwner(array $candidates, array $parsed): ?array
    {
        $prof = [];
        foreach ($candidates as $slug => $info) {
            if (($info['source'] ?? '') === 'dinamis' && ! empty($info['row']['query_config'])) {
                $cfg = json_decode($info['row']['query_config'], true);
                $t   = $cfg['profile']['table'] ?? null;
                $c   = $cfg['profile']['id_col'] ?? null;
                $n   = $cfg['profile']['name_col'] ?? null;
            } else {
                $t = $info['id_table'] ?? null;
                $c = $info['id_col'] ?? null;
                $n = $info['name_col'] ?? null;
            }
            if ($t && $c) {
                $prof[$slug] = [$t, $c, $n];
            }
        }

        if ($prof === []) {
            return null;
        }

        // 1. ID yang disebut → pemiliknya siapa
        $hits = [];
        foreach ($prof as $slug => [$t, $c]) {
            foreach ($parsed['ids'] as $tok) {
                try {
                    $rows = \App\Libraries\AcademicDb::select($t, [$c], [$c => $tok], 1, $c . ' ASC', [$c], $slug);
                    if ($rows !== []) {
                        $hits[] = [$slug, trim((string) $rows[0][$c])];
                        break;
                    }
                } catch (\Throwable) {
                }
            }
        }
        if (count($hits) === 1) {
            return $hits[0];
        }

        // 2+3. Nama eksak, lalu fuzzy per kata (SQL mentah — terbukti stabil).
        $found = []; // [slug, id, nama]
        foreach ($prof as $slug => [$t, $c, $n]) {
            if (! $n) {
                continue;
            }
            foreach ([$t, $c, $n] as $ident) {
                if (! preg_match('/^[A-Za-z0-9_]+$/', $ident)) {
                    continue 2;
                }
            }
            foreach ($parsed['names'] as $ph) {
                try {
                    $db   = \App\Libraries\AcademicDb::connect();
                    $rows = $db->query(
                        'SELECT TOP 4 ' . $c . ' AS id, ' . $n . ' AS nama FROM ' . $t
                        . ' WHERE LTRIM(RTRIM(' . $n . ')) LIKE ' . $db->escape('%' . $db->escapeLikeString($ph) . '%')
                    )->getResultArray();
                    if ($rows === []) {
                        $words = array_filter(preg_split('/\s+/', $ph), static fn ($w) => mb_strlen($w) >= 5);
                        if ($words === []) {
                            continue;
                        }
                        $conds = [];
                        foreach (array_slice($words, 0, 3) as $w) {
                            $conds[] = 'LTRIM(RTRIM(' . $n . ')) LIKE ' . $db->escape('%' . $db->escapeLikeString(mb_substr($w, 0, 5)) . '%');
                        }
                        $rows = $db->query(
                            'SELECT TOP 4 ' . $c . ' AS id, ' . $n . ' AS nama FROM ' . $t
                            . ' WHERE ' . implode(' AND ', $conds)
                        )->getResultArray();
                    }
                    foreach ($rows as $r) {
                        $found[] = [$slug, trim((string) $r['id']), trim((string) $r['nama'])];
                    }
                    break;
                } catch (\Throwable) {
                }
            }
        }

        // Unik lintas modul → langsung pakai. Banyak → kembalikan daftar orang.
        $slugs = array_unique(array_column($found, 0));
        if (count($found) === 1) {
            return [$found[0][0], $found[0][1], []];
        }
        if (count($found) > 1) {
            $people = [];
            foreach (array_slice($found, 0, 6) as [$s, $id, $nama]) {
                $people[] = ['slug' => $s, 'id' => $id, 'nama' => $nama];
            }

            return ['__ambig', '', $people];
        }

        return null;
    }

    /** Kata bersih untuk perbandingan (tanpa tanda baca). */
    private static function words(string $s): array
    {
        $parts = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($s));
        $parts = array_unique(array_filter($parts, static fn ($w) => mb_strlen($w) > 3));

        return array_values($parts);
    }

    /** Jumlah kata bermakna yang sama antara pertanyaan dan contoh terbanyak. */
    private static function wordOverlap(string $q, array $examples): int
    {
        $qw   = self::words($q);
        $best = 0;

        foreach ($examples as $ex) {
            $best = max($best, count(array_intersect($qw, self::words((string) $ex))));
        }

        return $best;
    }

    /**
     * "Tentang saya": ID penanya sendiri ada di tabel profil modul siapa?
     * Return slug bila unik, else null.
     */
    private function ownerOfCaller(array $candidates, string $id): ?string
    {
        $hits = [];
        foreach ($candidates as $slug => $info) {
            if (($info['source'] ?? '') === 'dinamis' && ! empty($info['row']['query_config'])) {
                $cfg = json_decode($info['row']['query_config'], true);
                $t   = $cfg['profile']['table'] ?? null;
                $c   = $cfg['profile']['id_col'] ?? null;
            } else {
                $t = $info['id_table'] ?? null;
                $c = $info['id_col'] ?? null;
            }
            if (! $t || ! $c || ! preg_match('/^[A-Za-z0-9_]+$/', $t) || ! preg_match('/^[A-Za-z0-9_]+$/', $c)) {
                continue;
            }
            try {
                $rows = \App\Libraries\AcademicDb::select($t, [$c], [$c => $id], 1, $c . ' ASC', [$c], $slug);
                if ($rows !== []) {
                    $hits[] = $slug;
                }
            } catch (\Throwable) {
            }
        }

        return count($hits) === 1 ? $hits[0] : null;
    }
}
