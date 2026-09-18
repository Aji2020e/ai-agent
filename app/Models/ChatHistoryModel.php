<?php

namespace App\Models;

use CodeIgniter\Model;

class ChatHistoryModel extends Model
{
    protected $table            = 'chat_history';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id', 'session_id', 'title', 'role', 'content', 'tokens_used',
        'attachment_name', 'attachment_path',
    ];
    protected $useTimestamps = false;
    protected $createdField  = 'created_at';

    public function getUserSessions(int $userId): array
    {
        // Satu baris per sesi: judul diambil dari baris sistem (baris pesan lain title-nya NULL).
        // Group by session_id SAJA — group by (session_id, title) memecah satu sesi
        // menjadi dua entri ("judul asli" + "Chat Baru").
        return $this->select('session_id, MAX(title) as title, MAX(created_at) as last_message, COUNT(*) as message_count')
                    ->where('user_id', $userId)
                    ->groupBy('session_id')
                    ->orderBy('last_message', 'DESC')
                    ->findAll();
    }

    /**
     * Pesan sebuah sesi, urut kronologis.
     *
     * PERBAIKAN B2: sebelumnya findAll() tanpa batas, sehingga sesi panjang
     * membuat prompt melewati context window provider. Provider lalu memotong
     * dari DEPAN — yang pertama terbuang adalah system prompt berisi data
     * akademik, dan model menjawab tanpa data.
     *
     * @param int $limit 0 = tanpa batas (untuk menampilkan riwayat di UI).
     *                   Untuk konteks AI, selalu isi dengan angka.
     */
    public function getSessionMessages(string $sessionId, int $userId, int $limit = 0): array
    {
        if ($limit > 0) {
            // Ambil N TERBARU lebih dulu, lalu balikkan ke urutan kronologis.
            $rows = $this->where('session_id', $sessionId)
                         ->where('user_id', $userId)
                         ->orderBy('id', 'DESC')
                         ->limit($limit)
                         ->findAll();

            return array_reverse($rows);
        }

        return $this->where('session_id', $sessionId)
                    ->where('user_id', $userId)
                    ->orderBy('id', 'ASC')
                    ->findAll();
    }

    /**
     * Cek kepemilikan sesi tanpa menarik seluruh riwayat.
     *
     * Sebelumnya Chat::send() memanggil getSessionMessages() hanya untuk
     * memastikan sesi milik user — mengambil seluruh percakapan demi satu
     * boolean. Pada sesi panjang itu query yang sangat boros.
     */
    public function sessionExists(string $sessionId, int $userId): bool
    {
        return $this->select('id')
                    ->where('session_id', $sessionId)
                    ->where('user_id', $userId)
                    ->limit(1)
                    ->first() !== null;
    }

    public function deleteSession(string $sessionId, int $userId): bool
    {
        return $this->where('session_id', $sessionId)
                    ->where('user_id', $userId)
                    ->delete();
    }

    public function renameSession(string $sessionId, int $userId, string $title): bool
    {
        return $this->where('session_id', $sessionId)
                    ->where('user_id', $userId)
                    ->set(['title' => $title])
                    ->update();
    }

    public function getUserMessageCount(int $userId): int
    {
        return $this->where('user_id', $userId)
                    ->where('role', 'user')
                    ->countAllResults();
    }

    public function getTotalTokensUsed(int $userId): int
    {
        $result = $this->selectSum('tokens_used')
                       ->where('user_id', $userId)
                       ->first();
        return (int) ($result['tokens_used'] ?? 0);
    }

    public function saveMessage(array $data): int|string|false
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->insert($data);
        return $this->getInsertID();
    }
}
