<?php

namespace App\Agents;

use App\Agents\Tools\GetBlogDetail;
use App\Agents\Tools\GetBlogs;
use App\Agents\Tools\GetCVDetail;
use App\Agents\Tools\GetProjectDetail;
use App\Agents\Tools\GetProjects;
use App\Agents\Tools\GetShares;
use App\Agents\Tools\GetTechnologies;
use App\Agents\Tools\SearchContent;
use App\Enums\UserContextKey;
use App\Models\UserContext;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

// No Temperature attribute: OpenAI rejects temperature when reasoning.effort
// is set ("Unsupported parameter"), and reasoning models ignore it anyway.
#[MaxSteps(7)]
#[MaxTokens(2048)]
class PortfolioAgent implements Agent, Conversational, HasProviderOptions, HasTools
{
    use Promptable;

    /**
     * @param  iterable<Message>  $conversationMessages
     */
    public function __construct(
        protected iterable $conversationMessages = [],
    ) {}

    /**
     * Ordered provider => model map; the SDK fails over to the next entry
     * when a provider throws a FailoverableException.
     *
     * @return array<string, string>
     */
    public function provider(): array
    {
        $providers = [
            config('agent.portfolio.provider') => config('agent.portfolio.model'),
        ];

        $fallbackProvider = config('agent.portfolio.fallback_provider');

        if ($fallbackProvider && ! array_key_exists($fallbackProvider, $providers)) {
            $providers[$fallbackProvider] = config('agent.portfolio.fallback_model');
        }

        return $providers;
    }

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        return match ($provider instanceof Lab ? $provider : Lab::tryFrom($provider)) {
            Lab::OpenAI => [
                'reasoning' => ['effort' => config('agent.portfolio.openai_reasoning_effort')],
            ],
            // Merged into generationConfig by the Gemini gateway
            Lab::Gemini => [
                'thinkingConfig' => ['thinkingLevel' => config('agent.portfolio.gemini_thinking_level')],
            ],
            default => [],
        };
    }

    public function timeout(): int
    {
        return config('agent.portfolio.timeout');
    }

    /**
     * Get the instructions that the agent should follow.
     * Prompt is hidden in the database for security reasons
     */
    public function instructions(): string
    {
        $promptTemplate = UserContext::cached(UserContextKey::AgentSystemPrompt);

        if ($promptTemplate) {
            return strtr($promptTemplate, [
                '{{cv_summary}}' => UserContext::cached(UserContextKey::CvSummary) ?? 'No CV summary available.',
                '{{contact_email}}' => config('contact.email'),
                '{{contact_linkedin}}' => config('contact.linkedin'),
                '{{contact_github}}' => config('contact.github'),
                '{{contact_twitter}}' => config('contact.twitter'),
            ]);
        }

        return 'You are sudo, the AI assistant on Nicholas Bell\'s portfolio website. Help visitors learn about Nick\'s work, projects, blog posts, and shared content. Use your tools to look up information — never guess. Keep responses conversational and concise.';
    }

    /**
     * @return iterable<Tool>
     */
    public function tools(): iterable
    {
        return [
            app(SearchContent::class),
            app(GetBlogs::class),
            app(GetProjects::class),
            app(GetShares::class),
            app(GetBlogDetail::class),
            app(GetProjectDetail::class),
            app(GetCVDetail::class),
            app(GetTechnologies::class),
        ];
    }

    /**
     * @return iterable<Message>
     */
    public function messages(): iterable
    {
        return $this->conversationMessages;
    }
}
