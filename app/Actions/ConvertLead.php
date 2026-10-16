<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\ServiceRequest;
use App\Models\ServiceType;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Turn a website lead into a customer with their first pool. If they picked a
 * service on the website, it becomes a pending request in the inbox, ready to
 * approve and schedule like any other. A lead converts once — null when it
 * already has.
 */
class ConvertLead
{
    public function __construct(private readonly CreateCustomer $createCustomer) {}

    /**
     * @param  array<string, mixed>  $data  validated customer + first-pool fields
     * @return array{customer: Customer, request: ServiceRequest|null}|null
     */
    public function handle(Lead $lead, array $data): ?array
    {
        return DB::transaction(function () use ($lead, $data): ?array {
            $locked = Lead::query()->lockForUpdate()->find($lead->id);
            if ($locked === null || $locked->getAttribute('customer_id') !== null) {
                return null;
            }

            $customer = $this->createCustomer->handle($data);
            $locked->forceFill(['status' => 'converted', 'customer_id' => $customer->id])->save();

            $service = ServiceType::query()->where('is_active', true)->find($locked->getAttribute('service_type_id'));
            $request = $service === null ? null : ServiceRequest::create([
                'customer_id' => $customer->id,
                'pool_id' => $customer->pools()->value('id'),
                'service_type_id' => $service->id,
                'type' => 'service',
                'message' => $locked->getAttribute('message'),
                'preferred_date' => $this->preferredDate($locked),
            ]);

            return ['customer' => $customer, 'request' => $request];
        });
    }

    /** The date they asked for on the booking form, if it hasn't passed. */
    private function preferredDate(Lead $lead): ?string
    {
        $date = $lead->details['preferred_date'] ?? null;

        return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 && $date >= Tenant::localToday()->toDateString()
            ? $date
            : null;
    }
}
