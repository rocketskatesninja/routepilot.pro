<?php

declare(strict_types=1);

namespace App\Http\Requests;

class LogEquipmentServiceRequest extends AdminRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'serviced_on' => ['nullable', 'date'],
            'description' => ['required', 'string', 'max:500'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'bill' => ['boolean'],
        ];
    }
}
