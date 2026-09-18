<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAttachmentsToChatHistory extends Migration
{
    public function up()
    {
        $this->forge->addColumn('chat_history', [
            'attachment_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'tokens_used',
            ],
            'attachment_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
                'after'      => 'attachment_name',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('chat_history', ['attachment_path', 'attachment_name']);
    }
}
