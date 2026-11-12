<?php

declare(strict_types=1);

namespace App\Services\Chat\Tools;

use App\Models\ServiceRequest;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\Chat\AssistantTool;

/** A homeowner's requests and their answers, and the services they can ask for (list prices only when shown). */
class MyRequests extends AssistantTool
{
    public function name(): string
    {
        return 'my_requests';
    }

    public function description(): string
    {
        return 'Your requests to the company (add a service, pause, stop, notes) with their status and any reply, plus the '
            .'services you can ask for on the Services page.';
    }

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass, 'required' => []];
    }

    public function availableTo(User $user): bool
    {
        return $user->role === 'customer';
    }

    public function execute(array $params, User $user): string
    {
        $customer = $this->customer($user);
        if ($customer === null) {
            return self::NO_CUSTOMER;
        }

        $requests = ServiceRequest::query()->where('customer_id', $customer->id)->with('pool', 'serviceType', 'plan.serviceType')->latest('id')->limit(10)->get();
        $services = ServiceType::query()->where('tenant_id', (int) $user->tenant_id)->requestable()->orderBy('price')->get();

        return ($requests->isEmpty() ? "You haven't sent any requests." : "Your requests:\n".$requests->map(fn (ServiceRequest $r): string => '- '
            .$r->title().' — '.$r->statusLabel().', sent '.self::day($r->created_at, 'M j')
            .($r->pool !== null ? ', '.$r->pool->getAttribute('name') : '')
            .($r->preferred_date !== null ? ', from '.$r->preferred_date->format('M j') : '')
            .($r->ends_on !== null ? ' to '.$r->ends_on->format('M j') : '')
            .(($m = self::note($r->message)) !== null ? "\n  You wrote: {$m}" : '')
            .(($a = self::note($r->reply)) !== null ? "\n  The company replied: {$a}" : ''))->join("\n"))
            ."\n\nServices you can ask for:\n"
            .($services->isEmpty() ? 'None listed right now: send a note on the Services page.' : $services->map(function (ServiceType $t): string {
                $price = $t->customerPrice();

                return "- {$t->name}".($price !== null ? ' — '.$t->offerLabel() : ' — price set for your pool when the company confirms')
                    .(is_string($t->description) && $t->description !== '' ? ": {$t->description}" : '');
            })->join("\n"));
    }
}
