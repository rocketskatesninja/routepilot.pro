<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;
use App\Models\User;
use App\Services\PhotoService;

/**
 * Update a customer's contact/profile fields. Privilege/identity fields
 * (tenant_id, user_id) are never touched here.
 */
class UpdateCustomer
{
    public function __construct(private readonly PhotoService $photos) {}

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

        // Keep the portal login's email in lockstep — an admin editing the
        // contact email is authoritative, so it applies straight away (the
        // request guarantees it's present + unique when a login exists).
        if ($customer->user_id !== null && $customer->email !== null) {
            $user = User::find($customer->user_id);
            if ($user !== null && $user->email !== $customer->email) {
                $user->forceFill(['email' => $customer->email])->save();
            }
        }

        $this->photos->attach($customer, $data['photo'] ?? null, 'photo_path', 'customers');

        return $customer;
    }
}
