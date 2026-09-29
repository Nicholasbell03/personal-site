<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RecordChatExchange;
use App\Actions\StartChatConversation;
use App\Agents\PortfolioAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChatRequest;
use App\Services\ChatConversationService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Symfony\Component\HttpFoundation\Response;

class ChatController extends Controller
{
    public function __construct(
        private ChatConversationService $conversations,
        private StartChatConversation $startConversation,
        private RecordChatExchange $recordExchange,
    ) {}

    public function __invoke(ChatRequest $request): Response
    {
        $userId = $this->conversations->chatbotUserId();

        if (! $userId) {
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
                $this->startConversation->execute($conversationId, $userId, $request->ip());
            }

            $agent = new PortfolioAgent($this->conversations->recentMessages($conversationId));

            $userMessage = $request->string('message')->toString();
            $response = $agent->stream($userMessage);

            $response->then(function (StreamedAgentResponse $streamed) use ($conversationId, $userId, $userMessage) {
                try {
                    $this->recordExchange->execute($conversationId, $userId, $userMessage, $streamed);
                } catch (\Throwable $e) {
                    Log::error('ChatController: failed to persist conversation messages', [
                        'conversation_id' => $conversationId,
                        'exception' => $e->getMessage(),
                    ]);
                }
            });

            // Stream the events manually (mirroring StreamableAgentResponse::toResponse)
            // so exceptions thrown mid-stream — after headers are sent — surface to the
            // client as an SSE error event instead of silently truncating the stream.
            return response()->stream(function () use ($response, $conversationId) {
                try {
                    foreach ($response as $event) {
                        yield 'data: '.((string) $event)."\n\n";
                    }
                } catch (\Throwable $e) {
                    Log::error('ChatController: agent streaming failed mid-stream', [
                        'conversation_id' => $conversationId,
                        'exception_class' => get_class($e),
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    [$code, $message] = $this->streamErrorDetails($e);

                    yield 'data: '.json_encode([
                        'type' => 'error',
                        'code' => $code,
                        'message' => $message,
                    ])."\n\n";
                }

                yield "data: [DONE]\n\n";
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
                'X-Conversation-Id' => $conversationId,
            ]);
        } catch (RateLimitedException $e) {
            Log::warning('ChatController: AI provider rate limited', [
                'conversation_id' => $conversationId,
                'exception' => $e->getMessage(),
            ]);

            [$code, $message] = $this->streamErrorDetails($e);

            return $this->sseError($message, $conversationId, $code, 429);
        } catch (ConnectionException $e) {
            Log::warning('ChatController: AI provider connection failed', [
                'conversation_id' => $conversationId,
                'exception' => $e->getMessage(),
            ]);

            [$code, $message] = $this->streamErrorDetails($e);

            return $this->sseError($message, $conversationId, $code, 503);
        } catch (\Throwable $e) {
            Log::error('ChatController: agent streaming failed', [
                'conversation_id' => $conversationId,
                'exception_class' => get_class($e),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if (! app()->isProduction()) {
                throw $e;
            }

            [$code, $message] = $this->streamErrorDetails($e);

            return $this->sseError($message, $conversationId, $code, 500);
        }
    }

    /**
     * Map an AI provider exception to a user-facing SSE error code and message.
     *
     * Single source of truth for both pre-stream and mid-stream failures so
     * error codes and copy cannot drift between the two paths.
     *
     * @return array{0: string, 1: string}
     */
    private function streamErrorDetails(\Throwable $e): array
    {
        return match (true) {
            $e instanceof InsufficientCreditsException => [
                'insufficient_credits',
                'The AI assistant is temporarily out of credits. Please check back later — in the meantime, feel free to browse the blog and projects.',
            ],
            $e instanceof RateLimitedException, $e instanceof ProviderOverloadedException => [
                'rate_limited',
                'The AI service is currently overloaded. Please try again in a moment.',
            ],
            $e instanceof ConnectionException => [
                'unavailable',
                'The AI service is temporarily unavailable. Please try again shortly.',
            ],
            default => [
                'internal_error',
                'Something went wrong while generating the response. Please try again.',
            ],
        };
    }

    /**
     * Return an SSE-formatted error response so the frontend can display
     * a meaningful message instead of hanging or showing a generic 500.
     *
     * The HTTP status allows monitoring and load balancers to detect failures,
     * while the SSE body keeps the frontend's event-stream parser happy.
     */
    private function sseError(string $message, string $conversationId, string $code, int $status): Response
    {
        return response()->stream(function () use ($message, $code) {
            $event = json_encode([
                'type' => 'error',
                'code' => $code,
                'message' => $message,
            ]);
            echo "data: {$event}\n\n";
            echo "data: [DONE]\n\n";

            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }, $status, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'X-Conversation-Id' => $conversationId,
            'X-Chat-Error' => 'true',
        ]);
    }
}
