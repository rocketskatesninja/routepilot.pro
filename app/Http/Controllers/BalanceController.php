<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Back-office Balances screen — accounts receivable. Lists every customer
 * who owes (highest first); the drawer breaks the balance down by unpaid
 * visit and manual charge. Mark-paid / send-invoice are Phase-6 actions
 * (they need the payment + invoice-generation flow).
 */
class BalanceController extends Controller
{
    public function index(Request $request, BillingService $billing): Response
    {
        $this->authorizeStaff($request);

        $balances = $billing->outstandingBalances();
        $rows = $balances->map(fn (array $r): array => [
            'id' => $r['customer']->id,
            'name' => $r['customer']->displayName(),
            'balance' => $r['balance'],
            'pools' => $r['customer']->pools->count(),
        ])->all();

        $selected = null;
        $selectedId = $request->integer('selected');
        if ($selectedId > 0) {
            $customer = Customer::query()->find($selectedId);
            if ($customer !== null) {
                $selected = [
                    'id' => $customer->id,
                    'name' => $customer->displayName(),
                    ...$billing->breakdownForCustomer($customer),
                ];
            }
        }

        return Inertia::render('balances/Index', [
            'balances' => $rows,
            'total' => round((float) $balances->sum(fn (array $r): float => $r['balance']), 2),
            'selected' => $selected,
        ]);
    }

    private function authorizeStaff(Request $request): void
    {
        $user = $request->user();
        abort_unless($user !== null && $user->tenant_id !== null && in_array($user->role, ['tenant_admin', 'agent'], true), 403);
    }
}
