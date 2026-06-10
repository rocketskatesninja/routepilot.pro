<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;

/**
 * Update a customer's contact/profile fields. Privilege/identity fields
 * (tenant_id, user_id) are never touched here.
 */
class UpdateCustomer
{
    /**
     * @param  array<string, mixed>  $data  validated customer fields
     */
    public function handle(Customer $customer, array $data): Customer
    {
        $customer->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address_line1' => $data['address_line1'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'zip' => $data['zip'] ?? null,
            'notes' => $data['notes'] ?? null,
            'bill_chemicals' => $data['bill_chemicals'] ?? false,
        ]);

        return $customer;
    }
}
