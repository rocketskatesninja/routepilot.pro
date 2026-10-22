<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\WorkOrder;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreManualChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'tenant_admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $this->user()?->tenant_id)],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'taxable' => ['boolean'],
            'occurred_on' => ['nullable', 'date'],
            // Billing a work order: this customer's, and billable (WorkOrderPolicy::bill).
            'work_order_id' => ['nullable', 'integer', Rule::exists('work_orders', 'id')->where('tenant_id', $this->user()?->tenant_id), $this->billable(...)],
        ];
    }

    private function billable(string $attribute, mixed $value, Closure $fail): void
    {
        $order = WorkOrder::query()->find($value);
        if ($order === null) {
            return; // the exists rule says so
        }
        if ((int) $order->pool?->customer_id !== (int) $this->input('customer_id')) {
            $fail('That work order is for a different customer.');
        } elseif (Gate::denies('bill', $order)) {
            $fail('That work order is still open, or has already been billed.');
        }
    }
}
