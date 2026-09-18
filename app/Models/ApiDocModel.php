<?php

namespace App\Models;

use CodeIgniter\Model;

class ApiDocModel extends Model
{
    protected $table            = 'api_docs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['module', 'title', 'content', 'is_active'];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public static array $modules = ['umum', 'mahasiswa', 'dosen', 'staff'];

    /** Cache per-request agar docs() + docsList() tidak query 2x. */
    private static array $cache = [];

    /** Dokumen aktif untuk modul (+ 'umum'), dipotong agar hemat token. */
    public function forModule(string $module, int $maxChars = 6000): array
    {
        $key = $module . ':' . $maxChars;

        if (! isset(self::$cache[$key])) {
            self::$cache[$key] = $this->where('is_active', 1)
                         ->whereIn('module', ['umum', $module])
                         ->orderBy('id', 'ASC')
                         ->findAll();
        }

        $rows = self::$cache[$key];

        $out     = [];
        $budget  = $maxChars;
        foreach ($rows as $row) {
            if ($budget <= 0) {
                break;
            }
            $text    = mb_substr($row['content'], 0, $budget);
            $budget -= mb_strlen($text);
            $out[]   = ['title' => $row['title'], 'content' => $text];
        }

        return $out;
    }
}
