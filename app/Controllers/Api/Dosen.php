<?php

namespace App\Controllers\Api;

use App\Libraries\Academic\DosenAssistant;

/** POST /api/dosen/analyze {nik|nidn|id, reason|pertanyaan} */
class Dosen extends BaseApi
{
    public function analyze()
    {
        return $this->runModule('dosen', DosenAssistant::class, [
            'nik'        => $this->param('nik', 'nidn', 'id'),
            'pertanyaan' => $this->reasonParam(),
        ]);
    }
}
