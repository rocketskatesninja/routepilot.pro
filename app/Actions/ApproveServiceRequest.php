<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ServiceRequest;
use App\Models\ServiceSubscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Say yes to a customer's request, turning it into scheduled work through the
 * plan actions:
 *  - a service request creates its plan (the company's schedule choices);
 *  - a pause puts the hold on the pool's active plans;
 *  - a stop ends the pool's plans on the date asked (or right away);
 *  - anything else is simply marked done.
 * A request is answered once — false when it already was.
 */
class ApproveServiceRequest
{
    public function __construct(
        private readonly CreateSubscription $createPlan,
        private readonly UpdateSubscription $updatePlan,
        private readonly SendRequestAnswer $answer,
    ) {}

    /**
     * @param  array<string, mixed>  $data  the plan's schedule (service requests) + optional reply
     */
    public function handle(ServiceRequest $request, array $data, User $by): bool
    {
        $approved = DB::transaction(function () use ($request, $data, $by): bool {
            $locked = ServiceRequest::query()->lockForUpdate()->find($request->id);
            if ($locked === null || $locked->status !== 'pending') {
                return false;
            }

            $plans = ServiceSubscription::query()->where('pool_id', $locked->getAttribute('pool_id'));

            if ($locked->isForService()) {
                $plan = $this->createPlan->handle([
                    ...$data,
                    'pool_id' => $locked->getAttribute('pool_id'),
                    'service_type_id' => $locked->getAttribute('service_type_id'),
                ]);
                $locked->forceFill(['service_subscription_id' => $plan->id]);
            } elseif ($locked->type === 'hold') {
                foreach ($plans->where('status', 'active')->get() as $plan) {
                    $this->updatePlan->handle($plan, [
                        'hold_starts_at' => $locked->preferred_date?->toDateString(),
                        'hold_ends_at' => $locked->ends_on?->toDateString(),
                    ]);
                }
            } elseif ($locked->type === 'cancel') {
                foreach ($plans->whereIn('status', ['active', 'paused'])->get() as $plan) {
                    $this->updatePlan->handle($plan, $this->ending($plan, $locked));
                }
            }

            $locked->forceFill([
                'status' => 'approved',
                'reply' => $data['reply'] ?? null,
                'resolved_by' => $by->id,
                'resolved_at' => now(),
            ])->save();

            return true;
        });

        if ($approved) {
            $this->answer->handle($request->refresh());
        }

        return $approved;
    }

    /**
     * End a plan after the customer's last date, or right away when they asked
     * for as soon as possible (or the plan wouldn't have started by then).
     *
     * @return array<string, mixed>
     */
    private function ending(ServiceSubscription $plan, ServiceRequest $request): array
    {
        $last = $request->preferred_date;
        $startsAfter = $last !== null && $plan->starts_on !== null && $plan->starts_on->gt($last);

        return $last === null || $last->lte(Tenant::localToday()) || $startsAfter
            ? ['status' => 'cancelled']
            : ['ends_on' => $last->toDateString()];
    }
}
