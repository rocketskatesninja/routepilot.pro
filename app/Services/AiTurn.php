<?php

declare(strict_types=1);

namespace App\Services;

/**
 * One reply from the AI provider, the same shape for Anthropic and OpenAI:
 * its text, any look-ups it asked for, the reply in the provider's own shape
 * (sent back alongside the look-up results), and what it cost.
 */
final class AiTurn
{
    public const UNAVAILABLE = 'The assistant isn’t available right now. Please try again in a minute.';

    /**
     * @param  list<array{id: string, name: string, arguments: array<string, mixed>}>  $toolCalls
     * @param  array<string, mixed>  $message
     */
    public function __construct(
        public readonly string $text,
        public readonly array $toolCalls = [],
        public readonly array $message = [],
        public readonly int $tokens = 0,
        public readonly float $cost = 0.0,
        public readonly bool $failed = false,
    ) {}

    /** The provider couldn't be reached or refused the request: a friendly line, never its error text. */
    public static function failure(): self
    {
        return new self(self::UNAVAILABLE, failed: true);
    }
}
