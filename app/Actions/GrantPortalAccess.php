<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Give a customer portal access: create a linked customer-role user and
 * point customer.user_id at it. Privilege fields via forceFill.
 */
class GrantPortalAccess
{
    public function handle(Customer $customer, string $password): User
    {
        return DB::transaction(function () use ($customer, $password): User {
            $user = new User;
            $user->fill([
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'password' => $password,
            ]);
            $user->forceFill([
                'tenant_id' => $customer->tenant_id,
                'role' => 'customer',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $user->save();

            $customer->forceFill(['user_id' => $user->id])->save();

            return $user;
        });
    }
}
