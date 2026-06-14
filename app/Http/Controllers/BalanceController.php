<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AddManualCharge;
use App\Actions\GenerateInvoice;
use App\Actions\RecordPayment;
use App\Http\Requests\RecordPaymentRequest;
use App\Http\Requests\StoreManualChargeRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            'photo' => $this->photoUrl($r['customer']->getAttribute('photo_path')),
            'balance' => $r['balance'],
            'pools' => $r['customer']->pools->count(),
        ])->all();

        // The list is computed in the billing service, not a DB query, so it
        // sorts in memory. Default: highest balance first.
        $sortKey = (string) $request->string('sort');
        if (! in_array($sortKey, ['name', 'pools', 'balance'], true)) {
            $sortKey = 'balance';
            $sortDir = 'desc';
        } else {
            $sortDir = strtolower((string) $request->string('dir')) === 'desc' ? 'desc' : 'asc';
        }
        usort($rows, fn (array $a, array $b): int => match ($sortKey) {
            'name' => strcasecmp((string) $a['name'], (string) $b['name']),
            'pools' => $a['pools'] <=> $b['pools'],
            default => $a['balance'] <=> $b['balance'],
        });
        if ($sortDir === 'desc') {
            $rows = array_reverse($rows);
        }
        $sort = ['key' => $sortKey, 'dir' => $sortDir];

        $selected = null;
        $selectedId = $request->integer('selected');
        if ($selectedId > 0) {
            $customer = Customer::query()->find($selectedId);
            if ($customer !== null) {
                $selected = [
                    'id' => $customer->id,
                    'name' => $customer->displayName(),
                    'photo' => $this->photoUrl($customer->getAttribute('photo_path')),
                    ...$billing->breakdownForCustomer($customer),
                    'invoices' => Invoice::query()
                        ->where('customer_id', $customer->id)
                        ->latest('issued_at')->latest('id')->limit(10)->get()
                        ->map(fn (Invoice $i): array => [
                            'id' => $i->id,
                            'number' => $i->number,
                            'status' => $i->status,
                            'total' => (float) $i->total,
                            'balance' => $i->balance(),
                            'issued_on' => $i->issued_at?->toDateString(),
                        ])->all(),
                ];
            }
        }

        return Inertia::render('balances/Index', [
            'balances' => $rows,
            'total' => round((float) $balances->sum(fn (array $r): float => $r['balance']), 2),
            'selected' => $selected,
            'sort' => $sort,
            'canManage' => $request->user()?->role === 'tenant_admin',
            'customers' => $this->customerOptions(),
        ]);
    }

    public function addCharge(StoreManualChargeRequest $request, AddManualCharge $action): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $action->handle($request->validated(), (int) $user->id);

        return back()->with('success', 'Charge added.');
    }

    public function recordPayment(RecordPaymentRequest $request, Customer $customer, RecordPayment $action): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $payment = $action->handle($customer, (string) $request->validated()['method'], (int) $user->id);

        return back()->with('success', $payment !== null ? 'Payment recorded.' : 'Nothing outstanding.');
    }

    public function generateInvoice(Request $request, Customer $customer, GenerateInvoice $action): RedirectResponse
    {
        abort_unless($request->user()?->role === 'tenant_admin', 403);

        $invoice = $action->handle($customer);

        return back()->with('success', $invoice !== null ? "Invoice {$invoice->number} created." : 'Nothing to invoice.');
    }

    /** QuickBooks-friendly CSV of every invoice (tenant-scoped). */
    public function exportCsv(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->role === 'tenant_admin', 403);

        $invoices = Invoice::query()->with('customer:id,first_name,last_name')->latest('issued_at')->latest('id')->get();

        return response()->streamDownload(function () use ($invoices): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['Number', 'Customer', 'Issued', 'Due', 'Subtotal', 'Tax', 'Total', 'Paid', 'Status']);
            foreach ($invoices as $invoice) {
                fputcsv($out, [
                    $invoice->number,
                    $invoice->customer?->displayName() ?? '',
                    $invoice->issued_at?->toDateString() ?? '',
                    $invoice->due_at?->toDateString() ?? '',
                    $invoice->subtotal,
                    $invoice->tax,
                    $invoice->total,
                    $invoice->amount_paid,
                    $invoice->status,
                ]);
            }
            fclose($out);
        }, 'invoices.csv', ['Content-Type' => 'text/csv']);
    }

    /** @return list<array{id: int, name: string}> */
    private function customerOptions(): array
    {
        return Customer::query()
            ->orderBy('first_name')->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (Customer $c): array => ['id' => $c->id, 'name' => $c->displayName()])
            ->all();
    }
}
