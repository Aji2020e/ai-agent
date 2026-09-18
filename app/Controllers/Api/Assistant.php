<?php

namespace App\Controllers\Api;

use App\Libraries\Academic\DynamicAssistant;
use App\Models\ApiModuleModel;

/** POST /api/assistant/{slug}/analyze — modul dinamis dari wizard. */
class Assistant extends BaseApi
{
    public function analyze(string $slug = '')
    {
        $m   = new ApiModuleModel();
        $row = $m->findBySlug($slug);

        if ($row === null || empty($row['is_active'])) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'error'   => "Modul '{$slug}' tidak ditemukan atau nonaktif.",
            ]);
        }

        $client = $this->requireModule($slug);

        if ($client === null) {
            return $this->forbidden($slug);
        }

        $idParam = $row['id_param'] ?: 'id';

        try {
            $result = DynamicAssistant::analyzeModule($row, [
                $idParam        => $this->param($idParam, 'npm', 'nim', 'nik'),
                'pertanyaan'     => $this->reasonParam(),
                'tabel'          => $this->param('tabel', 'table'),
                'kolom'          => $this->param('kolom', 'columns'),
                'filter_kolom'   => $this->param('filter_kolom', 'filter_col'),
                'filter_nilai'   => $this->param('filter_nilai', 'filter_value'),
                'limit'          => $this->param('limit'),
            ]);
        } catch (\RuntimeException $e) {
            $this->log($client ? (int) $client['id'] : null, $slug, 'assistant/' . $slug . '/analyze', 0, 'error');

            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable) {
            $this->log($client ? (int) $client['id'] : null, $slug, 'assistant/' . $slug . '/analyze', 0, 'error');

            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => 'Kesalahan internal.']);
        }

        $this->log($client ? (int) $client['id'] : null, $slug, 'assistant/' . $slug . '/analyze', (int) ($result['tokens'] ?? 0));

        return $this->response->setJSON([
            'success'  => true,
            'module'   => $slug,
            'data'     => $result['data'],
            'analysis' => $result['analysis'],
            'tokens'   => $result['tokens'] ?? 0,
            'jejak'    => $result['jejak'] ?? [],
            'evidence' => $result['evidence'] ?? null,
            'confidence' => $result['confidence'] ?? 'sedang',
        ]);
    }
}
