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
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

#[MaxSteps(7)]
#[MaxTokens(2048)]
#[Temperature(0.6)]
class PortfolioAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * @param  iterable<\Laravel\Ai\Messages\Message>  $conversationMessages
     */
    public function __construct(
        protected iterable $conversationMessages = [],
    ) {}

    public function provider(): string
    {
        return config('agent.portfolio.provider');
    }

    public function model(): string
    {
        return config('agent.portfolio.model');
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
     * @return iterable<\Laravel\Ai\Contracts\Tool>
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
     * @return iterable<\Laravel\Ai\Messages\Message>
     */
    public function messages(): iterable
    {
        return $this->conversationMessages;
    }
}
