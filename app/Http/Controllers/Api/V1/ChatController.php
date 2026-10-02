<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\StreamChatReply;
use App\Exceptions\ChatUnavailableException;
use App\Exceptions\ConversationLimitReachedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChatRequest;
use App\Http\Responses\SseErrorResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Symfony\Component\HttpFoundation\Response;

class ChatController extends Controller
{
    public function __construct(private StreamChatReply $streamChatReply) {}

    public function __invoke(ChatRequest $request): Response
    {
        // Generated here, not in the action, so error responses can still return it in X-Conversation-Id.
        $conversationId = $request->validated('conversation_id');
        $isNew = ! $conversationId;

        if ($isNew) {
            $conversationId = Str::uuid7()->toString();
        }

        try {
            $response = $this->streamChatReply->execute(
                $conversationId,
                $isNew,
                $request->string('message')->toString(),
                $request->ip(),
            );

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
        } catch (ConversationLimitReachedException) {
            Log::warning('ChatController: conversation turn limit reached', [
                'conversation_id' => $conversationId,
                'ip' => $request->ip(),
            ]);

            return SseErrorResponse::make(
                'This conversation has reached its message limit. Start a new chat to keep going.',
                'conversation_limit',
                429,
                $conversationId,
            );
        } catch (ChatUnavailableException) {
            Log::error('ChatController: chatbot user not found — run ChatbotUserSeeder', [
                'email' => config('chat.user.email'),
            ]);
            abort(500, 'Chat service is unavailable.');
        } catch (RateLimitedException $e) {
            Log::warning('ChatController: AI provider rate limited', [
                'conversation_id' => $conversationId,
                'exception' => $e->getMessage(),
            ]);

            [$code, $message] = $this->streamErrorDetails($e);

            return SseErrorResponse::make($message, $code, 429, $conversationId);
        } catch (ProviderConnectionException $e) {
            Log::warning('ChatController: AI provider connection failed', [
                'conversation_id' => $conversationId,
                'exception' => $e->getMessage(),
            ]);

            [$code, $message] = $this->streamErrorDetails($e);

            return SseErrorResponse::make($message, $code, 503, $conversationId);
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

            return SseErrorResponse::make($message, $code, 500, $conversationId);
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
            $e instanceof ProviderConnectionException => [
                'unavailable',
                'The AI service is temporarily unavailable. Please try again shortly.',
            ],
            default => [
                'internal_error',
                'Something went wrong while generating the response. Please try again.',
            ],
        };
    }
}
