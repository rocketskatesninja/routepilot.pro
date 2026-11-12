<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\Customer;
use App\Models\Pool;

/**
 * The homeowner assistant's live data: who they are and their pools. Their
 * service, visits, requests and balance come from the my_* look-ups, built
 * from the same data as their portal pages.
 */
class CustomerContext
{
    public function build(Customer $customer): string
    {
        $pools = $customer->pools()->orderBy('name')->get()
            ->map(fn (Pool $p): string => "- {$p->name} ({$p->type}, ".($p->volume_gallons !== null ? number_format($p->volume_gallons).' gallons' : 'size not on file').", {$p->sanitizer_type})")
            ->join("\n");

        return "THEIR ACCOUNT\nHomeowner: {$customer->displayName()}\n"
            .($pools !== '' ? "Pools:\n{$pools}\n" : "No pools on file yet.\n")
            .'For their service, visits, requests or balance, use the my_service, my_visits, my_requests and my_balance look-ups.';
    }
}
