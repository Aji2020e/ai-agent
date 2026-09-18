<?php

namespace App\Libraries\Tools;

class FileReaderTool implements ToolInterface
{
    public function getName(): string
    {
        return 'file_reader';
    }

    public function getDescription(): string
    {
        return 'Membaca konten file PDF, DOCX, TXT, MD.';
    }

    public function run(array $params): ToolResult
    {
        $filePath = $params['file_path'] ?? '';
        $maxChars = $params['max_chars'] ?? 8000;

        if (empty($filePath) || ! file_exists($filePath)) {
            return new ToolResult(false, null, 'File tidak ditemukan.');
        }

        try {
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $text = '';

            if ($ext === 'pdf') {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($filePath);
                $text = trim(preg_replace('/\s+/', ' ', $pdf->getText() ?? ''));
            } elseif ($ext === 'docx') {
                $text = $this->readDocx($filePath);
            } else {
                $raw = @file_get_contents($filePath);
                if ($raw === false) {
                    return new ToolResult(false, null, 'Gagal membaca file.');
                }
                $text = $raw;
            }

            $total = mb_strlen($text);
            if ($total > $maxChars) {
                $text = mb_substr($text, 0, $maxChars) . "\n[...file dipotong, total {$total} karakter...]";
            }

            return new ToolResult(true, $text, null, 'Konten file:');
        } catch (\Throwable $e) {
            return new ToolResult(false, null, $e->getMessage(), 'Gagal membaca file.');
        }
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
        $dom->loadXML($xml);

        $text = '';
        foreach ($dom->getElementsByTagName('t') as $node) {
            $text .= $node->nodeValue;
        }

        return $text;
    }
}
