<?php

namespace App\Http\Controllers\Api\V1;

use App\Agents\PortfolioAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChatRequest;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Messages\Message;
use Symfony\Component\HttpFoundation\Response;

class ChatController extends Controller
{
    public function __invoke(ChatRequest $request): Response
    {
        $userId = Cache::rememberForever('chat.user_id', function () {
            return User::where('email', config('chat.user.email'))->value('id');
        });
        $conversationId = $request->validated('conversation_id');
        $isNew = ! $conversationId;

        if ($isNew) {
            $conversationId = Str::uuid7()->toString();
        }

        try {
            if ($isNew) {
                DB::table('agent_conversations')->insert([
                    'id' => $conversationId,
                    'user_id' => $userId,
                    'title' => 'Chat',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $messages = DB::table('agent_conversation_messages')
                ->where('conversation_id', $conversationId)
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->reverse()
                ->values()
                ->map(fn ($m) => new Message($m->role, $m->content));

            $agent = new PortfolioAgent($messages->all());

            $userMessage = $request->string('message')->toString();
            $response = $agent->stream($userMessage);

            $response->then(function ($streamed) use ($conversationId, $userId, $userMessage) {
                try {
                    DB::table('agent_conversation_messages')->insert([
                        'id' => Str::uuid7()->toString(),
                        'conversation_id' => $conversationId,
                        'user_id' => $userId,
                        'agent' => PortfolioAgent::class,
                        'role' => 'user',
                        'content' => $userMessage,
                        'attachments' => '[]',
                        'tool_calls' => '[]',
                        'tool_results' => '[]',
                        'usage' => '[]',
                        'meta' => '[]',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('agent_conversation_messages')->insert([
                        'id' => Str::uuid7()->toString(),
                        'conversation_id' => $conversationId,
                        'user_id' => $userId,
                        'agent' => PortfolioAgent::class,
                        'role' => 'assistant',
                        'content' => $streamed->text,
                        'attachments' => '[]',
                        'tool_calls' => json_encode($streamed->toolCalls),
                        'tool_results' => json_encode($streamed->toolResults),
                        'usage' => json_encode($streamed->usage),
                        'meta' => json_encode($streamed->meta),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    Log::error('ChatController: failed to persist conversation messages', [
                        'conversation_id' => $conversationId,
                        'exception' => $e->getMessage(),
                    ]);
                }
            });

            $httpResponse = $response->toResponse($request);
            $httpResponse->headers->set('X-Conversation-Id', $conversationId);

            return $httpResponse;
        } catch (\Throwable $e) {
            Log::error('ChatController: agent streaming failed', [
                'conversation_id' => $conversationId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
