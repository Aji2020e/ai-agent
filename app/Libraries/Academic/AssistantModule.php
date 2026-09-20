<?php

namespace App\Libraries\Academic;

use App\Libraries\AcademicDb;
use App\Libraries\AiClient;
use App\Models\ApiDocModel;
use RuntimeException;

/**
 * Basis modul analis akademik. Semua akses data READ-ONLY via AcademicDb.
 */
abstract class AssistantModule
{
    abstract public static function slug(): string;

    abstract public static function name(): string;

    /** Peta tabel default; bisa ditimpa via settings akad_map (JSON). */
    abstract protected static function defaultMap(): array;

    /** Analisis. $params dari API. Return ['data'=>..., 'analysis'=>..., 'tokens'=>...]. */
    abstract public static function analyze(array $params): array;

    public static function tableMap(): array
    {
        $map = static::defaultMap();

        try {
            $json = (new \App\Models\SettingModel())->getGlobal('akad_map', '');
            if ($json !== '') {
                $custom = json_decode($json, true);
                if (is_array($custom) && isset($custom[static::slug()]) && is_array($custom[static::slug()])) {
                    $map = array_merge($map, $custom[static::slug()]);
                }
            }
        } catch (\Throwable) {
        }

        return $map;
    }

    /** Daftar dokumen panduan aktif (judul + konten terpotong). */
    protected static function docsList(): array
    {
        return (new ApiDocModel())->forModule(static::slug());
    }

    protected static function docs(): string
    {
        $docs = static::docsList();
        $out  = [];

        foreach ($docs as $d) {
            $out[] = '### ' . $d['title'] . "\n" . $d['content'];
        }

        return implode("\n\n", $out);
    }

    /** Metadata bukti untuk audit jawaban (sumber data + panduan). */
    protected static function evidence(array $tables, array $docs, string $question, array $extra = []): array
    {
        $docTitles = [];
        foreach ($docs as $d) {
            $t = trim((string) ($d['title'] ?? ''));
            if ($t !== '') {
                $docTitles[] = $t;
            }
        }

        return array_merge([
            'question'      => $question,
            'tables'        => array_values(array_unique(array_filter($tables))),
            'docs'          => array_values(array_unique($docTitles)),
            'generated_at'  => date('c'),
            'source_policy' => 'read_only_db + official_guides + dictionary',
        ], $extra);
    }

    /**
     * Keyakinan jawaban. Utama: hasil deliberasi agen (melihat isi, bukan
     * sekadar struktur). Heuristik hanya fallback bila deliberasi bungkam.
     */
    protected static function confidence(array $data, string $analysis, array $evidence = [], ?string $deliberation = null): string
    {
        if (in_array($deliberation, ['tinggi', 'sedang', 'rendah'], true)) {
            return $deliberation;
        }

        $score = 0;

        if ($data !== []) {
            $score += 2;
        }

        $docCount = count((array) ($evidence['docs'] ?? []));
        if ($docCount > 0) {
            $score += 1;
        }

        $tableCount = count((array) ($evidence['tables'] ?? []));
        if ($tableCount > 1) {
            $score += 1;
        }

        $a = mb_strtolower($analysis);
        if (str_contains($a, 'data tidak') || str_contains($a, 'tidak ditemukan') || str_contains($a, 'belum cukup')) {
            $score -= 1;
        }

        if ($score >= 4) {
            return 'tinggi';
        }
        if ($score >= 2) {
            return 'sedang';
        }

        return 'rendah';
    }

    /** Kamus data resmi yang cocok dengan topik pertanyaan. */
    protected static function dictionary(string $question): string
    {
        try {
            $lines = (new \App\Models\ApiDictionaryModel())->forQuestion(static::slug(), $question);

            return $lines === [] ? '' : "KAMUS DATA RESMI (sumber kebenaran tabel/kolom):\n" . implode("\n", $lines);
        } catch (\Throwable) {
            return '';
        }
    }

