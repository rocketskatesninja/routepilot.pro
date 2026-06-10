<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ManualCharge;

/**
 * Add an ad-hoc charge to a customer's account (raises their balance).
 */
class AddManualCharge
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, int $userId): ManualCharge
    {
        return ManualCharge::create([
            'customer_id' => $data['customer_id'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'taxable' => (bool) ($data['taxable'] ?? true),
            'occurred_on' => $data['occurred_on'] ?? now()->toDateString(),
            'created_by' => $userId,
        ]);
    }
}
