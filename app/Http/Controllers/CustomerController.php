<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateCustomer;
use App\Actions\GrantPortalAccess;
use App\Actions\UpdateCustomer;
use App\Http\Requests\GrantPortalRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ManualCharge;
use App\Models\Pool;
use App\Models\ServiceVisit;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Customer mutations for the back office. Route-model binding resolves
 * {customer} through the global TenantScope, so a foreign id 404s. Mutations
 * are tenant_admin-only (enforced in the Form Requests / inline).
 */
class CustomerController extends Controller
{
    public function store(StoreCustomerRequest $request, CreateCustomer $action): RedirectResponse
    {
        $action->handle($request->validated());

        return back()->with('success', 'Customer added.');
    }

    public function update(UpdateCustomerRequest $request, Customer $customer, UpdateCustomer $action): RedirectResponse
    {
        $action->handle($customer, $request->validated());

        return back()->with('success', 'Customer updated.');
    }

    public function destroy(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless($request->user()?->role === 'tenant_admin', 403);

        $this->audit($request, 'customer.deleted', $customer);
        $customer->delete();

        return back()->with('success', 'Customer removed.');
    }

    /** GDPR/CCPA — download all of a customer's data as JSON (audited). */
    public function export(Request $request, Customer $customer): JsonResponse
    {
        abort_unless($request->user()?->role === 'tenant_admin', 403);

        $this->audit($request, 'customer.exported', $customer);

        $pools = $customer->pools()->with(['visits' => fn ($q) => $q->where('status', 'completed')->with(['chemicalReading', 'treatments'])])->get();

        $data = [
            'customer' => $customer->only(['id', 'first_name', 'last_name', 'email', 'phone', 'address_line1', 'city', 'state', 'zip', 'created_at']),
            'pools' => $pools->map(fn (Pool $p): array => [
                'name' => $p->name,
                'type' => $p->type,
                'visits' => $p->visits->map(fn (ServiceVisit $v): array => [
                    'completed_on' => $v->completed_at?->toDateString(),
                    'reading' => $v->chemicalReading?->only(['free_chlorine', 'ph', 'alkalinity', 'lsi_score']),
                    'treatments' => $v->treatments->map(fn ($t): array => ['chemical' => $t->getAttribute('chemical_name'), 'amount' => (float) $t->amount, 'unit' => $t->getAttribute('unit')])->all(),
                ])->all(),
            ])->all(),
            'charges' => ManualCharge::query()->where('customer_id', $customer->id)->get()->map(fn (ManualCharge $c): array => ['description' => $c->description, 'amount' => (float) $c->amount, 'on' => $c->getAttribute('occurred_on')?->toDateString()])->all(),
            'invoices' => Invoice::query()->where('customer_id', $customer->id)->get()->map(fn (Invoice $i): array => ['number' => $i->number, 'total' => (float) $i->total, 'status' => $i->status])->all(),
        ];

        return response()->json($data, 200, ['Content-Disposition' => 'attachment; filename="customer-'.$customer->id.'-data.json"']);
    }

    private function audit(Request $request, string $action, Customer $customer): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'model_type' => Customer::class,
            'model_id' => $customer->id,
            'ip_address' => $request->ip(),
        ]);
    }

    public function grantPortal(GrantPortalRequest $request, Customer $customer, GrantPortalAccess $action): RedirectResponse
    {
        if ($customer->user_id !== null) {
            return back()->with('error', 'This customer already has portal access.');
        }
        if ($customer->email === null) {
            return back()->with('error', 'Add an email for this customer first.');
        }
        if (User::query()->where('email', $customer->email)->exists()) {
            return back()->with('error', 'That email is already in use by another login.');
        }

        $action->handle($customer, (string) $request->validated()['password']);

        return back()->with('success', 'Portal access granted.');
    }
}
