<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AddManualCharge;
use App\Actions\GenerateInvoice;
use App\Actions\RecordPayment;
use App\Actions\SendInvoice;
use App\Http\Requests\RecordPaymentRequest;
use App\Http\Requests\StoreManualChargeRequest;
use App\Models\AuditLog;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Services\BillingService;
use App\Support\OptionLists;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Back-office Balances screen — accounts receivable. Two views: who owes
 * (highest first; the side panel breaks a balance down by unpaid visit and
 * charge) and the invoices list (the side panel shows one invoice). Plus the
 * money actions: add a charge, record a payment, invoice a customer now.
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
        [$owing, $owingSort] = $this->owingPage($request, $balances);
        [$invoices, $invoiceSort, $invoiceStatus] = $view === 'invoices' ? $this->invoicePage($request) : [null, null, ''];

        $selectedId = $request->integer('selected');
        $selected = match (true) {
            $selectedId <= 0 => null,
            $view === 'invoices' => $this->invoiceDetail($selectedId, $billing),
            default => $this->owingDetail($selectedId, $billing),
        };

        return Inertia::render('balances/Index', [
            'view' => $view,
            'balances' => $owing,
            'invoices' => $invoices,
            'counts' => ['owing' => $balances->count(), 'invoices' => Invoice::query()->count()],
            'total' => round((float) $balances->sum(fn (array $r): float => $r['balance']), 2),
            'selected' => $selected,
            'sort' => $view === 'invoices' ? $invoiceSort : $owingSort,
            'invoiceStatus' => $invoiceStatus,
            'canManage' => $this->canManage($request->user()),
            'customers' => OptionLists::customers(),
        ]);
    }

    /**
     * The owing list, sorted and paged in memory (balances are computed, not
     * queried).
     *
     * @param  Collection<int, array{customer: Customer, balance: float}>  $balances
     * @return array{0: LengthAwarePaginator<int, covariant array<string, mixed>>, 1: array{key: string, dir: string}}
     */
    private function owingPage(Request $request, Collection $balances): array
    {
        $customers = (new EloquentCollection($balances->pluck('customer')->all()))->loadCount('pools');
        // The saved default cards, for the Autopay column, in one query.
        $cards = PaymentMethod::query()->whereIn('id', $customers->pluck('default_payment_method_id')->filter()->unique())->get()->keyBy('id');

        $rows = $balances->map(function (array $r) use ($cards): array {
            $c = $r['customer'];
            $pm = $cards->get($c->getAttribute('default_payment_method_id'));

            return [
                'id' => $c->id,
                'name' => $c->displayName(),
                'photo' => $this->photoUrl($c->getAttribute('photo_path')),
                'balance' => $r['balance'],
                'pools' => (int) $c->getAttribute('pools_count'),
                'autopay' => (bool) $c->getAttribute('autopay_enabled'),
                'card' => $pm !== null
                    ? trim(ucfirst((string) $pm->getAttribute('brand')).' •••• '.$pm->getAttribute('last4'))
                    : null,
            ];
        })->all();

        $key = (string) $request->string('sort');
        if (! in_array($key, ['name', 'pools', 'balance'], true)) {
            [$key, $dir] = ['balance', 'desc'];
        } else {
            $dir = strtolower((string) $request->string('dir')) === 'desc' ? 'desc' : 'asc';
        }
        usort($rows, fn (array $a, array $b): int => match ($key) {
            'name' => strcasecmp((string) $a['name'], (string) $b['name']),
            'pools' => $a['pools'] <=> $b['pools'],
            default => $a['balance'] <=> $b['balance'],
        });
        if ($dir === 'desc') {
            $rows = array_reverse($rows);
        }

        $perPage = $this->perPage($request);
        $page = max(1, $request->integer('page'));
        $paginator = new LengthAwarePaginator(array_slice($rows, ($page - 1) * $perPage, $perPage), count($rows), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()]);

        return [$paginator, ['key' => $key, 'dir' => $dir]];
    }

    /**
     * The invoices list: paginated, sortable, filterable by status.
     *
     * @return array{0: LengthAwarePaginator<int, covariant array<string, mixed>>, 1: array{key: string, dir: string}, 2: string}
     */
    private function invoicePage(Request $request): array
    {
        $status = (string) $request->string('status');
        $valid = in_array($status, ['draft', 'sent', 'overdue', 'paid', 'void'], true);
        $query = Invoice::query()
            ->with('customer:id,first_name,last_name')
            ->when($valid, fn ($q) => $q->where('status', $status));
        $sort = $this->applySort($query, $request, [
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
            'issued_on' => Tenant::localDate($i->issued_at),
            'due_on' => $i->due_at?->toDateString(),
            'total' => (float) $i->total,
            'balance' => $i->balance(),
            'status' => $i->status,
        ]);

        return [$invoices, $sort, $valid ? $status : ''];
    }

    /** @return array<string, mixed>|null */
    private function invoiceDetail(int $id, BillingService $billing): ?array
    {
        $invoice = Invoice::query()->with(['customer:id,first_name,last_name,email', 'charges'])->find($id);
        if ($invoice === null) {
            return null;
        }

        return [
            'kind' => 'invoice',
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status,
            'customer' => $invoice->customer?->displayName(),
            'customer_id' => $invoice->getAttribute('customer_id'),
            'has_email' => filled($invoice->customer?->email),
            'period_start' => $invoice->period_start?->toDateString(),
            'period_end' => $invoice->period_end?->toDateString(),
            'issued_on' => Tenant::localDate($invoice->issued_at),
            'due_on' => $invoice->due_at?->toDateString(),
            'subtotal' => (float) $invoice->subtotal,
            'tax' => (float) $invoice->tax,
            'total' => (float) $invoice->total,
            'amount_paid' => (float) $invoice->amount_paid,
            'balance' => $invoice->balance(),
            'line_items' => $invoice->charges->map(fn (Charge $c): array => [
                'description' => $c->description,
                'amount' => (float) $c->amount,
            ])->all(),
            // What paid it — including payments on account, which pay the oldest bills first.
            'paid_by' => array_map(fn (array $row): array => $row['source'] instanceof Payment
                ? [...$this->paymentRow($row['source']), 'applied' => $row['applied']]
                : ['kind' => 'credit', 'on' => $row['source']->occurred_on->toDateString(), 'description' => $row['source']->description, 'amount' => -$row['source']->total(), 'applied' => $row['applied']],
                $billing->paidBy($invoice)),
        ];
    }

    /**
     * One payment as the panels show it: when, how, how much, and who recorded
     * it (none for card payments made online). A declined autopay try is dated
     * when it was tried.
     *
     * @return array<string, mixed>
     */
    private function paymentRow(Payment $p): array
    {
        return [
            'kind' => 'payment',
            'on' => Tenant::localDate($p->paid_at ?? $p->created_at),
            'method' => $p->method,
            'amount' => (float) $p->amount,
            'declined' => $p->status === 'failed',
            'by' => $p->recorder?->firstName(),
            'invoice' => $p->invoice?->number,
        ];
    }

    /** @return array<string, mixed>|null */
    private function owingDetail(int $customerId, BillingService $billing): ?array
    {
        $customer = Customer::query()->find($customerId);
        if ($customer === null) {
            return null;
        }

        return [
            'kind' => 'owing',
            'id' => $customer->id,
            'name' => $customer->displayName(),
            'photo' => $this->photoUrl($customer->getAttribute('photo_path')),
            ...$billing->breakdownForCustomer($customer),
            'payments' => Payment::query()->where('customer_id', $customer->id)->whereIn('status', ['succeeded', 'failed'])
                ->with(['recorder:id,first_name,last_name', 'invoice:id,number'])
                ->latest('created_at')->latest('id')->limit(10)->get()
                ->map(fn (Payment $p): array => $this->paymentRow($p))->all(),
            'invoices' => Invoice::query()
                ->where('customer_id', $customer->id)
                ->latest('issued_at')->latest('id')->limit(10)->get()
                ->map(fn (Invoice $i): array => [
                    'id' => $i->id,
                    'number' => $i->number,
                    'status' => $i->status,
                    'total' => (float) $i->total,
                    'balance' => $i->balance(),
                    'issued_on' => Tenant::localDate($i->issued_at),
                ])->all(),
        ];
    }

    public function addCharge(StoreManualChargeRequest $request, AddManualCharge $action): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $charge = $action->handle($request->validated(), (int) $user->id);
        AuditLog::record($user, 'charge.created', $charge, ['amount' => (float) $charge->amount, 'customer_id' => $charge->customer_id]);

        return back()->with('success', 'Charge added.');
    }

    public function recordPayment(RecordPaymentRequest $request, Customer $customer, RecordPayment $action, BillingService $billing): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $amount = $request->validated()['amount'] ?? null;
        $payment = $action->handle($customer, (string) $request->validated()['method'], (int) $user->id, null, $amount !== null ? (float) $amount : null);
        if ($payment === null) {
            return back()->with('error', 'Nothing is owed.');
        }
        AuditLog::record($user, 'payment.recorded', $payment, ['customer_id' => $customer->id, 'amount' => (float) $payment->amount, 'method' => (string) $payment->method]);

        $left = $billing->outstandingForCustomer($customer);

        return back()->with('success', 'Payment of $'.number_format((float) $payment->amount, 2).' recorded. '
            .($left > 0 ? '$'.number_format($left, 2).' is still owed.' : 'They’re all paid up.'));
    }

    /** "Invoice now": bill what isn't on an invoice yet and email it, like the monthly run. */
    public function generateInvoice(Request $request, Customer $customer, GenerateInvoice $generate, SendInvoice $send): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $invoice = $generate->handle($customer);
        if ($invoice === null) {
            return back()->with('error', 'Everything this customer owes is already on an invoice.');
        }
        AuditLog::record($request->user(), 'invoice.created', $invoice, ['customer_id' => $customer->id, 'total' => (float) $invoice->total]);

        $email = $send->handle($invoice);

        return back()->with('success', $email !== null
            ? "Invoice {$invoice->number} emailed to {$email}."
            : "Invoice {$invoice->number} created. There's no email address on file, so it's a draft. Download the PDF to send it.");
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
