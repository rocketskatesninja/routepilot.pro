<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\ServiceVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer portal — the homeowner's own service history. Strictly scoped to
 * the logged-in customer's pools (a customer never sees another's visits).
 */
class PortalController extends Controller
{
    public function history(Request $request): Response
    {
        $customer = $this->resolveCustomer($request);
        $poolIds = $customer->pools()->pluck('id');

        $visits = ServiceVisit::query()
            ->whereIn('pool_id', $poolIds)
            ->where('status', 'completed')
            ->with('pool:id,name')
            ->latest('completed_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ServiceVisit $v): array => [
                'id' => $v->id,
                'pool' => $v->pool?->getAttribute('name'),
                'on' => $v->completed_at?->toDateString(),
            ]);

        $selected = null;
        $selectedId = $request->integer('selected');
        if ($selectedId > 0) {
            $visit = ServiceVisit::query()
                ->whereIn('pool_id', $poolIds)
                ->whereKey($selectedId)
                ->with(['pool:id,name', 'agent:id,first_name,last_name', 'chemicalReading', 'treatments', 'tasks', 'photos'])
                ->first();
            if ($visit !== null) {
                $selected = $this->toDetail($visit);
            }
        }

        return Inertia::render('portal/History', [
            'visits' => $visits,
            'selected' => $selected,
        ]);
    }

    public function requests(Request $request): Response
    {
        $customer = $this->resolveCustomer($request);

        $requests = ServiceRequest::query()
            ->where('customer_id', $customer->id)
            ->with('pool:id,name')
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (ServiceRequest $r): array => [
                'id' => $r->id,
                'type' => $r->type,
                'message' => $r->message,
                'status' => $r->status,
                'pool' => $r->pool?->getAttribute('name'),
                'preferred_date' => $r->preferred_date?->toDateString(),
                'on' => $r->created_at?->toDateString(),
            ])->all();

        $pools = $customer->pools()->orderBy('name')->get(['id', 'name'])
            ->map(fn ($p): array => ['id' => $p->id, 'name' => $p->getAttribute('name')])->all();

        return Inertia::render('portal/Requests', [
            'requests' => $requests,
            'pools' => $pools,
        ]);
    }

    /** The customer record for the signed-in portal user (or 403/404). */
    private function resolveCustomer(Request $request): Customer
    {
        $user = $request->user();
        abort_unless($user?->role === 'customer', 403);

        $customer = Customer::query()->where('user_id', $user->id)->first();
        abort_if($customer === null, 404);

        return $customer;
    }

    /** @return array<string, mixed> */
    private function toDetail(ServiceVisit $visit): array
    {
        $reading = $visit->chemicalReading;

        return [
            'id' => $visit->id,
            'pool' => $visit->pool?->getAttribute('name'),
            'on' => $visit->completed_at?->toDateString(),
            'agent' => $visit->agent?->displayName(),
            'notes' => $visit->notes,
            'reading' => $reading !== null ? [
                'free_chlorine' => $reading->free_chlorine,
                'ph' => $reading->ph,
                'alkalinity' => $reading->alkalinity,
                'calcium_hardness' => $reading->calcium_hardness,
                'cyanuric_acid' => $reading->cyanuric_acid,
                'salt' => $reading->salt,
                'lsi_score' => $reading->lsi_score,
            ] : null,
            'treatments' => $visit->treatments->map(fn ($t): array => [
                'name' => $t->getAttribute('chemical_name'),
                'amount' => (float) $t->amount,
                'unit' => $t->getAttribute('unit'),
            ])->all(),
            'tasks' => $visit->tasks->map(fn ($t): array => [
                'name' => $t->getAttribute('task_name'),
                'done' => (bool) $t->getAttribute('is_completed'),
            ])->all(),
            'photos' => $visit->photos->map(fn ($p): string => Storage::disk('public')->url((string) $p->getAttribute('photo_path')))->all(),
        ];
    }
}
