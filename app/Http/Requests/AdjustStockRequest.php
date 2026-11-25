<?php

declare(strict_types=1);

namespace App\Http\Requests;

class AdjustStockRequest extends AdminRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:restock,usage,adjustment'],
            'quantity' => ['required', 'numeric', 'min:0', 'max:1000000'],
        ];
    }
}
