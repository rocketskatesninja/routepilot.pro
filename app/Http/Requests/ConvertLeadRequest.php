<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * Turning a website lead into a customer: the add-customer form, with their
 * first pool required (a request is scheduled on a pool) and no photo.
 */
class ConvertLeadRequest extends StoreCustomerRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['photo']);
        $rules['pool_name'] = ['required', 'string', 'max:255'];

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['pool_name.required' => 'Give their pool a name, e.g. "Backyard pool".'];
    }
}
