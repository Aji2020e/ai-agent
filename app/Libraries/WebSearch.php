<?php

namespace App\Libraries;

use RuntimeException;
use Throwable;

/**
 * Pencarian internet sebagai sumber cadangan bila data lokal tak tersedia.
 * Provider: 'tavily' (butuh API key, hasil bagus) atau 'ddg' (gratis DuckDuckGo).
 * Return: [['title'=>, 'url'=>, 'snippet'=>], ...].
 */
class WebSearch
{
    public static function config(): array
    {
        $s = new \App\Models\SettingModel();

        return [
            'provider' => $s->getGlobal('search_provider', 'off') ?: 'off',
            'key'      => (string) $s->getSecret('search_key', ''),
            'max'      => max(1, min(8, (int) $s->getGlobal('search_max', '5'))),
        ];
    }

    /** true bila pencarian diaktifkan (dan key ada bila butuh). */
    public static function enabled(): bool
    {
        $c = self::config();

        if ($c['provider'] === 'tavily') {
            return $c['key'] !== '';
        }

        return $c['provider'] === 'ddg';
    }

    /**
     * Heuristik butuh internet: topik kekinian ATAU data lokal kosong.
     */
    public static function needsWeb(string $question, bool $localEmpty = false): bool
    {
        if (! self::enabled()) {
            return false;
        }

        if ($localEmpty) {
            return true;
        }

        $q = mb_strtolower($question);

        foreach (['terkini', 'terbaru', 'saat ini', 'sekarang', 'tahun ', 'harga', 'jadwal', 'berita', 'presiden', 'menteri', 'pemilu', 'kurs', 'cuaca', 'skor', 'hasil pertandingan', 'lowongan'] as $kw) {
            if (mb_strpos($q, $kw) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tambahkan konteks hasil pencarian ke $messages bila pertanyaan butuh internet.
     * Aman dipanggil di semua jalur (web/API): gagal/ nonaktif = pesan dikembalikan apa adanya.
     */
    /**
     * Suntikkan hasil web ke dalam pesan SYSTEM.
     *
     * PERBAIKAN B4: sebelumnya blok hasil web ditambahkan sebagai turn
     * `role: user` SETELAH pertanyaan asli. Akibatnya ada dua pesan user
     * berurutan dan instruksi "jawab pertanyaan terakhir" jadi ambigu — model
     * bisa mengira blok web itulah pertanyaannya.
     *
     * Sekarang struktur pesan tidak berubah: system tetap di depan, pesan user
     * tetap yang terakhir.
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @return array<int, array{role: string, content: string}>
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
                . "jawabanmu memakai sumber web. Bila ada data internal di konteks, "
                . "UTAMAKAN data internal atas sumber web.\n";

            foreach ($messages as $i => $m) {
                if (($m['role'] ?? '') === 'system') {
                    $messages[$i]['content'] = (string) ($m['content'] ?? '') . $inject;

                    return $messages;
                }
            }

            // Tidak ada pesan system → sisipkan di DEPAN, bukan di belakang,
            // agar pesan user tetap yang terakhir.
            array_unshift($messages, ['role' => 'system', 'content' => trim($inject)]);
        } catch (\Throwable) {
            // Kegagalan web search tidak boleh memutus permintaan
        }

        return $messages;
    }

    /** Format siap suntik ke prompt. */
    public static function contextBlock(array $results): string
    {
        if ($results === []) {
            return '';
        }

        $out = ["SUMBER INTERNET (boleh dipakai bila relevan, sebutkan bila memakai):"];
        foreach ($results as $i => $r) {
            $out[] = '[' . ($i + 1) . '] ' . $r['title'] . ' — ' . $r['snippet'] . ' (' . $r['url'] . ')';
        }

        return implode("\n", $out);
    }

    /**
     * @throws RuntimeException
     */
    public static function search(string $query, ?int $max = null): array
    {
        $c   = self::config();
        $max ??= $c['max'];

        if ($c['provider'] === 'tavily' && $c['key'] !== '') {
            return self::viaTavily($query, $max, $c['key']);
        }

        if ($c['provider'] === 'ddg') {
            return self::viaDdg($query, $max);
        }

        throw new RuntimeException('Pencarian web belum dikonfigurasi.');
    }

    private static function viaTavily(string $query, int $max, string $key): array
    {
        try {
            $res = \Config\Services::curlrequest()->post('https://api.tavily.com/search', [
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => json_encode([
                    'api_key'         => $key,
                    'query'           => $query,
                    'max_results'     => $max,
                    'include_answer'  => false,
                    'search_depth'    => 'basic',
                ]),
                'timeout' => 30,
            ]);
            $data = json_decode($res->getBody(), true);
        } catch (Throwable $e) {
            throw new RuntimeException('Tavily gagal: ' . $e->getMessage());
        }

        $out = [];
        foreach (array_slice($data['results'] ?? [], 0, $max) as $r) {
            $out[] = [
                'title'   => (string) ($r['title'] ?? ''),
                'url'     => (string) ($r['url'] ?? ''),
                'snippet' => mb_substr((string) ($r['content'] ?? ''), 0, 400),
            ];
        }

        return $out;
    }

    private static function viaDdg(string $query, int $max): array
    {
        try {
            $res = \Config\Services::curlrequest()->get(
                'https://api.duckduckgo.com/?q=' . urlencode($query) . '&format=json&no_html=1&skip_disambig=1',
                ['headers' => ['User-Agent' => 'Mozilla/5.0'], 'timeout' => 25]
            );
            $data = json_decode($res->getBody(), true);
        } catch (Throwable $e) {
            throw new RuntimeException('DuckDuckGo gagal: ' . $e->getMessage());
        }

        $out = [];
        if (! empty($data['AbstractText'])) {
            $out[] = [
                'title'   => (string) ($data['Heading'] ?? 'Ringkasan'),
                'url'     => (string) ($data['AbstractURL'] ?? ''),
                'snippet' => mb_substr((string) $data['AbstractText'], 0, 400),
            ];
        }
        foreach ($data['RelatedTopics'] ?? [] as $t) {
            if (count($out) >= $max) {
                break;
            }
            if (! empty($t['Text'])) {
                $out[] = [
                    'title'   => mb_substr((string) $t['Text'], 0, 80),
                    'url'     => (string) ($t['FirstURL'] ?? ''),
                    'snippet' => mb_substr((string) $t['Text'], 0, 400),
                ];
            }
        }

        return array_slice($out, 0, $max);
    }
}
