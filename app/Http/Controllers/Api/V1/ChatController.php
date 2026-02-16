<?php

namespace App\Http\Controllers\Api\V1;

use App\Agents\PortfolioAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChatRequest;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Messages\Message;
use Symfony\Component\HttpFoundation\Response;

class ChatController extends Controller
{
    public function __invoke(ChatRequest $request): Response
    {
        $userId = Cache::rememberForever('chat.user_id', function () {
            return User::where('email', config('chat.user.email'))->value('id');
        });

        if (! $userId) {
            Cache::forget('chat.user_id');

            Log::error('ChatController: chatbot user not found — run ChatbotUserSeeder', [
                'email' => config('chat.user.email'),
            ]);
            abort(500, 'Chat service is unavailable.');
        }

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
        } catch (ConnectionException|RateLimitedException $e) {
            Log::warning('ChatController: AI provider unavailable', [
                'conversation_id' => $conversationId,
                'exception' => $e->getMessage(),
            ]);

            return $this->sseError(
                $e instanceof RateLimitedException
                    ? 'The AI service is currently rate limited. Please try again in a moment.'
                    : 'The AI service is temporarily unavailable. Please try again shortly.',
                $conversationId,
            );
        } catch (\Throwable $e) {
            Log::error('ChatController: agent streaming failed', [
                'conversation_id' => $conversationId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->sseError(
                'Something went wrong. Please try again.',
                $conversationId,
            );
        }
    }

    /**
     * Return an SSE-formatted error response so the frontend can display
     * a meaningful message instead of hanging or showing a generic 500.
     */
    private function sseError(string $message, string $conversationId): Response
    {
        return response()->stream(function () use ($message) {
            $event = json_encode(['type' => 'error', 'message' => $message]);
            echo "data: {$event}\n\n";
            echo "data: [DONE]\n\n";

            if (ob_get_level()) {
                ob_flush();
            }
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'X-Conversation-Id' => $conversationId,
        ]);
    }
}
