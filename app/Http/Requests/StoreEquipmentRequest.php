<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/** New equipment: everything an edit accepts, plus the pool it's on. */
class StoreEquipmentRequest extends UpdateEquipmentRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'pool_id' => ['required', Rule::exists('pools', 'id')->where('tenant_id', $this->user()?->tenant_id)],
        ];
    }
}
