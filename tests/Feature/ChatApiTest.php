<?php

use App\Agents\PortfolioAgent;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    User::factory()->create(['email' => config('chat.user.email')]);
    Cache::forget('chat.user_id');
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

    expect(DB::table('agent_conversations')->where('id', $conversationId)->exists())->toBeTrue();
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
