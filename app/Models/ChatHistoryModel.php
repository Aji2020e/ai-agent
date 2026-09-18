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

    public function getSessionMessages(string $sessionId, int $userId): array
    {
        return $this->where('session_id', $sessionId)
                    ->where('user_id', $userId)
                    ->orderBy('id', 'ASC')
                    ->findAll();
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
