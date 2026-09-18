<?php

namespace App\Models;

use CodeIgniter\Model;

class ChatContextModel extends Model
{
    protected $table            = 'chat_context';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['session_id', 'user_id', 'context'];
    protected $useTimestamps = true;

    public function getContext(string $sessionId, int $userId): array
    {
        $row = $this->where('session_id', $sessionId)
                    ->where('user_id', $userId)
                    ->first();

        if (! $row) {
            return [
                'user_type'    => null,
                'identifier'   => null,
                'profile_data' => null,
                'last_topic'   => null,
                'last_intent'  => null,
            ];
        }

        $context = json_decode($row['context'] ?? '{}', true);
        $context['id'] = $row['id'];

        return $context;
    }

    public function saveContext(string $sessionId, int $userId, array $context): bool
    {
        $existing = $this->where('session_id', $sessionId)
                         ->where('user_id', $userId)
                         ->first();

        $data = ['context' => json_encode($context)];

        if ($existing) {
            return $this->update($existing['id'], $data);
        }

        $data['session_id'] = $sessionId;
        $data['user_id']    = $userId;

        return (bool) $this->insert($data);
    }
}
