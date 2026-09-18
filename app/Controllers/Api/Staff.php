<?php

namespace App\Controllers\Api;

use App\Libraries\Academic\StaffAssistant;

/** POST /api/staff/analyze {nik|id, reason|pertanyaan} */
class Staff extends BaseApi
{
    public function analyze()
    {
        return $this->runModule('staff', StaffAssistant::class, [
            'nik'        => $this->param('nik', 'nidn', 'id'),
            'pertanyaan' => $this->reasonParam(),
        ]);
    }
}
