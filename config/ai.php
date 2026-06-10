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
];
