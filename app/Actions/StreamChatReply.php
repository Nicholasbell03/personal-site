<?php

namespace App\Actions;

use App\Agents\PortfolioAgent;
use App\Exceptions\ChatUnavailableException;
use App\Services\ChatConversationService;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Responses\StreamedAgentResponse;

class StreamChatReply
{
    public function __construct(
        private ChatConversationService $conversations,
        private StartChatConversation $startConversation,
        private RecordChatExchange $recordExchange,
    ) {}

    /**
     * Start or continue an anonymous conversation and begin streaming the agent's reply. Once the stream
     * completes, the exchange is recorded; a failure to record is logged and never breaks the reply.
     *
     * @throws ChatUnavailableException when the chatbot user hasn't been seeded
     */
    public function execute(string $conversationId, bool $isNewConversation, string $message, ?string $ipAddress): StreamableAgentResponse
    {
        $userId = $this->conversations->chatbotUserId() ?? throw new ChatUnavailableException;

        if ($isNewConversation) {
            $this->startConversation->execute($conversationId, $userId, $ipAddress);
        }

        $agent = new PortfolioAgent($this->conversations->recentMessages($conversationId));

        return $agent->stream($message)->then(function (StreamedAgentResponse $reply) use ($conversationId, $userId, $message) {
            try {
                $this->recordExchange->execute($conversationId, $userId, $message, $reply);
            } catch (\Throwable $e) {
                Log::error('ChatController: failed to persist conversation messages', [
                    'conversation_id' => $conversationId,
                    'exception' => $e->getMessage(),
                ]);
            }
        });
    }
}
