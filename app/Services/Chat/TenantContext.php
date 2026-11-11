<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\ChemicalInventory;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Pool;
use App\Models\Route;
use App\Models\ServiceRequest;
use App\Models\ServiceSubscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;

/**
 * The office assistant's live data: a summary of the company right now, never
 * its customer list. Details come from the look-up tools when asked.
 */
class TenantContext
{
    public function build(Tenant $tenant): string
    {
        $id = $tenant->id;
        $today = Tenant::localToday();

        $techs = User::query()->where('tenant_id', $id)->where('role', 'agent')->where('is_active', true)->orderBy('first_name')->get()
            ->map(fn (User $a): string => '- [[agent:'.$a->id.':'.$a->displayName().']]'.((bool) $a->getAttribute('agent_plus') ? ' (Agent Plus)' : '')
                .', '.ServiceSubscription::query()->where('tenant_id', $id)->where('assigned_agent_id', $a->id)->where('status', 'active')->count().' active plans')
            ->join("\n");

        $routes = Route::query()->where('tenant_id', $id)->whereDate('scheduled_date', $today->toDateString())->with('agent', 'stops')->get()
            ->map(fn (Route $r): string => '- '.($r->agent?->displayName() ?? 'Unassigned').': '.$r->stops->count().' stops ('
                .$r->stops->countBy('status')->map(fn (int $n, string $s): string => "{$n} {$s}")->join(', ').')')
            ->join("\n");

        $warnings = array_filter([
            ($n = ServiceSubscription::query()->where('tenant_id', $id)->where('status', 'active')->whereNull('assigned_agent_id')->count()) > 0 ? "{$n} active plans have no tech" : null,
            ($n = ServiceRequest::query()->where('tenant_id', $id)->where('status', 'pending')->count()) > 0 ? "{$n} customer requests are waiting" : null,
            ($n = WorkOrder::query()->where('tenant_id', $id)->where('status', 'open')->where('urgent', true)->count()) > 0 ? "{$n} urgent work orders are open" : null,
            $tenant->customer_billing !== false && ($n = Invoice::query()->where('tenant_id', $id)->where('status', 'overdue')->count()) > 0 ? "{$n} invoices are overdue" : null,
            $tenant->inventory_tracking !== false && ($n = ChemicalInventory::query()->where('tenant_id', $id)->active()->get()->filter->isLowStock()->count()) > 0 ? "{$n} stock items are low" : null,
        ]);

        $switches = collect([
            'customer billing' => $tenant->customer_billing !== false,
            'stock counting' => $tenant->inventory_tracking !== false,
            'prices shown to customers' => (bool) $tenant->show_prices,
        ])->map(fn (bool $on, string $what): string => $what.' '.($on ? 'on' : 'off'))->join(', ');

        return "THE COMPANY RIGHT NOW\n"
            .Customer::query()->where('tenant_id', $id)->count().' customers, '
            .Pool::query()->where('tenant_id', $id)->count().' pools, '
            .ServiceSubscription::query()->where('tenant_id', $id)->where('status', 'active')->count()." active plans.\n"
            ."Features: {$switches}.\n\n"
            ."Techs:\n".($techs !== '' ? $techs : 'No techs yet.')."\n\n"
            .'Today ([[day:'.$today->format('Ymd').":Today's schedule]]):\n".($routes !== '' ? $routes : 'No stops today.')
            .($warnings !== [] ? "\n\nNeeds attention:\n- ".implode("\n- ", $warnings) : '');
    }
}
