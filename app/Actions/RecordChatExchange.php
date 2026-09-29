<?php

namespace App\Actions;

use App\Agents\PortfolioAgent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StreamedAgentResponse;

class RecordChatExchange
{
    /**
     * Store a user message and the agent's streamed reply in the conversation history.
     */
    public function execute(string $conversationId, int $userId, string $userMessage, StreamedAgentResponse $reply): void
    {
        DB::table('agent_conversation_messages')->insert(
            $this->row($conversationId, $userId, 'user', $userMessage)
        );

        DB::table('agent_conversation_messages')->insert(
            $this->row($conversationId, $userId, 'assistant', $reply->text, [
                'tool_calls' => json_encode($reply->toolCalls),
                'tool_results' => json_encode($reply->toolResults),
                'usage' => json_encode($reply->usage),
                'meta' => json_encode($reply->meta),
            ])
        );
    }

    /**
     * @param  array<string, string|false>  $json  JSON-encoded columns that differ from the empty default.
     * @return array<string, mixed>
     */
    private function row(string $conversationId, int $userId, string $role, string $content, array $json = []): array
    {
        return [
            'id' => Str::uuid7()->toString(),
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'agent' => PortfolioAgent::class,
            'role' => $role,
            'content' => $content,
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
            ...$json,
        ];
    }
}
