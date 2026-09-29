<?php

namespace App\Actions;

use Illuminate\Support\Facades\DB;

class StartChatConversation
{
    /**
     * Record a new anonymous conversation. The caller generates the id so it can be returned to the
     * client even if this insert fails.
     */
    public function execute(string $conversationId, int $userId, ?string $ipAddress): void
    {
        DB::table('agent_conversations')->insert([
            'id' => $conversationId,
            'user_id' => $userId,
            'title' => 'Chat',
            'ip_address' => $ipAddress,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
