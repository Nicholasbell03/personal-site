<?php

use App\Agents\PortfolioAgent;
use App\Models\Blog;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;

beforeEach(function () {
    Cache::forget('chat.user_id');

    config(['cors.allowed_origins' => ['http://localhost:5173']]);

    $this->withHeaders([
        'Origin' => 'http://localhost:5173',
        'Sec-Fetch-Site' => 'cross-site',
        'Sec-Fetch-Mode' => 'cors',
    ]);
});

it('validates message is required', function () {
    PortfolioAgent::fake();

    $this->postJson('/api/v1/chat', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['message']);
});

it('validates message minimum length', function () {
    PortfolioAgent::fake();

    $this->postJson('/api/v1/chat', ['message' => 'a'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['message']);
});

it('validates message maximum length', function () {
    PortfolioAgent::fake();

    $this->postJson('/api/v1/chat', ['message' => str_repeat('a', 501)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['message']);
});

it('validates conversation_id must be a uuid', function () {
    PortfolioAgent::fake();

    $this->postJson('/api/v1/chat', [
        'message' => 'Hello',
        'conversation_id' => 'not-a-uuid',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['conversation_id']);
});

it('accepts null conversation_id', function () {
    PortfolioAgent::fake(['Response']);

    $this->post('/api/v1/chat', [
        'message' => 'Hello',
        'conversation_id' => null,
    ], ['Accept' => 'application/json'])->assertOk();
});

it('returns streaming response with event-stream content type', function () {
    PortfolioAgent::fake(['I can help with that!']);

    $response = $this->post('/api/v1/chat', [
        'message' => 'Tell me about Nick',
    ], ['Accept' => 'application/json']);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');
});

it('returns X-Conversation-Id header', function () {
    PortfolioAgent::fake(['Sure thing!']);

    $response = $this->post('/api/v1/chat', [
        'message' => 'Hello world',
    ], ['Accept' => 'application/json']);

    $response->assertOk();
    expect($response->headers->get('X-Conversation-Id'))->not->toBeNull()
        ->and($response->headers->get('X-Conversation-Id'))->toMatch('/^[0-9a-f-]+$/');
});

it('creates conversation in database on first request', function () {
    PortfolioAgent::fake(['Welcome!']);

    $response = $this->post('/api/v1/chat', [
        'message' => 'First message',
    ], ['Accept' => 'application/json']);

    $conversationId = $response->headers->get('X-Conversation-Id');

    $conversation = DB::table('agent_conversations')->where('id', $conversationId)->first();

    expect($conversation)->not->toBeNull()
        ->and($conversation->ip_address)->toBe('127.0.0.1');
});

it('persists messages after streaming completes', function () {
    PortfolioAgent::fake(['This is the response.']);

    $response = $this->post('/api/v1/chat', [
        'message' => 'Persist test message',
    ], ['Accept' => 'application/json']);

    $conversationId = $response->headers->get('X-Conversation-Id');

    // Consume the streamed response so then() callbacks fire
    $response->streamedContent();

    $messages = DB::table('agent_conversation_messages')
        ->where('conversation_id', $conversationId)
        ->orderBy('created_at')
        ->get();

    expect($messages)->toHaveCount(2);
    expect($messages[0]->role)->toBe('user');
    expect($messages[0]->content)->toBe('Persist test message');
    expect($messages[1]->role)->toBe('assistant');
});

it('reuses existing conversation when conversation_id is provided', function () {
    PortfolioAgent::fake(['First response', 'Second response']);

    // First message creates a conversation
    $response1 = $this->post('/api/v1/chat', [
        'message' => 'First question',
    ], ['Accept' => 'application/json']);

    $conversationId = $response1->headers->get('X-Conversation-Id');

    $response1->streamedContent();

    // Second message reuses the conversation
    $response2 = $this->post('/api/v1/chat', [
        'message' => 'Follow up question',
        'conversation_id' => $conversationId,
    ], ['Accept' => 'application/json']);

    $response2->assertOk();
    expect($response2->headers->get('X-Conversation-Id'))->toBe($conversationId);

    $response2->streamedContent();

    $messages = DB::table('agent_conversation_messages')
        ->where('conversation_id', $conversationId)
        ->get();

    expect($messages)->toHaveCount(4);
});

it('returns 500 when chatbot user is not seeded', function () {
    // Delete the chatbot user seeded in beforeEach
    User::where('email', config('chat.user.email'))->delete();
    Cache::forget('chat.user_id');

    PortfolioAgent::fake(['Response']);

    $this->postJson('/api/v1/chat', ['message' => 'Hello'])
        ->assertInternalServerError();
});

it('does not cache null user_id', function () {
    User::where('email', config('chat.user.email'))->delete();
    Cache::forget('chat.user_id');

    PortfolioAgent::fake(['Response']);

    $this->postJson('/api/v1/chat', ['message' => 'Hello'])
        ->assertInternalServerError();

    expect(Cache::get('chat.user_id'))->toBeNull();
});

it('streams a provider tool call and answer through to the client', function () {
    config([
        'agent.portfolio.provider' => 'openai',
        'agent.portfolio.fallback_provider' => null,
        'ai.providers.openai.key' => 'test-key',
    ]);

    // Creating a blog queues embedding generation, which would call OpenAI unfaked.
    Queue::fake();

    Blog::factory()->published()->create(['title' => 'Upgrading Laravel AI']);

    $sse = fn (array ...$events) => collect($events)
        ->map(fn (array $event) => 'data: '.json_encode($event)."\n\n")
        ->join('');

    Http::fakeSequence('api.openai.com/*')
        ->push($sse(
            ['type' => 'response.created', 'response' => ['id' => 'resp_1', 'model' => 'gpt-5.1']],
            ['type' => 'response.output_item.added', 'output_index' => 0, 'item' => ['type' => 'function_call', 'id' => 'fc_1', 'call_id' => 'call_1', 'name' => 'GetBlogs']],
            ['type' => 'response.function_call_arguments.done', 'item_id' => 'fc_1', 'arguments' => '{"limit":1}'],
            ['type' => 'response.completed', 'response' => ['id' => 'resp_1', 'status' => 'completed', 'output' => [['type' => 'function_call', 'status' => 'completed']], 'usage' => ['input_tokens' => 50, 'output_tokens' => 5]]],
        ), 200, ['Content-Type' => 'text/event-stream'])
        ->push($sse(
            ['type' => 'response.created', 'response' => ['id' => 'resp_2', 'model' => 'gpt-5.1']],
            ['type' => 'response.output_text.delta', 'delta' => 'Here is '],
            ['type' => 'response.output_text.delta', 'delta' => 'the latest post.'],
            ['type' => 'response.output_text.done'],
            ['type' => 'response.completed', 'response' => ['id' => 'resp_2', 'status' => 'completed', 'output' => [['type' => 'message', 'status' => 'completed']], 'usage' => ['input_tokens' => 80, 'output_tokens' => 10]]],
        ), 200, ['Content-Type' => 'text/event-stream']);

    $response = $this->post('/api/v1/chat', [
        'message' => 'What did Nick write recently?',
    ], ['Accept' => 'application/json']);

    $events = collect(explode("\n\n", $response->streamedContent()))
        ->filter(fn (string $line) => str_starts_with($line, 'data: {'))
        ->map(fn (string $line) => json_decode(substr($line, 6), true))
        ->values();

    $toolResult = $events->firstWhere('type', 'tool_result');

    expect($events->where('type', 'error'))->toBeEmpty()
        ->and($toolResult['tool_name'])->toBe('GetBlogs')
        ->and($toolResult['successful'])->toBeTrue()
        ->and($toolResult['result'])->toContain('Upgrading Laravel AI')
        ->and($events->where('type', 'text_delta')->pluck('delta')->join(''))->toBe('Here is the latest post.');

    $reply = DB::table('agent_conversation_messages')
        ->where('conversation_id', $response->headers->get('X-Conversation-Id'))
        ->where('role', 'assistant')
        ->first();

    expect($reply->content)->toBe('Here is the latest post.')
        ->and(json_decode($reply->tool_calls, true))->toHaveCount(1)
        ->and(json_decode($reply->usage, true))->toMatchArray(['input_tokens' => 130, 'output_tokens' => 15]);
});

it('emits an SSE error event when the provider fails mid-stream', function (Throwable $exception, string $code, string $message) {
    PortfolioAgent::fake(function () use ($exception) {
        throw $exception;
    });

    $response = $this->post('/api/v1/chat', [
        'message' => 'Hello there',
    ], ['Accept' => 'application/json']);

    $response->assertOk();

    $content = $response->streamedContent();

    expect($content)->toContain('"type":"error"')
        ->and($content)->toContain('"code":"'.$code.'"')
        ->and($content)->toContain($message)
        ->and($content)->toContain('data: [DONE]');
})->with([
    'out of credits' => [InsufficientCreditsException::forProvider('openai'), 'insufficient_credits', 'out of credits'],
    'unreachable provider' => [ProviderConnectionException::forProvider('openai'), 'unavailable', 'temporarily unavailable'],
    'unexpected failure' => [new RuntimeException('boom'), 'internal_error', 'Something went wrong'],
]);

it('is rate limited', function () {
    PortfolioAgent::fake(array_fill(0, 15, 'Response'));

    for ($i = 0; $i < 10; $i++) {
        $this->post('/api/v1/chat', [
            'message' => "Message {$i}",
        ], ['Accept' => 'application/json'])->assertOk();
    }

    $this->post('/api/v1/chat', [
        'message' => 'One too many',
    ], ['Accept' => 'application/json'])->assertTooManyRequests();
});

it('has multi-tier rate limits configured', function () {
    $request = Request::create('/api/v1/chat', 'POST');
    $request->server->set('REMOTE_ADDR', '192.168.1.1');

    $limits = RateLimiter::limiter('chat')($request);

    expect($limits)->toBeArray()->toHaveCount(4);
    expect($limits[0]->maxAttempts)->toBe(10);
    expect($limits[0]->decaySeconds)->toBe(60);
    expect($limits[1]->maxAttempts)->toBe(50);
    expect($limits[1]->decaySeconds)->toBe(3600);
    expect($limits[2]->maxAttempts)->toBe(100);
    expect($limits[2]->decaySeconds)->toBe(86400);
    expect($limits[3]->maxAttempts)->toBe(config('agent.portfolio.daily_global_limit'));
    expect($limits[3]->decaySeconds)->toBe(86400);
    expect($limits[3]->key)->toBe('global');
});

it('rate limits IPv6 clients by /64 network', function () {
    $keyFor = function (string $ip): string {
        $request = Request::create('/api/v1/chat', 'POST');
        $request->server->set('REMOTE_ADDR', $ip);

        return RateLimiter::limiter('chat')($request)[0]->key;
    };

    expect($keyFor('2001:db8:1:2::1'))->toBe($keyFor('2001:db8:1:2:ffff:ffff:ffff:9'))
        ->and($keyFor('2001:db8:1:2::1'))->toStartWith('2001:db8:1:2::/64:')
        ->and($keyFor('2001:db8:1:2::1'))->not->toBe($keyFor('2001:db8:1:3::1'))
        ->and($keyFor('203.0.113.7'))->toStartWith('203.0.113.7:');
});

it('stops a conversation once it reaches the turn limit', function () {
    config(['agent.portfolio.max_conversation_turns' => 2]);
    PortfolioAgent::fake(['One', 'Two', 'Three']);

    $first = $this->post('/api/v1/chat', ['message' => 'First'], ['Accept' => 'application/json']);
    $first->streamedContent();
    $conversationId = $first->headers->get('X-Conversation-Id');

    $this->post('/api/v1/chat', ['message' => 'Second', 'conversation_id' => $conversationId], ['Accept' => 'application/json'])
        ->assertOk()
        ->streamedContent();

    $response = $this->post('/api/v1/chat', ['message' => 'Third', 'conversation_id' => $conversationId], ['Accept' => 'application/json']);

    $response->assertTooManyRequests();
    expect($response->streamedContent())->toContain('"code":"conversation_limit"')
        ->and($response->headers->get('X-Conversation-Id'))->toBe($conversationId);
});

describe('turnstile', function () {
    beforeEach(function () {
        config(['services.turnstile.secret_key' => 'turnstile-secret']);
        PortfolioAgent::fake(['Hello!']);
    });

    it('rejects chat without a token', function () {
        Http::fake();

        $response = $this->post('/api/v1/chat', ['message' => 'Hello there'], ['Accept' => 'application/json']);

        $response->assertForbidden();
        expect($response->streamedContent())->toContain('"code":"verification_failed"');
        Http::assertNothingSent();
    });

    it('rejects a token Cloudflare says is invalid', function () {
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);

        $this->post('/api/v1/chat', ['message' => 'Hello there'], ['Accept' => 'application/json', 'X-Turnstile-Token' => 'bad'])
            ->assertForbidden();
    });

    it('fails closed when Cloudflare is unreachable', function () {
        Http::fake(['challenges.cloudflare.com/*' => fn () => throw new ConnectionException('timeout')]);

        $this->post('/api/v1/chat', ['message' => 'Hello there'], ['Accept' => 'application/json', 'X-Turnstile-Token' => 'tok'])
            ->assertForbidden();
    });

    it('lets a verified visitor chat', function () {
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->post('/api/v1/chat', ['message' => 'Hello there'], ['Accept' => 'application/json', 'X-Turnstile-Token' => 'good'])
            ->assertOk();

        Http::assertSent(fn ($request) => $request['secret'] === 'turnstile-secret'
            && $request['response'] === 'good'
            && $request['remoteip'] === '127.0.0.1');
    });

    it('is skipped when no secret is configured', function () {
        config(['services.turnstile.secret_key' => null]);
        Http::fake();

        $this->post('/api/v1/chat', ['message' => 'Hello there'], ['Accept' => 'application/json'])->assertOk();
        Http::assertNothingSent();
    });
});
