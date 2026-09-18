<?php

namespace App\Libraries;

/**
 * Merakit pesan AI dengan urutan dan anggaran token yang benar.
 *
 * Menjamin tiga hal yang sebelumnya tidak dijamin:
 *  1. [0]       = system prompt (identitas + aturan grounding + data)
 *  2. [1..n-1]  = riwayat, berselang-seling, dipangkas dari yang TERLAMA
 *  3. [n]       = pesan user saat ini, TEPAT SATU KALI, DI POSISI TERAKHIR
 *
 * Sebelumnya pesan saat ini muncul dua kali (dari PromptBuilder dan dari
 * riwayat yang sudah disimpan lebih dulu), dan riwayat dikirim tanpa batas
 * sehingga provider memotong dari depan — membuang system prompt beserta
 * seluruh data akademik. Model lalu menjawab tanpa data, yaitu mengarang.
 *
 * Algoritma di kelas ini sudah diverifikasi lewat simulasi terhadap skenario
 * sesi 400 pesan; lihat dokumen rencana bagian 1b.
 */
class ContextBuilder
{
    /**
     * Perbandingan karakter→token. Bahasa Indonesia sekitar 3.6 char/token.
     * Sengaja konservatif (lebih kecil = estimasi token lebih besar) agar
     * prompt tidak diam-diam melewati batas provider.
     *
     * PENTING: konstanta ini dipakai dua arah — pembagian di estimateTokens()
     * dan perkalian di truncateSystem(). Keduanya harus memakai konstanta yang
     * sama tanpa pembulatan ganda, jika tidak anggaran bisa jebol ~20%.
     */
    private const CHARS_PER_TOKEN = 3.6;

    /** Cadangan token untuk teks catatan yang ditempel di akhir system prompt. */
    private const NOTE_RESERVE = 40;

    /** Penanda bagian data pada system prompt, urut dari yang paling boleh dipotong. */
    private const DATA_MARKERS = [
        "\n[DATA AKADEMIK]",
        "\n[HASIL WEB SEARCH]",
        "\n[ISI FILE]",
        "\n[MODUL PANDUAN]",
        "\n[PANDUAN SKILL]",
    ];

    public function __construct(
        private int $maxPromptTokens = 6000,
        private int $reservedOutput = 1024,
    ) {
        $this->maxPromptTokens = max(500, $this->maxPromptTokens);
        $this->reservedOutput  = max(64, $this->reservedOutput);
    }

    /** Estimasi jumlah token sebuah teks. */
    public static function estimateTokens(string $text): int
    {
        return max(1, (int) ceil(mb_strlen($text) / self::CHARS_PER_TOKEN));
    }

    /** Anggaran output yang disarankan untuk parameter max_tokens/num_predict. */
    public function reservedOutput(): int
    {
        return $this->reservedOutput;
    }

    /**
     * @param string                 $systemPrompt   system prompt lengkap
     * @param array<int, array>      $history        riwayat dari DB (idealnya TANPA pesan saat ini)
     * @param string                 $currentMessage pesan user yang sedang dijawab
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function build(string $systemPrompt, array $history, string $currentMessage): array
    {
        $budget = $this->maxPromptTokens;

        // --- 1. Pesan saat ini: prioritas mutlak, selalu utuh ---
        $budget -= self::estimateTokens($currentMessage);

        // --- 2. System prompt: prioritas kedua (membawa data grounding) ---
        $sysTokens     = self::estimateTokens($systemPrompt);
        $truncatedData = false;

        if ($sysTokens > $budget) {
            $systemPrompt  = $this->truncateSystem($systemPrompt, max(800, $budget));
            $sysTokens     = self::estimateTokens($systemPrompt);
            $truncatedData = true;
        }
        $budget -= $sysTokens;

        // --- 3. Riwayat: isi sisa anggaran, dari TERBARU ke terlama ---
        $clean = self::sanitizeHistory($history, $currentMessage);
        $kept  = [];
        $used  = 0;

        foreach (array_reverse($clean) as $m) {
            $t = self::estimateTokens($m['content']);
            if ($used + $t > $budget) {
                break;              // berhenti utuh; jangan potong setengah pesan
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
                . ($dropped > 0 ? "- Sekitar {$dropped} pesan lama TIDAK disertakan karena batas konteks.\n" : '')
                . ($truncatedData ? "- Sebagian data referensi dipotong.\n" : '')
                . '- Bila user merujuk hal yang tidak ada di riwayat maupun data di atas, '
                . 'TANYA KEMBALI. JANGAN mengarang atau menebak.';

            // CLAMP: teks catatan juga memakan token. Buang riwayat tertua
            // sampai system + note + riwayat + current benar-benar muat.
            $fixed = self::estimateTokens($systemPrompt)
                   + self::estimateTokens($note)
                   + self::estimateTokens($currentMessage);

            $histTokens = 0;
            foreach ($kept as $m) {
                $histTokens += self::estimateTokens($m['content']);
            }

            while ($kept !== [] && $fixed + $histTokens > $this->maxPromptTokens) {
                $removed     = array_shift($kept);
                $histTokens -= self::estimateTokens($removed['content']);
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
     * Bersihkan riwayat sebelum dimasukkan ke prompt.
     *
     * - buang baris non-percakapan ('system', 'Session started')
     * - buang pesan kosong
     * - buang duplikat pesan saat ini (pengaman bila pemanggil terlanjur
     *   menyimpan pesan ke DB sebelum merakit konteks — bug lama)
     *
     * @param array<int, array> $history
     * @return array<int, array{role: string, content: string}>
     */
    public static function sanitizeHistory(array $history, string $currentMessage): array
    {
        $out     = [];
        $lastIdx = count($history) - 1;
        $needle  = trim($currentMessage);

        foreach ($history as $i => $m) {
            if (! is_array($m)) {
                continue;
            }

            $role = (string) ($m['role'] ?? '');
            if ($role !== 'user' && $role !== 'assistant') {
                continue;
            }

            $content = trim((string) ($m['content'] ?? ''));
            if ($content === '') {
                continue;
            }

            // Dedupe: pesan yang sama di posisi terakhir = pesan saat ini
            if ($i === $lastIdx && $role === 'user' && $needle !== '' && $content === $needle) {
                continue;
            }

            $out[] = ['role' => $role, 'content' => $content];
        }

        return $out;
    }

    /**
     * Potong system prompt yang kebesaran.
     *
     * Bagian ATURAN (di atas penanda data pertama) selalu dipertahankan utuh;
     * sisa anggaran dipakai memuat data sebanyak mungkin, bukan dibuang semua.
     */
    private function truncateSystem(string $system, int $budget): string
    {
        $cutAt = mb_strlen($system);

        foreach (self::DATA_MARKERS as $marker) {
            $pos = mb_strpos($system, $marker);
            if ($pos !== false && $pos < $cutAt) {
                $cutAt = $pos;
            }
        }

        $allowChars = (int) (max(0, $budget - self::NOTE_RESERVE) * self::CHARS_PER_TOKEN);
        $rules      = mb_substr($system, 0, $cutAt);

        // Bahkan bagian aturan pun kebesaran
        if (mb_strlen($rules) >= $allowChars) {
            return mb_substr($rules, 0, max(0, $allowChars))
                 . "\n[CATATAN] Konteks dipotong karena melebihi batas.";
        }

        // Sisa ruang dipakai memuat sebagian data — jauh lebih baik daripada nol.
        $room = $allowChars - mb_strlen($rules);

        return $rules . mb_substr($system, $cutAt, $room)
             . "\n[CATATAN] Data referensi terlalu besar, sebagian dihilangkan.";
    }
}
