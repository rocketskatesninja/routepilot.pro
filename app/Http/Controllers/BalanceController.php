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
use App\Support\OptionLists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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

        $view = (string) $request->string('view');
        if (! in_array($view, ['owing', 'invoices'], true)) {
            $view = 'owing';
        }

        // Owing summary: drives the owing list, the header total, and the tab badge.
        $balances = $billing->outstandingBalances();
        $rows = $balances->map(fn (array $r): array => [
            'id' => $r['customer']->id,
            'name' => $r['customer']->displayName(),
            'photo' => $this->photoUrl($r['customer']->getAttribute('photo_path')),
            'balance' => $r['balance'],
            'pools' => $r['customer']->pools->count(),
        ])->all();

        // The owing list is computed (not a query), so it sorts in memory.
        $owingKey = (string) $request->string('sort');
        if (! in_array($owingKey, ['name', 'pools', 'balance'], true)) {
            $owingKey = 'balance';
            $owingDir = 'desc';
        } else {
            $owingDir = strtolower((string) $request->string('dir')) === 'desc' ? 'desc' : 'asc';
        }
        usort($rows, fn (array $a, array $b): int => match ($owingKey) {
            'name' => strcasecmp((string) $a['name'], (string) $b['name']),
            'pools' => $a['pools'] <=> $b['pools'],
            default => $a['balance'] <=> $b['balance'],
        });
        if ($owingDir === 'desc') {
            $rows = array_reverse($rows);
        }

        // Paginate the (sorted) owing list in memory so it pages like the others.
        $owingPerPage = $this->perPage($request);
        $owingPage = max(1, $request->integer('page'));
        $owing = new LengthAwarePaginator(
            array_slice($rows, ($owingPage - 1) * $owingPerPage, $owingPerPage),
            count($rows),
            $owingPerPage,
            $owingPage,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        // Invoices view: a paginated, sortable, status-filterable list.
        $invoices = null;
        $invoiceSort = null;
        $invoiceStatus = '';
        if ($view === 'invoices') {
            $statusFilter = (string) $request->string('status');
            $valid = in_array($statusFilter, ['draft', 'sent', 'overdue', 'paid'], true);
            $query = Invoice::query()
                ->with('customer:id,first_name,last_name')
                ->when($valid, fn ($q) => $q->where('status', $statusFilter));
            $invoiceSort = $this->applySort($query, $request, [
                'number' => 'number',
                'issued' => 'issued_at',
                'due' => 'due_at',
                'total' => 'total',
                'status' => 'status',
                'customer' => fn ($q, $dir) => $q->orderBy(Customer::query()->select('first_name')->whereColumn('customers.id', 'invoices.customer_id'), $dir),
            ], 'issued', 'desc');
            $invoices = $query->paginate($this->perPage($request))->withQueryString()->through(fn (Invoice $i): array => [
                'id' => $i->id,
                'number' => $i->number,
                'customer' => $i->customer?->displayName(),
                'customer_id' => $i->getAttribute('customer_id'),
                'issued_on' => $i->issued_at?->toDateString(),
                'due_on' => $i->due_at?->toDateString(),
                'total' => (float) $i->total,
                'balance' => $i->balance(),
                'status' => $i->status,
            ]);
            $invoiceStatus = $valid ? $statusFilter : '';
        }

        // Detail pane: an invoice (invoices view) or a customer breakdown (owing).
        $selected = null;
        $selectedId = $request->integer('selected');
        if ($selectedId > 0 && $view === 'invoices') {
            $invoice = Invoice::query()->with(['customer:id,first_name,last_name', 'lineItems'])->find($selectedId);
            if ($invoice !== null) {
                $selected = [
                    'kind' => 'invoice',
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'status' => $invoice->status,
                    'customer' => $invoice->customer?->displayName(),
                    'customer_id' => $invoice->getAttribute('customer_id'),
                    'period_start' => $invoice->period_start?->toDateString(),
                    'period_end' => $invoice->period_end?->toDateString(),
                    'issued_on' => $invoice->issued_at?->toDateString(),
                    'due_on' => $invoice->due_at?->toDateString(),
                    'subtotal' => (float) $invoice->subtotal,
                    'tax' => (float) $invoice->tax,
                    'total' => (float) $invoice->total,
                    'amount_paid' => (float) $invoice->amount_paid,
                    'balance' => $invoice->balance(),
                    'line_items' => $invoice->lineItems->map(fn ($li): array => [
                        'description' => $li->getAttribute('description'),
                        'amount' => (float) $li->getAttribute('amount'),
                    ])->all(),
                ];
            }
        } elseif ($selectedId > 0) {
            $customer = Customer::query()->find($selectedId);
            if ($customer !== null) {
                $selected = [
                    'kind' => 'owing',
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
            'view' => $view,
            'balances' => $owing,
            'invoices' => $invoices,
            'counts' => ['owing' => count($rows), 'invoices' => Invoice::query()->count()],
            'total' => round((float) $balances->sum(fn (array $r): float => $r['balance']), 2),
            'selected' => $selected,
            'sort' => $view === 'invoices' ? $invoiceSort : ['key' => $owingKey, 'dir' => $owingDir],
            'invoiceStatus' => $invoiceStatus,
            'canManage' => $this->canManage($request->user()),
            'customers' => OptionLists::customers(),
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
        $this->authorizeAdmin($request);

        $invoice = $action->handle($customer);

        return back()->with('success', $invoice !== null ? "Invoice {$invoice->number} created." : 'Nothing to invoice.');
    }

    /** QuickBooks-friendly CSV of every invoice (tenant-scoped). */
    public function exportCsv(Request $request): StreamedResponse
    {
        $this->authorizeAdmin($request);

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
}
