<?php

namespace App\Controllers\Api;

use App\Libraries\AcademicDb;
use App\Libraries\AiClient;

/** GET /api/status — kesehatan integrasi (butuh API key). */
class Status extends BaseApi
{
    public function index()
    {
        $client = $this->client();

        [$provider, $url, $model] = AiClient::currentConfig();

        try {
            $akad = AcademicDb::test();
            $akadOk = true;
        } catch (\Throwable $e) {
            $akadOk = false;
            $akad   = ['error' => $e->getMessage()];
        }

        $this->log($client ? (int) $client['id'] : null, '-', 'status');

        return $this->response->setJSON([
            'success'  => true,
            'time'     => date('c'),
            'provider' => ['type' => $provider, 'model' => $model],
            'akademik' => array_merge(['connected' => $akadOk], $akad),
        ]);
    }
}
