<?php

namespace App\Http\Responses;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * An SSE-formatted error, so the chat frontend can show the message instead of hanging.
 * The HTTP status still lets monitoring detect the failure.
 */
class SseErrorResponse
{
    public static function make(string $message, string $code, int $status, ?string $conversationId = null): StreamedResponse
    {
        return response()->stream(function () use ($message, $code) {
            $event = json_encode([
                'type' => 'error',
                'code' => $code,
                'message' => $message,
            ]);
            echo "data: {$event}\n\n";
            echo "data: [DONE]\n\n";
        }, $status, array_filter([
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'X-Conversation-Id' => $conversationId,
            'X-Chat-Error' => 'true',
        ]));
    }
}
