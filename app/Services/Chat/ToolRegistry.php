<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Services\Chat\Tools\LookupBalance;
use App\Services\Chat\Tools\LookupChemistry;
use App\Services\Chat\Tools\LookupCustomer;
use App\Services\Chat\Tools\LookupInventory;
use App\Services\Chat\Tools\LookupPool;
use App\Services\Chat\Tools\LookupServiceHistory;

/**
 * Registry of AI tools available to the assistant (tenant-admin role).
 * Add a tool by appending its class here.
 */
class ToolRegistry
{
    /**
     * Registered tool classes.
     *
     * @var list<class-string<AiTool>>
     */
    protected static array $toolClasses = [
        // Look-ups only. The change tools (ChangePreferredDay, ReassignAgent,
        // SkipStop) are switched off: they acted at once, on the first fuzzy
        // match, with no confirmation or audit. They come back as confirm cards
        // in the assistant rebuild.
        LookupCustomer::class,
        LookupPool::class,
        LookupChemistry::class,
        LookupServiceHistory::class,
        LookupInventory::class,
        LookupBalance::class,
    ];

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
