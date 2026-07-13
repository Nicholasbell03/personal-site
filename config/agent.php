<?php

return [

    'portfolio' => [
        'provider' => env('PORTFOLIO_AGENT_PROVIDER', 'openai'),
        'model' => env('PORTFOLIO_AGENT_MODEL', 'gpt-5.6-luna'),

        // Failover: tried in order when the primary provider throws a
        // FailoverableException (insufficient credits, rate limited, overloaded).
        'fallback_provider' => env('PORTFOLIO_AGENT_FALLBACK_PROVIDER', 'gemini'),
        'fallback_model' => env('PORTFOLIO_AGENT_FALLBACK_MODEL', 'gemini-3.5-flash'),

        // Reasoning kept low for fast responses — visitor questions are simple,
        // and tool selection still needs some reasoning ("none" degrades it).
        // OpenAI accepts none|low|medium|high|xhigh|max; Gemini low|medium|high.
        'openai_reasoning_effort' => env('PORTFOLIO_AGENT_OPENAI_REASONING_EFFORT', 'low'),
        'gemini_thinking_level' => env('PORTFOLIO_AGENT_GEMINI_THINKING_LEVEL', 'low'),

        'timeout' => (int) env('PORTFOLIO_AGENT_TIMEOUT', 15),

        // Attribute-only (framework limitation) — change in PortfolioAgent class
        'temperature' => 0.6,
        'max_tokens' => 2048,
        'max_steps' => 7,
    ],

];
