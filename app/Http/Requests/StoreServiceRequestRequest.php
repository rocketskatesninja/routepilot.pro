<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'customer';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $customerId = Customer::query()->where('user_id', $this->user()?->id)->value('id');

        return [
            'type' => ['required', 'in:service,hold'],
            'message' => ['required', 'string', 'max:2000'],
            'pool_id' => ['nullable', Rule::exists('pools', 'id')->where('customer_id', $customerId)],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }
}
