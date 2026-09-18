<?php

namespace App\Libraries\Tools;

use App\Libraries\Auth\PolicyGuard;

/**
 * Pembaca dokumen (PDF/DOCX/TXT/MD) yang dibatasi direktori.
 *
 * PERBAIKAN H4b: sebelumnya tool ini memanggil file_get_contents() langsung
 * pada path apa pun yang diberikan, sehingga bisa membaca `.env` (kredensial
 * DB akademik + encryption.key) atau berkas sistem lain. Karena tool ini
 * dapat dipicu dari teks pengguna biasa, itu sama dengan arbitrary file
 * disclosure via prompt injection.
 *
 * Sekarang: path diselesaikan dengan realpath() (menghapus `..` dan symlink)
 * lalu WAJIB berada di dalam salah satu direktori yang diizinkan.
 */
class FileReaderTool implements ToolInterface
{
    /** Batas atas karakter yang dikembalikan, apa pun permintaan AI. */
    private const MAX_CHARS_CEILING = 20000;

    /** Ekstensi yang boleh dibaca. Selain ini ditolak. */
    private const ALLOWED_EXT = ['pdf', 'docx', 'txt', 'md', 'markdown', 'csv', 'json', 'log'];

    public function getName(): string
    {
        return 'file_reader';
    }

    public function getDescription(): string
    {
        return 'Membaca konten file PDF, DOCX, TXT, MD dari direktori yang diizinkan.';
    }

    /**
     * Direktori yang boleh dibaca. Hanya tempat unggahan & dokumen panduan.
     * Sengaja TIDAK mencakup ROOTPATH (akar proyek) — di sana ada `.env`.
     *
     * @return string[]
     */
    public static function allowedRoots(): array
    {
        $roots = [];

        if (defined('WRITEPATH')) {
            $roots[] = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR;
            $roots[] = WRITEPATH . 'docs' . DIRECTORY_SEPARATOR;
        }
        if (defined('ROOTPATH')) {
            $roots[] = ROOTPATH . 'storage' . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR;
        }

        return $roots;
    }

    public function run(array $params): ToolResult
    {
        $filePath = trim((string) ($params['file_path'] ?? ''));

        $maxChars = (int) ($params['max_chars'] ?? 8000);
        $maxChars = max(500, min($maxChars, self::MAX_CHARS_CEILING));

        if ($filePath === '') {
            return new ToolResult(false, null, 'Path file tidak diberikan.');
        }

        // ---- Penahanan path (containment) ----
        // realpath() menyelesaikan `..`, symlink, dan path relatif menjadi
        // bentuk kanonik, sehingga perbandingan prefix tidak bisa diakali.
        $real = realpath($filePath);

        if ($real === false || ! is_file($real)) {
            return new ToolResult(false, null, 'File tidak ditemukan.');
        }

        if (! $this->insideAllowedRoot($real)) {
            // Catat untuk audit, tapi JANGAN sebutkan path yang dicoba di
            // pesan balik — itu membocorkan struktur direktori server.
            $this->logViolation($filePath);

            return new ToolResult(false, null, 'Akses file di luar direktori yang diizinkan.');
        }

        // ---- Batasi ekstensi ----
        $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
        if (! in_array($ext, self::ALLOWED_EXT, true)) {
            return new ToolResult(false, null, 'Jenis file tidak didukung.');
        }

        try {
            // Semua pembacaan memakai $real (hasil realpath), bukan $filePath.
            $text = match ($ext) {
                'pdf'  => $this->readPdf($real),
                'docx' => $this->readDocx($real),
                default => $this->readPlain($real),
            };
        } catch (\Throwable) {
            // Jangan teruskan pesan exception mentah: bisa memuat path server.
            return new ToolResult(false, null, 'Gagal membaca file.');
        }

        if ($text === '') {
            return new ToolResult(false, null, 'File kosong atau tidak mengandung teks.');
        }

        $total = mb_strlen($text);
        if ($total > $maxChars) {
            $text = mb_substr($text, 0, $maxChars) . "\n[...file dipotong, total {$total} karakter...]";
        }

        return new ToolResult(true, $text, null, 'Konten file:');
    }

    /** Apakah path kanonik berada di dalam salah satu akar yang diizinkan? */
    private function insideAllowedRoot(string $realPath): bool
    {
        foreach (self::allowedRoots() as $root) {
            $rootReal = realpath($root);
            if ($rootReal === false) {
                continue;   // direktori belum ada
            }
            // Wajib pakai pemisah di akhir, agar "/x/uploads-evil" tidak lolos
            // sebagai anak dari "/x/uploads".
            if (str_starts_with($realPath, rtrim($rootReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    private function logViolation(string $attempted): void
    {
        try {
            (new \App\Models\AuthViolationModel())->record([
                'client_id'  => PolicyGuard::policy()?->clientId,
                'violation'  => 'file_outside_root',
                'module'     => '-',
                // Hanya nama berkasnya, bukan path lengkap — cukup untuk audit
                // tanpa menuliskan struktur direktori ke dalam tabel log.
                'attempted'  => basename($attempted),
                'subject_id' => PolicyGuard::subjectId(),
            ]);
        } catch (\Throwable) {
            // Audit tidak boleh memutus request
        }
    }

    private function readPdf(string $path): string
    {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf    = $parser->parseFile($path);

        return trim((string) preg_replace('/\s+/', ' ', (string) $pdf->getText()));
    }

    private function readPlain(string $path): string
    {
        $raw = @file_get_contents($path);

        if ($raw === false) {
            throw new \RuntimeException('Gagal membaca file.');
        }

        // Buang byte NUL & kendali yang bisa mengacaukan prompt
        return trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $raw));
    }

    protected function readDocx(string $path): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Gagal membuka file DOCX.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! $xml) {
            throw new \RuntimeException('Konten DOCX tidak ditemukan.');
        }

        $dom = new \DOMDocument();
        // Cegah XXE: jangan muat entity eksternal maupun DTD dari jaringan.
        $prev                  = libxml_use_internal_errors(true);
        $dom->substituteEntities = false;
        $dom->resolveExternals   = false;
        $ok                    = $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (! $ok) {
            throw new \RuntimeException('XML DOCX tidak valid.');
        }

        $text = '';
        foreach ($dom->getElementsByTagName('t') as $node) {
            $text .= $node->nodeValue;
        }

        return $text;
    }
}
