<?php

declare(strict_types=1);

namespace App\Http\Requests;

class UpdateEquipmentRequest extends AdminRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:pump,filter,heater,salt_cell,cleaner,automation,other'],
            'make' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial' => ['nullable', 'string', 'max:255'],
            'installed_on' => ['nullable', 'date'],
            'warranty_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
