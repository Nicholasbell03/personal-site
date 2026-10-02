<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Messages\Message;

/**
 * Reads for the anonymous portfolio chat. Writes live in StartChatConversation and RecordChatExchange.
 */
class ChatConversationService
{
    private const USER_ID_CACHE_KEY = 'chat.user_id';

    /**
     * The id of the chatbot user that owns every anonymous conversation (created by ChatbotUserSeeder).
     * Cached forever once found; a miss isn't cached, so seeding the user fixes chat without a cache flush.
     */
    public function chatbotUserId(): ?int
    {
        $userId = Cache::rememberForever(self::USER_ID_CACHE_KEY, function () {
            return User::where('email', config('chat.user.email'))->value('id');
        });

        if (! $userId) {
            Cache::forget(self::USER_ID_CACHE_KEY);

            return null;
        }

        return $userId;
    }

    /**
     * The most recent messages in a conversation, oldest first, as agent history.
     *
     * @return list<Message>
     */
    public function recentMessages(string $conversationId, ?int $limit = null): array
    {
        $limit ??= config('agent.portfolio.history_messages');

        return DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($m) => new Message($m->role, $m->content))
            ->all();
    }

    /**
     * How many messages the visitor has sent in this conversation.
     */
    public function visitorTurnCount(string $conversationId): int
    {
        return DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->where('role', 'user')
            ->count();
    }
}
