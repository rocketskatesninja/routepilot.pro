<?php

declare(strict_types=1);

namespace App\Services\Chat;

/**
 * Registry of AI tools available to the assistant.
 *
 * The concrete tools (LookupCustomer/Pool/Chemistry/ServiceHistory/
 * Inventory/Balance + the ReassignAgent/SkipStop/DeleteStops/
 * ChangePreferredDay actions) are ported in Phase 3 alongside the
 * back-office models and controllers they read and mutate; add each class
 * to $toolClasses there.
 */
class ToolRegistry
{
    /**
     * Registered tool classes.
     *
     * @var list<class-string<AiTool>>
     */
    protected static array $toolClasses = [];

    /**
     * Cached tool instances (built once per request).
     *
     * @var list<AiTool>|null
     */
    protected static ?array $instances = null;

    /** @return list<AiTool> */
    public static function all(): array
    {
        return static::$instances ??= array_map(fn (string $cls): AiTool => new $cls, static::$toolClasses);
    }

    /**
     * Provider-agnostic schemas for the AI request.
     *
     * @return list<array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public static function schemas(): array
    {
        return array_map(fn (AiTool $t): array => $t->toSchema(), static::all());
    }

    public static function find(string $name): ?AiTool
    {
        foreach (static::all() as $tool) {
            if ($tool->name() === $name) {
                return $tool;
            }
        }

        return null;
    }

    /** Name + description list for embedding in a system prompt. */
    public static function descriptionList(): string
    {
        return implode("\n", array_map(
            fn (AiTool $t): string => "- {$t->name()} — {$t->description()}",
            static::all(),
        ));
    }
}
