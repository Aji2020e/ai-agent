<?php

namespace App\Controllers\Api;

use App\Libraries\AiClient;

/**
 * POST /api/chat — obrolan umum TANPA data akademik.
 * Selalu boleh selama key valid (tidak butuh modul aktif).
 */
class Chat extends BaseApi
{
    public function send()
    {
        $client = $this->client();
        $role   = $this->roleParam();
        $reason = $this->reasonParam();

        if ($reason === null || mb_strlen($reason) > 2000) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'error'   => "Parameter 'reason' wajib (maks 2000 karakter).",
            ]);
        }

        try {
            [$provider, $url, $model, $opt] = AiClient::currentConfig();
            $model = AiClient::clientModel($client, $model);
            $reply = AiClient::chat($provider, $url, $model, \App\Libraries\WebSearch::withWebContext([
                ['role' => 'system', 'content' => 'Kamu asisten kampus yang ramah. Jawab umum tanpa data akademik internal. Bahasa Indonesia santai, tidak kaku, maksimal 200 kata.'],
                ['role' => 'user', 'content' => $reason],
            ], $reason), $opt);
        } catch (\RuntimeException $e) {
            $this->log($client ? (int) $client['id'] : null, '-', 'chat', 0, 'error');

            return $this->response->setStatusCode(502)->setJSON(['success' => false, 'error' => $e->getMessage()]);
        }

        $this->log($client ? (int) $client['id'] : null, '-', 'chat', (int) $reply['tokens']);

        return $this->response->setJSON([
            'success'  => true,
            'mode'     => 'obrolan',
            'analysis' => $reply['content'],
            'tokens'   => (int) $reply['tokens'],
        ]);
    }
}
