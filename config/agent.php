<?php

return [

    'portfolio' => [
        'provider' => env('PORTFOLIO_AGENT_PROVIDER', 'gemini'),
        'model' => env('PORTFOLIO_AGENT_MODEL', 'gemini-3-flash-preview'),
        'timeout' => (int) env('PORTFOLIO_AGENT_TIMEOUT', 15),

        // Attribute-only (framework limitation) — change in PortfolioAgent class
        'temperature' => 0.6,
        'max_tokens' => 2048,
        'max_steps' => 7,
    ],

];
