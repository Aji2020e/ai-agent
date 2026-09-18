<?php

namespace App\Libraries\Academic;

/**
 * Bedah pertanyaan per kata: ID, frasa nama, kata kunci.
 * Hasil: ['ids'=>[], 'names'=>[], 'keywords'=>[], 'tafsir'=>'...'].
 */
class QueryParser
{
    public static function parse(string $question): array
    {
        $ids   = [];
        $names = [];

        // Token mirip ID (NIM/NIK): alnum bertitik/garis, min 4 char
        preg_match_all('/[A-Za-z0-9][A-Za-z0-9.\-_\/]{3,}/', $question, $m);
        foreach (array_unique($m[0]) as $tok) {
            // Lewati kata biasa tanpa digit/titik
            if (preg_match('/[0-9]/', $tok) || str_contains($tok, '.')) {
                $ids[] = $tok;
            }
        }
        $ids = array_values(array_slice($ids, 0, 6));

        // Frasa nama: setelah "nama"/"atas nama", atau dalam kutip
        if (preg_match_all('/(?:atas\s+nama|nama)\s+([a-zA-Z. ]+)/i', $question, $mm)) {
            foreach ($mm[1] as $ph) {
                $ph = trim($ph);
                if (mb_strlen($ph) >= 4) {
                    $names[] = $ph;
                }
            }
        }
        if (preg_match_all('/"([^"]{3,})"/', $question, $mm)) {
            foreach ($mm[1] as $ph) {
                $names[] = trim($ph);
            }
        }
        $names = array_values(array_unique(array_slice($names, 0, 3)));

        // Kata bermakna untuk skor topik
        $words = array_unique(preg_split('/\s+/', mb_strtolower($question)));
        $words = array_values(array_filter($words, static fn ($w) => mb_strlen($w) > 3));

        $tafsir = [];
        if ($ids !== []) {
            $tafsir[] = 'ID disebut: ' . implode(', ', $ids);
        }
        if ($names !== []) {
            $tafsir[] = 'Nama disebut: ' . implode(', ', $names);
        }

        return ['ids' => $ids, 'names' => $names, 'keywords' => $words, 'tafsir' => implode(' | ', $tafsir)];
    }
}
