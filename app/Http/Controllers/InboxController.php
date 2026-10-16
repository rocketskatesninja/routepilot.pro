<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\ServiceRequest;
use App\Models\ServiceSubscription;
use App\Models\Tenant;
use App\Support\OptionLists;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The company's Requests inbox: what customers asked for in the portal, and
 * leads from the website. Requests are answered here (approve & schedule /
 * decline); leads are converted into customers.
 */
class InboxController extends Controller
{
    /** Website-lead detail keys, with the label people see. */
    private const LEAD_DETAILS = [
        'service_name' => 'Service',
        'estimate' => 'Estimate',
        'pool_type' => 'Pool type',
        'volume_gallons' => 'Pool size (gal)',
        'preferred_date' => 'Preferred date',
        'time_window' => 'Time of day',
    ];

    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);

        $tab = $request->string('tab')->toString() === 'leads' ? 'leads' : 'requests';
        $status = $request->string('status')->toString() === 'closed' ? 'closed' : 'open';
        $selectedId = $request->integer('selected');

        if ($tab === 'requests') {
            $rows = $this->requests()
                ->whereIn('status', $status === 'open' ? ['pending'] : ['approved', 'declined'])
                ->paginate($this->perPage($request))->withQueryString()
                ->through(fn (ServiceRequest $r): array => $this->requestRow($r));
            $selected = $selectedId > 0 ? $this->requests()->find($selectedId) : null;
            $detail = $selected !== null ? $this->requestDetail($selected) : null;
        } else {
            $rows = Lead::query()->with('serviceType:id,name')->latest('id')
                ->whereIn('status', $status === 'open' ? ['new', 'contacted'] : ['converted', 'archived'])
                ->paginate($this->perPage($request))->withQueryString()
                ->through(fn (Lead $l): array => $this->leadRow($l));
            $selected = $selectedId > 0 ? Lead::query()->with(['serviceType', 'customer'])->find($selectedId) : null;
            $detail = $selected !== null ? $this->leadDetail($selected) : null;
        }

        return Inertia::render('inbox/Index', [
            'tab' => $tab,
            'status' => $status,
            'rows' => $rows,
            'selected' => $detail,
            'counts' => [
                'requests' => $this->requests()->where('status', 'pending')->count(),
                'leads' => Lead::query()->where('status', 'new')->count(),
            ],
            'agents' => OptionLists::agents(),
        ]);
    }

    /** @return Builder<ServiceRequest> */
    private function requests(): Builder
    {
        // Requests from archived customers drop out of the inbox.
        return ServiceRequest::query()->whereHas('customer')
            ->with(['customer:id,first_name,last_name', 'pool:id,name', 'serviceType'])
            ->latest('id');
    }

    /** @return array<string, mixed> */
    private function requestRow(ServiceRequest $r): array
    {
        return [
            'id' => $r->id,
            'type' => $r->type,
            'title' => $r->title(),
            'customer' => $r->customer?->displayName(),
            'pool' => $r->pool?->getAttribute('name'),
            'preferred_date' => $r->preferred_date?->toDateString(),
            'on' => Tenant::localDate($r->created_at),
            'status' => $r->status,
            'status_label' => $r->statusLabel(),
        ];
    }

    /** @return array<string, mixed> */
    private function requestDetail(ServiceRequest $r): array
    {
        $service = $r->serviceType;
        $poolPlans = ServiceSubscription::query()->where('pool_id', $r->getAttribute('pool_id'))
            ->with(['serviceType:id,name', 'agent:id,first_name,last_name'])->orderBy('id')->get();

        return [
            ...$this->requestRow($r),
            'customer_id' => $r->getAttribute('customer_id'),
            'pool_id' => $r->getAttribute('pool_id'),
            'for_service' => $r->isForService(),
            'service' => $service === null ? null : [
                'id' => $service->id,
                'name' => $service->name,
                'offer' => $service->offerLabel(),
                'description' => $service->description,
                'is_active' => $service->is_active,
            ],
            'ends_on' => $r->ends_on?->toDateString(),
            'message' => $r->message,
            'reply' => $r->reply,
            'answered_on' => Tenant::localDate($r->resolved_at),
            'plan' => $r->plan?->scheduleLabel(),
            'pool_plans' => $poolPlans->map(fn (ServiceSubscription $p): array => [
                'id' => $p->id,
                'service_type_id' => $p->getAttribute('service_type_id'),
                'service' => $p->serviceType?->name,
                'schedule' => $p->scheduleLabel(),
                'status' => $p->status,
                'agent' => $p->agent?->displayName(),
            ])->all(),
            'defaults' => $r->isForService() ? $this->planDefaults($r, $poolPlans) : null,
        ];
    }

    /**
     * The approve form, pre-filled: the schedule the service is sold on, starting
     * on the customer's preferred date, with the tech who already does the pool.
     *
     * @param  Collection<int, ServiceSubscription>  $poolPlans
     * @return array<string, mixed>
     */
    private function planDefaults(ServiceRequest $r, Collection $poolPlans): array
    {
        $today = Tenant::localToday();
        $date = $r->preferred_date !== null && $r->preferred_date->gte($today) ? $r->preferred_date : $today;
        $frequency = (string) $r->serviceType?->frequency;

        return [
            'frequency' => array_key_exists($frequency, ServiceSubscription::FREQUENCIES) ? $frequency : 'weekly',
            'preferred_day' => strtolower($date->format('l')),
            'day_of_month' => $date->day,
            'starts_on' => $date->toDateString(),
            'assigned_agent_id' => $poolPlans->where('status', 'active')->whereNotNull('assigned_agent_id')->first()?->getAttribute('assigned_agent_id'),
        ];
    }

    /** @return array<string, mixed> */
    private function leadRow(Lead $l): array
    {
        return [
            'id' => $l->id,
            'name' => $l->name,
            'contact' => $l->email ?? $l->getAttribute('phone'),
            'source' => $l->source,
            'service' => $l->serviceType?->name,
            'on' => Tenant::localDate($l->created_at),
            'status' => $l->status,
        ];
    }

    /** @return array<string, mixed> */
    private function leadDetail(Lead $l): array
    {
        $details = $l->details ?? [];
        [$first, $last] = array_pad(explode(' ', trim($l->name), 2), 2, '');
        $poolType = $details['pool_type'] ?? null;

        return [
            ...$this->leadRow($l),
            'email' => $l->email,
            'phone' => $l->getAttribute('phone'),
            'message' => $l->getAttribute('message'),
            'details' => collect(self::LEAD_DETAILS)
                ->filter(fn (string $label, string $key): bool => is_string($details[$key] ?? null) && $details[$key] !== '')
                ->map(fn (string $label, string $key): array => ['label' => $label, 'value' => str_replace('_', ' ', (string) $details[$key])])
                ->values()->all(),
            'service' => $l->serviceType === null ? null : ['name' => $l->serviceType->name, 'offer' => $l->serviceType->offerLabel()],
            'customer' => $l->customer === null ? null : ['id' => $l->customer->id, 'name' => $l->customer->displayName()],
            // The add-customer form, pre-filled from what they told us.
            'convert' => [
                'first_name' => $first,
                'last_name' => $last,
                'email' => $l->email ?? '',
                'phone' => $l->getAttribute('phone') ?? '',
                'pool_type' => in_array($poolType, ['inground', 'above_ground', 'spa', 'infinity', 'other'], true) ? $poolType : 'inground',
                'pool_volume' => is_numeric($details['volume_gallons'] ?? null) ? (int) $details['volume_gallons'] : null,
            ],
        ];
    }
}
