<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Restore a customer's soft-deleted portal login — e.g. after the customer
 * deleted their own account and later wants service (or deleted it by mistake).
 * Nothing was cascaded on the soft delete, so restoring brings the login back
 * intact (same customer link + history); the password is preserved unless the
 * admin sets a new one.
 */
class RestorePortalAccess
{
    public function handle(Customer $customer, ?string $password = null): User
    {
        return DB::transaction(function () use ($customer, $password): User {
            /** @var User $user */
            $user = User::withTrashed()->findOrFail($customer->user_id);
            $user->restore();
            if ($password !== null) {
                $user->fill(['password' => $password]);
            }

            // Realign the login email with the contact record in case it changed
            // while the login was gone (skip if another login now holds it).
            if ($customer->email !== null && $customer->email !== $user->email
                && ! User::query()->where('email', $customer->email)->where('id', '!=', $user->id)->exists()) {
                $user->email = $customer->email;
            }

            $user->forceFill(['is_active' => true])->save();

            return $user;
        });
    }
}
