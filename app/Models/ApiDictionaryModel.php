<?php

namespace App\Models;

use CodeIgniter\Model;

class ApiDictionaryModel extends Model
{
    protected $table            = 'api_dictionary';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['module', 'topik', 'tabel', 'kolom', 'deskripsi', 'is_active'];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Ambil entri kamus yang relevan: topik '*' selalu ikut,
     * sisanya bila salah satu kata kunci topik muncul di pertanyaan.
     */
    public function forQuestion(string $module, string $question, int $maxChars = 4000): array
    {
        $rows = $this->where('is_active', 1)
                     ->whereIn('module', ['umum', $module])
                     ->orderBy('id', 'ASC')
                     ->findAll();

        $q   = mb_strtolower($question);
        $out = [];

        foreach ($rows as $row) {
            $topik = trim((string) $row['topik']);

            if ($topik !== '*') {
                $hit = false;
                foreach (explode(',', $topik) as $kw) {
                    $kw = trim(mb_strtolower($kw));
                    if ($kw !== '' && mb_strpos($q, $kw) !== false) {
                        $hit = true;
                        break;
                    }
                }
                if (! $hit) {
                    continue;
                }
            }

            $out[] = $row;
        }

        // Budget karakter
        $text   = [];
        $budget = $maxChars;
        foreach ($out as $row) {
            $chunk = '- ' . ($row['tabel'] ? '[' . $row['tabel'] . '] ' : '') . ($row['kolom'] ? $row['kolom'] . '. ' : '') . ($row['deskripsi'] ?? '');
            if ($budget <= 0) {
                break;
            }
            $chunk  = mb_substr($chunk, 0, $budget);
            $budget -= mb_strlen($chunk);
            $text[] = $chunk;
        }

        return $text;
    }
}
