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
 *  - its charges (the visit, and its chemicals if they were billed) are cancelled
 *    (kept in the history, marked void); an invoice they were on updates, and money
 *    already paid for them stays on the account as credit
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

    /** @return float what the charges it cancelled came to (0 when the visit had none) */
    public function handle(ServiceVisit $visit, User $by): float
    {
        $photoPaths = $visit->photos()->pluck('photo_path')->all();
        $route = $visit->routeStop?->route;

        $charges = DB::transaction(function () use ($visit, $by) {
            $charges = Charge::query()->active()->where('service_visit_id', $visit->id)->get();
            if ($charges->isNotEmpty()) {
                Charge::query()->whereKey($charges->modelKeys())->update([
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

            if ($charges->isNotEmpty()) {
                $customer = Customer::withTrashed()->find($charges->value('customer_id'));
                if ($customer !== null) {
                    $this->billing->refreshInvoices($customer);
                }
            }

            return $charges;
        });

        foreach ($photoPaths as $path) {
            $this->photos->delete(is_string($path) ? $path : null);
        }
        if ($route !== null) {
            event(RouteUpdated::for($route));
        }

        return round((float) $charges->sum(fn (Charge $c): float => $c->total()), 2);
    }
}