    /** Panggil AI provider aktif dengan konteks modul (satu tahap). */
    protected static function ask(string $systemRole, string $userText, int $timeout = 300): array
    {
        [$provider, $url, $model, $opt] = AiClient::currentConfig();
        $model = AiClient::clientModel(\App\Libraries\ApiAuth::client(), $model);
        $opt['timeout']                 = $timeout;

        // Suntik aturan grounding ke setiap panggilan, dari mana pun systemRole
        // berasal. Sebelumnya tiap asisten menulis aturannya sendiri-sendiri dan
        // sebagian besar tidak melarang pengarangan angka/tanggal secara eksplisit.
        $systemRole .= "\n\n" . \App\Libraries\PromptBuilder::groundingRules(true, true);

        $messages = \App\Libraries\WebSearch::withWebContext([
            ['role' => 'system', 'content' => $systemRole],
            ['role' => 'user', 'content' => $userText],
        ], $userText);
        $reply = AiClient::chat($provider, $url, $model, $messages, $opt);

        return [$reply['content'], (int) $reply['tokens'], $model];
    }

    /** Panduan gaya bahasa (bisa ditimpa via settings answer_style). */
    protected static function styleGuide(): string
    {
        try {
            $custom = trim((string) (new \App\Models\SettingModel())->getGlobal('answer_style', ''));
            if ($custom !== '') {
                return $custom;
            }
        } catch (\Throwable) {
        }

        return 'GAYA BAHASA: Indonesia akademik yang hangat dan manusiawi, tidak kaku. '
            . 'Sapa sesuai peran (mahasiswa/dosen/staf). Langsung ke inti, konkret dengan angka/kode yang ada. '
            . 'Hindari bahasa birokrasi dan template robot ("Sebagai AI...", "Berikut adalah..."). '
            . 'Jangan mengulang salam pembuka yang sama di setiap jawaban. '
            . 'Jangan menambah kalimat template seperti "Ada yang bisa saya bantu..." kecuali user memang meminta arahan umum. '
            . 'Bila user memberi sapaan singkat, balas sapaan singkat juga (1-2 kalimat), jangan ubah jadi paragraf panjang. '
            . 'Sebut ID/NIM/NIK user hanya bila relevan dengan pertanyaan saat ini. '
            . 'JANGAN pernah mengaku sebagai dosen/rektor/pejabat — kamu asisten AI. '
            . 'Boleh memberi semangat singkat bila relevan. Maksimal 250 kata kecuali diminta rinci.';
    }

