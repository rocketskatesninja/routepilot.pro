<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\BillingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A payment the office records — on a customer's account or against one
 * invoice. Any amount up to what's owed (partial payments are fine); with no
 * amount, all of it.
 */
class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'tenant_admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'method' => ['required', 'in:card,cash,check,ach,other'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $amount = $this->input('amount');
            $owed = $this->owed();
            if (is_numeric($amount) && $owed !== null && round((float) $amount, 2) > $owed) {
                $validator->errors()->add('amount', 'That’s more than is owed ($'.number_format($owed, 2).').');
            }
        }];
    }

    /** What the payment can cover: the invoice's balance, or the customer's. */
    private function owed(): ?float
    {
        $invoice = $this->route('invoice');
        $customer = $this->route('customer');

        return match (true) {
            $invoice instanceof Invoice => $invoice->balance(),
            $customer instanceof Customer => app(BillingService::class)->outstandingForCustomer($customer),
            default => null,
        };
    }
}
