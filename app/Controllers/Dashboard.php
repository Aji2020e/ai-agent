<?php

namespace App\Controllers;

use App\Models\ChatHistoryModel;
use App\Models\ProjectModel;
use App\Models\UserModel;

class Dashboard extends BaseController
{
    public function index()
    {
        $userId = session()->get('user_id');

        $chatModel    = new ChatHistoryModel();
        $projectModel = new ProjectModel();

        $data = [
            'title'           => 'Dashboard',
            'total_sessions'  => count($chatModel->getUserSessions($userId)),
            'total_messages'  => $chatModel->getUserMessageCount($userId),
            'total_tokens'    => $chatModel->getTotalTokensUsed($userId),
            'total_projects'  => $projectModel->getUserProjectCount($userId),
        ];

        return view('dashboard/index', $data);
    }
}
