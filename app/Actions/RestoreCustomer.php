<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;
use App\Models\Pool;
use Illuminate\Support\Facades\DB;

/**
 * Un-archive a customer: restore the customer and exactly the pools that were
 * soft-deleted together with them (matched on the shared archive timestamp), so
 * pools the admin had deleted separately stay archived. Subscriptions that were
 * cancelled on archive stay cancelled — the admin re-activates them from the
 * pool if they want service to resume (no surprise re-scheduling).
 */
class RestoreCustomer
{
    public function handle(Customer $customer): void
    {
        DB::transaction(function () use ($customer): void {
            $archivedAt = $customer->deleted_at;

            $customer->restore();

            if ($archivedAt !== null) {
                Pool::withTrashed()
                    ->where('customer_id', $customer->id)
                    ->where('deleted_at', $archivedAt)
                    ->restore();
            }
        });
    }
}
