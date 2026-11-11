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

    // USD per million tokens, for the usage records. Update when prices change;
    // a model missing here still records its tokens, at no cost.
    'prices' => [
        'claude-haiku-4-5' => ['input' => 1.00, 'output' => 5.00, 'cached_input' => 0.10],
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60, 'cached_input' => 0.075],
    ],
];
