<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\User;
use App\Services\Chat\Tools\AnalyzeWater;
use App\Services\Chat\Tools\LookupBalance;
use App\Services\Chat\Tools\LookupChemistry;
use App\Services\Chat\Tools\LookupCustomer;
use App\Services\Chat\Tools\LookupInventory;
use App\Services\Chat\Tools\LookupPool;
use App\Services\Chat\Tools\LookupRequests;
use App\Services\Chat\Tools\LookupSchedule;
use App\Services\Chat\Tools\LookupServiceHistory;
use App\Services\Chat\Tools\LookupWorkOrders;

/**
 * Every look-up the in-app assistant has. Each tool decides who is offered it
 * (availableTo); none of them changes anything. The assistant is guide-only:
 * it explains how and links to the page where the change is made.
 */
class ToolRegistry
{
    /** @var list<class-string<AssistantTool>> */
    protected static array $toolClasses = [
        LookupCustomer::class,
        LookupPool::class,
        LookupChemistry::class,
        LookupServiceHistory::class,
        LookupSchedule::class,
        LookupRequests::class,
        LookupWorkOrders::class,
        LookupInventory::class,
        LookupBalance::class,
        AnalyzeWater::class,
    ];

    /** @return list<AssistantTool> */
    public static function all(): array
    {
        return array_map(fn (string $cls): AssistantTool => new $cls, static::$toolClasses);
    }

    /**
     * The look-ups this person is offered.
     *
     * @return list<AssistantTool>
     */
    public static function for(User $user): array
    {
        return array_values(array_filter(static::all(), fn (AssistantTool $t): bool => $t->availableTo($user)));
    }

    public static function find(string $name): ?AssistantTool
    {
        foreach (static::all() as $tool) {
            if ($tool->name() === $name) {
                return $tool;
            }
        }

        return null;
    }
}
