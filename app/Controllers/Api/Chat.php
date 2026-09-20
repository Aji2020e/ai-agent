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
        // PERBAIKAN H2: peran dari kebijakan klien, bukan dari request.
        $role   = $this->resolvedRole();
        $reason = $this->reasonParam();

        if ($reason === null || mb_strlen($reason) > 2000) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'error'   => "Parameter 'reason' wajib (maks 2000 karakter).",
            ]);
        }

        $small = $this->smallTalkReply($reason);
        if ($small !== null) {
            $this->log($client ? (int) $client['id'] : null, '-', 'chat', 0);

            return $this->response->setJSON([
                'success'  => true,
                'mode'     => 'obrolan',
                'analysis' => $small,
                'tokens'   => 0,
            ]);
        }

        try {
            [$provider, $url, $model, $opt] = AiClient::currentConfig();
            $model = AiClient::clientModel($client, $model);
            $reply = AiClient::chat($provider, $url, $model, \App\Libraries\WebSearch::withWebContext([
                ['role' => 'system', 'content' => \App\Libraries\PromptBuilder::general(
                    'asisten kampus yang ramah. Jawab obrolan umum tanpa membuka data akademik internal; maksimal 200 kata'
                )],
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

    private function smallTalkReply(string $question): ?string
    {
        $q = mb_strtolower(trim($question));
        if ($q === '') {
            return null;
        }

        if (str_contains($q, 'terima kasih') || str_contains($q, 'makasih') || str_contains($q, 'thanks')) {
            return 'Sama-sama. Kalau ada yang ingin ditanyakan, tinggal tulis saja.';
        }
        if (str_contains($q, 'selamat pagi') || $q === 'pagi') {
            return 'Selamat pagi juga. Semoga harimu lancar. Mau bahas apa dulu?';
        }
        if (str_contains($q, 'selamat siang') || $q === 'siang') {
            return 'Selamat siang juga. Siap, saya bantu sesuai pertanyaanmu.';
        }
        if (str_contains($q, 'selamat sore') || $q === 'sore') {
            return 'Selamat sore juga. Lanjut, pertanyaanmu apa?';
        }
        if (str_contains($q, 'selamat malam') || $q === 'malam') {
            return 'Selamat malam juga. Kalau ada yang ingin ditanyakan, langsung saja.';
        }
        if (str_contains($q, 'apa kabar')) {
            return 'Baik, terima kasih. Semoga kamu juga baik. Ada yang ingin kamu bahas?';
        }
        if ($q === 'halo' || $q === 'hai' || $q === 'hallo') {
            return 'Halo. Saya siap bantu sesuai pertanyaanmu.';
        }
        if ($q === 'ok' || $q === 'oke' || $q === 'sip') {
            return 'Siap. Lanjut saja, saya ikuti pertanyaanmu.';
        }

        return null;
    }
}