    /**
     * Pipeline agen berpikir kritis: analis → kritik/resolve (bisa gali data
     * lagi, maks agent_max_rounds) → deliberasi → penyusun bahasa.
     * $step: callback(string $tahap, string $pesan) untuk jejak realtime.
     * Return [jawabanAkhir, totalToken, model, jejak[]].
     */
    protected static function answer(string $systemRole, string $dataText, string $question, int $timeout = 300, ?callable $step = null): array
    {
        // Jika ini obrolan ringan (sapaan/terima kasih), jangan lewatkan
        // pipeline analis-kritikus agar tidak keluar format jawaban template.
        if (static::isSmallTalk($question)) {
            $emitOnly = [];
            $emitOnly[] = ['tahap' => 'obrolan', 'pesan' => 'Obrolan umum terdeteksi, menjawab secara generik.', 'jam' => date('H:i:s')];
            if ($step) {
                $step('obrolan', 'Obrolan umum terdeteksi, menjawab secara generik.');
            }

            // Balasan deterministik agar tidak kembali ke template model.
            return [static::smallTalkReply($question), 0, 'rule-based', $emitOnly, 'sedang'];
        }

        $tokens = 0;
        $model  = '';
        $jejak  = [];
        $emit   = static function (string $tahap, string $pesan) use (&$jejak, $step) {
            $jejak[] = ['tahap' => $tahap, 'pesan' => $pesan, 'jam' => date('H:i:s')];
            if ($step) {
                $step($tahap, $pesan);
            }
        };

        // Agen 1: pembaca data — keluarkan temuan terstruktur (JSON)
        $emit('analis', 'Agen Analis membaca data…');
        [$raw, $tok, $model] = static::ask(
            $systemRole . ' Tugasmu pada tahap ini: baca data, JANGAN menjawab langsung. '
            . 'Keluarkan HANYA objek JSON valid tanpa markdown: '
            . '{"ringkasan":"1 kalimat","fakta":["..."],"temuan":["..."],"saran_mentah":["..."],"info_kurang":["..."]}.',
            $dataText . "\n\nPERTANYAAN: {$question}",
            $timeout
        );
        $tokens += $tok;
        $facts   = static::parseFacts($raw);

        // Agen 2 (kritikus-resolver): uji temuan, minta gali lagi bila ragu
        $rounds = max(0, min(2, (int) static::setting('agent_max_rounds', '1')));
        $criticNotes = [];
        for ($i = 0; $i <= $rounds; $i++) {
            [$critRaw, $tok, $model] = static::ask(
                'Kamu auditor berpikir kritis. Periksa TEMUAN ANALIS terhadap DATA dan PERTANYAAN. '
                . 'Cari: kontradiksi, klaim tanpa dukungan data, overclaim, info kurang. '
                . 'Bila butuh data tambahan yang ADA di kamus, minta spesifik. '
                . 'Keluarkan HANYA JSON valid: '
                . '{"lolos":true/false,"masalah":["..."],"catatan":"...","butuh_data":[{"tabel":"...","kolom":"...","filter_kolom":"...","filter_nilai":"...","alasan":"..."}]}. '
                . 'Set lolos=true bila temuan sudah cukup untuk menjawab jujur (termasuk menjawab "data tidak ada").',
                "PERTANYAAN: {$question}\n\nDATA:\n{$dataText}\n\nTEMUAN ANALIS:\n" . json_encode($facts, JSON_UNESCAPED_UNICODE),
                $timeout
            );
        $tokens += $tok;
        $nFakta = 0;
        foreach (['fakta', 'temuan', 'saran_mentah'] as $k) {
            if (is_array($facts[$k] ?? null)) {
                $nFakta += count($facts[$k]);
            }
        }
        if ($nFakta === 0 && trim(json_encode($facts, JSON_UNESCAPED_UNICODE)) !== '') {
            $nFakta = 1; // temuan non-JSON tetap dihitung 1 simpulan
        }
        $emit('analis', "Agen Analis menemukan {$nFakta} poin dari data.");
        $emit('kritikus', 'Agen Kritikus memeriksa temuan…');
        $crit = static::parseFacts($critRaw);

        if (! empty($crit['masalah'])) {
            $criticNotes = array_merge($criticNotes, (array) $crit['masalah']);
        }
        if (! empty($crit['catatan'])) {
            $criticNotes[] = (string) $crit['catatan'];
        }

        $needs = $crit['butuh_data'] ?? [];
        if (empty($crit['lolos']) && is_array($needs) && $needs !== [] && $i < $rounds) {
            $emit('kritikus', 'Kritikus belum yakin — meminta gali data tambahan.');
            $extra = static::fetchExtra($needs);
            if ($extra !== '') {
                $dataText .= "\n\nDATA TAMBAHAN (hasil gali ulang):\n" . $extra;
                $emit('analis', 'Agen Analis membaca ulang dengan data tambahan…');
                // Analis baca ulang dengan data baru
                [$raw2, $tok2] = static::ask(
                    $systemRole . ' Baca ULANG semua data termasuk DATA TAMBAHAN. '
                    . 'Keluarkan HANYA objek JSON valid: '
                    . '{"ringkasan":"1 kalimat","fakta":["..."],"temuan":["..."],"saran_mentah":["..."],"info_kurang":["..."]}.',
                    $dataText . "\n\nPERTANYAAN: {$question}",
                    $timeout
                );
                $tokens += $tok2;
                $facts   = static::parseFacts($raw2);
                continue;
            }
        }
        $emit('kritikus', empty($crit['lolos']) ? 'Kritikus mencatat keberatan.' : 'Kritikus menyetujui temuan.');
        break;
    }

        // Agen 3: DELIBERASI — analis & kritikus bertukar argumen sampai konsensus
        $consensus  = '';
        $keyakinan  = null;
        $emit('deliberasi', 'Agen deliberasi menengahi Analis vs Kritikus…');
        try {
            [$delibRaw, $tokD] = static::ask(
                'Kamu moderator diskusi dua agen AI (Analis Data vs Kritikus). '
                . 'Bacalah argumen keduanya, damaikan perbedaan, dan tetapkan kesimpulan AMAN yang disepakati. '
                . 'Keluarkan HANYA JSON valid: '
                . '{"kesepakatan":"1 kalimat","poin_diperdebatkan":["..."],"kesimpulan_aman":["..."],"keyakinan":"tinggi/sedang/rendah"}.',
                "PERTANYAAN: {$question}\n\nARGUMEN ANALIS:\n" . json_encode($facts, JSON_UNESCAPED_UNICODE)
                . "\n\nSANGGAHAN KRITIKUS:\n- " . ($criticNotes !== [] ? implode("\n- ", $criticNotes) : '(tidak ada sanggahan)'),
                $timeout
            );
            $tokens += $tokD;
            $consensus = $delibRaw;
            $parsed    = static::parseFacts($delibRaw);
            $k         = strtolower(trim((string) ($parsed['keyakinan'] ?? '')));
            if (in_array($k, ['tinggi', 'sedang', 'rendah'], true)) {
                $keyakinan = $k;
            }
        } catch (\Throwable) {
        }

        // Agen 4: perangkai bahasa akademik yang hangat + jujur
        $composerIn = "PERTANYAAN: {$question}\n\nTEMUAN ANALIS:\n" . json_encode($facts, JSON_UNESCAPED_UNICODE);
        if ($criticNotes !== []) {
            $composerIn .= "\n\nCATATAN KRITIS (wajib dipatuhi — sampaikan ketidakpastian secara jujur):\n- " . implode("\n- ", $criticNotes);
        }
        if ($consensus !== '') {
            $composerIn .= "\n\nKONSENSUS DISKUSI AGEN:\n" . $consensus;
        }

        $emit('penyusun', 'Penyusun merangkai jawaban akhir…');
        [$text, $tok3] = static::ask(
            'Kamu penasihat akademik yang ramah dan berpengalaman. '
            . 'Susun jawaban dari TEMUAN ANALIS berikut. ' . static::styleGuide(),
            $composerIn,
            $timeout
        );
        $tokens += $tok3;
        $emit('selesai', 'Jawaban akhir siap.');

        return [$text, $tokens, $model, $jejak, $keyakinan];
    }

