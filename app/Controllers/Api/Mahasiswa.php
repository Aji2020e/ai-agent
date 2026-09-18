<?php

namespace App\Controllers\Api;

use App\Libraries\Academic\MahasiswaAssistant;

/** POST /api/mahasiswa/analyze {nim|npm|id, reason|pertanyaan} */
class Mahasiswa extends BaseApi
{
    public function analyze()
    {
        return $this->runModule('mahasiswa', MahasiswaAssistant::class, [
            'nim'        => $this->param('nim', 'npm', 'id'),
            'pertanyaan' => $this->reasonParam(),
        ]);
    }
}
