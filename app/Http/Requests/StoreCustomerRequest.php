<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Pool;
use Illuminate\Validation\Rule;

/** A new customer: everything an edit accepts, plus an optional first pool. */
class StoreCustomerRequest extends UpdateCustomerRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'pool_name' => ['nullable', 'string', 'max:255'],
            'pool_type' => ['nullable', Rule::in(Pool::TYPES)],
            'pool_volume' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'pool_sanitizer' => ['nullable', Rule::in(Pool::SANITIZERS)],
        ];
    }
}