    /** Heuristik obrolan ringan agar tidak masuk pipeline analisis data. */
    protected static function isSmallTalk(string $question): bool
    {
        $q = mb_strtolower(trim($question));
        if ($q === '') {
            return false;
        }

        // Jika ada kata data spesifik, jangan dianggap small-talk.
        $dataKeywords = [
            'ipk', 'ips', 'krs', 'khs', 'nilai', 'jadwal', 'semester', 'npm', 'nim', 'nik', 'nidn',
            'dosen', 'mahasiswa', 'staff', 'jabatan', 'email', 'transkrip', 'sks', 'ukt', 'tagihan',
        ];
        foreach ($dataKeywords as $kw) {
            if (str_contains($q, $kw)) {
                return false;
            }
        }

        $smallTalk = [
            'halo', 'hai', 'pagi', 'siang', 'sore', 'malam', 'apa kabar',
            'terima kasih', 'makasih', 'thanks', 'ok', 'oke', 'sip', 'hallo',
        ];

        foreach ($smallTalk as $kw) {
            if (str_contains($q, $kw)) {
                return true;
            }
        }

        return mb_strlen($q) <= 24;
    }

    /** Balasan obrolan ringan yang singkat dan natural. */
    protected static function smallTalkReply(string $question): string
    {
        $q = mb_strtolower(trim($question));

        if (str_contains($q, 'terima kasih') || str_contains($q, 'makasih') || str_contains($q, 'thanks')) {
            return 'Sama-sama. Kalau ada yang ingin ditanyakan, tinggal tulis saja.';
        }

        if (str_contains($q, 'selamat pagi') || str_contains($q, 'pagi')) {
            return 'Selamat pagi juga. Semoga harimu lancar. Mau bahas apa dulu?';
        }

        if (str_contains($q, 'selamat siang') || str_contains($q, 'siang')) {
            return 'Selamat siang juga. Siap, saya bantu sesuai pertanyaanmu.';
        }

        if (str_contains($q, 'selamat sore') || str_contains($q, 'sore')) {
            return 'Selamat sore juga. Lanjut, pertanyaanmu apa?';
        }

        if (str_contains($q, 'selamat malam') || str_contains($q, 'malam')) {
            return 'Selamat malam juga. Kalau ada yang ingin ditanyakan, langsung saja.';
        }

        if (str_contains($q, 'apa kabar')) {
            return 'Baik, terima kasih. Semoga kamu juga baik. Ada yang ingin kamu bahas?';
        }

        if (str_contains($q, 'ok') || str_contains($q, 'oke') || str_contains($q, 'sip')) {
            return 'Siap. Lanjut saja, saya ikuti pertanyaanmu.';
        }

        return 'Halo. Saya siap bantu sesuai pertanyaanmu.';
    }

