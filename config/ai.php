<?php

declare(strict_types=1);

return [
    // Platform-provided AI is the default (bundled per plan); a tenant may
    // bring their own key, resolved at the call site in a later phase.
    'default_provider' => env('AI_PROVIDER', 'anthropic'),

    'platform_keys' => [
        'anthropic' => env('ANTHROPIC_API_KEY', ''),
        'openai' => env('OPENAI_API_KEY', ''),
    ],

    // Default models. Routine assistant chat runs on Haiku for cost (per the
    // bundled-allowance plan); complex chemistry can escalate to a stronger
    // model (with adaptive thinking) when the field assistant is specialized.
    'models' => [
        'anthropic' => env('AI_ANTHROPIC_MODEL', 'claude-haiku-4-5'),
        'openai' => env('AI_OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'max_tokens' => (int) env('AI_MAX_TOKENS', 4096),
    'timeout' => (int) env('AI_TIMEOUT', 30),

    // Look-up rounds per message: the last one must answer in words.
    'max_rounds' => 4,

    // Questions a day per person, by kind of account (none listed = no daily
    // cap beyond the 30-an-hour limit), and the share of the company's monthly
    // allowance customers can't use, so staff always have some left.
    'daily_limits' => ['customer' => 20, 'agent' => 100],
    'staff_reserve' => 0.2,

    // OpenAI models that need a reasoning setting sent, by name prefix. The
    // gpt-6 family only allows tools on chat completions with reasoning off.
    'openai_reasoning_effort' => ['gpt-6' => 'none'],

    // USD per million tokens, for the usage records. Update when prices change;
    // a model missing here still records its tokens, at no cost.
    // OpenAI's standard-tier list prices as of 2026-11-11.
    'prices' => [
        'claude-haiku-4-5' => ['input' => 1.00, 'output' => 5.00, 'cached_input' => 0.10],
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60, 'cached_input' => 0.075],
        'gpt-4.1-mini' => ['input' => 0.40, 'output' => 1.60, 'cached_input' => 0.10],
        'gpt-5-mini' => ['input' => 0.25, 'output' => 2.00, 'cached_input' => 0.025],
        'gpt-5-nano' => ['input' => 0.05, 'output' => 0.40, 'cached_input' => 0.005],
        'gpt-5.4-mini' => ['input' => 0.75, 'output' => 4.50, 'cached_input' => 0.075],
        'gpt-5.4-nano' => ['input' => 0.20, 'output' => 1.25, 'cached_input' => 0.02],
        'gpt-5.6-luna' => ['input' => 0.20, 'output' => 1.20, 'cached_input' => 0.02],
        'gpt-6-luna' => ['input' => 0.10, 'output' => 0.50, 'cached_input' => 0.01],
        'gpt-6-sol' => ['input' => 2.00, 'output' => 10.00, 'cached_input' => 0.20],
    ],
];
