<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceSubscription;
use Illuminate\Support\Facades\DB;

/**
 * Archive (soft-delete) a customer AND their operational footprint, so nothing
 * they own keeps affecting the tenant's account while they're archived:
 *  - their active subscriptions are cancelled (SubscriptionMaterializer only
 *    schedules 'active' subs, so this stops phantom visits);
 *  - their pools are soft-deleted too (BillingMeter counts non-trashed pools by
 *    tenant, so this drops them from metering);
 *  - the customer is soft-deleted.
 *
 * Customer and pools share ONE `deleted_at` timestamp so RestoreCustomer can
 * bring back exactly the pools archived in this cascade (and not ones the admin
 * had deleted separately). A permanent delete later (PurgeCustomer) cascades it
 * all away via the DB foreign keys.
 */
class ArchiveCustomer
{
    public function handle(Customer $customer): void
    {
        DB::transaction(function () use ($customer): void {
            $ts = now();
            $poolIds = $customer->pools()->pluck('id'); // active (non-trashed) pools only

            if ($poolIds->isNotEmpty()) {
                ServiceSubscription::query()->whereIn('pool_id', $poolIds)
                    ->where('status', 'active')->update(['status' => 'cancelled']);

                Pool::query()->whereIn('id', $poolIds)->update(['deleted_at' => $ts]);
            }

            $customer->forceFill(['deleted_at' => $ts])->save();
        });
    }
}