    /**
     * Gali data tambahan yang diminta kritikus.
     * Whitelist: tabel modul ini (kamus + query_config) + lolos skema nyata.
     */
    protected static function fetchExtra(array $needs): string
    {
        $allowed = static::allowedTables();
        $out     = [];

        foreach (array_slice($needs, 0, 3) as $need) {
            if (! is_array($need) || empty($need['tabel'])) {
                continue;
            }
            $table = trim((string) $need['tabel']);
            if (! preg_match('/^[A-Za-z0-9_]+$/', $table) || ! in_array($table, $allowed, true)) {
                continue;
            }
            try {
                $real = AcademicDb::columns($table);
                $cols = [];
                foreach (preg_split('/[\s,]+/', (string) ($need['kolom'] ?? '')) as $c) {
                    $c = trim($c);
                    if ($c !== '' && preg_match('/^[A-Za-z0-9_]+$/', $c)
                        && in_array($c, $real, true) && ! preg_match('/pass|password|passwd|foto/i', $c)) {
                        $cols[] = $c;
                    }
                }
                if ($cols === []) {
                    $cols = array_slice(array_filter($real, static fn ($c) => ! preg_match('/pass|password|passwd|foto/i', $c)), 0, 12);
                }
                if ($cols === []) {
                    continue;
                }
                $where = [];
                $fc    = trim((string) ($need['filter_kolom'] ?? ''));
                $fv    = trim((string) ($need['filter_nilai'] ?? ''));
                if ($fc !== '' && preg_match('/^[A-Za-z0-9_]+$/', $fc) && in_array($fc, $real, true) && mb_strlen($fv) <= 100) {
                    $where = [$fc => $fv];
                }
                $rows = AcademicDb::select($table, $cols, $where, 30, $cols[0] . ' ASC', [], static::slug());
                $out[] = "Tabel {$table}: " . json_encode($rows, JSON_UNESCAPED_UNICODE);
            } catch (\Throwable) {
            }
        }

        return implode("\n", $out);
    }

    /** Daftar tabel yang boleh digali ulang (kamus + konfigurasi modul). */
    protected static function allowedTables(): array
    {
        $tables = [];

        try {
            $rows = (new \App\Models\ApiDictionaryModel())
                ->where('is_active', 1)->whereIn('module', ['umum', static::slug()])->findAll();
            foreach ($rows as $r) {
                $t = trim((string) ($r['tabel'] ?? ''));
                if ($t !== '' && preg_match('/^[A-Za-z0-9_]+$/', $t)) {
                    $tables[] = $t;
                }
            }
        } catch (\Throwable) {
        }

        $map = static::tableMap();
        foreach (['profile_table', 'nilai_table', 'ips_table', 'prasy_table'] as $k) {
            if (! empty($map[$k])) {
                $tables[] = $map[$k];
            }
        }

        return array_values(array_unique($tables));
    }

    protected static function setting(string $key, string $default): string
    {
        try {
            return (string) (new \App\Models\SettingModel())->getGlobal($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }

    private static function parseFacts(string $raw): array
    {
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            $j = json_decode($m[0], true);
            if (is_array($j)) {
                return $j;
            }
        }

        return ['ringkasan' => $raw];
    }

    protected static function requireParam(array $params, string $key, int $max = 50): string
    {
        $v = trim((string) ($params[$key] ?? ''));

        if ($v === '' || strlen($v) > $max || ! preg_match('/^[A-Za-z0-9\-_.\/]+$/', $v)) {
            throw new RuntimeException("Parameter '{$key}' wajib dan tidak valid.");
        }

        return $v;
    }

    protected static function requireQuestion(array $params): string
    {
        $v = trim((string) ($params['pertanyaan'] ?? $params['question'] ?? ''));

        if ($v === '' || mb_strlen($v) > 2000) {
            throw new RuntimeException("Parameter 'pertanyaan' wajib (maks 2000 karakter).");
        }

        return $v;
    }
}
