<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\RouteUpdated;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;
use App\Services\PhotoService;
use Illuminate\Support\Facades\DB;

/**
 * Delete a visit report — a duplicate, say, or one filed against the wrong pool
 * — and undo what it set in motion:
 *  - its charge is cancelled (kept in the history, marked void); an invoice it
 *    was on updates, and money already paid for it stays on the account as credit
 *  - the chemicals it took from inventory go back on the shelf
 *  - its route stop opens again: due if its day is today or later, missed if
 *    the day has gone; the live schedule board refreshes
 *  - its readings, treatments, tasks and photos go with it
 */
class DeleteReport
{
    public function __construct(
        private BillingService $billing,
        private ReverseVisitInventory $reverseInventory,
        private PhotoService $photos,
    ) {}

    /** @return Charge|null the charge it cancelled, if the visit had one */
    public function handle(ServiceVisit $visit, User $by): ?Charge
    {
        $photoPaths = $visit->photos()->pluck('photo_path')->all();
        $route = $visit->routeStop?->route;

        $charge = DB::transaction(function () use ($visit, $by): ?Charge {
            $charge = Charge::query()->active()->where('service_visit_id', $visit->id)->first();
            if ($charge !== null) {
                Charge::query()->whereKey($charge->id)->update([
                    'voided_at' => now(),
                    'void_reason' => 'Visit report deleted',
                    'voided_by' => $by->id,
                ]);
            }

            $this->reverseInventory->handle($visit);

            $stop = $visit->routeStop;
            if ($stop !== null && $stop->status === 'completed' && $stop->route !== null) {
                $past = $stop->route->scheduled_date->toDateString() < Tenant::localToday()->toDateString();
                $stop->update(['status' => $past ? 'missed' : 'pending', 'completed_at' => null]);
            }

            $visit->delete(); // its reading, treatments, tasks and photo rows cascade

            if ($charge !== null) {
                $customer = Customer::withTrashed()->find($charge->customer_id);
                if ($customer !== null) {
                    $this->billing->refreshInvoices($customer);
                }
            }

            return $charge;
        });

        foreach ($photoPaths as $path) {
            $this->photos->delete(is_string($path) ? $path : null);
        }
        if ($route !== null) {
            event(RouteUpdated::for($route));
        }

        return $charge;
    }
}
