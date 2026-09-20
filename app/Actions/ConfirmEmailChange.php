<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Apply a verified email change: set the user's login email and, for a customer,
 * keep their linked contact record in lockstep so the login and contact email
 * stay one and the same. Re-checks uniqueness in case the address was claimed
 * between the request and the click.
 */
class ConfirmEmailChange
{
    /** @return bool true if applied; false if the address is now in use elsewhere */
    public function handle(User $user, string $newEmail): bool
    {
        if ($user->email !== $newEmail
            && User::query()->where('email', $newEmail)->where('id', '!=', $user->id)->exists()) {
            return false;
        }

        return DB::transaction(function () use ($user, $newEmail): bool {
            $user->forceFill(['email' => $newEmail, 'email_verified_at' => now()])->save();

            // The confirm link may be opened while signed out, so no tenant is
            // bound — match the customer by its user link, scope-free.
            Customer::withoutGlobalScope(TenantScope::class)
                ->where('user_id', $user->id)
                ->update(['email' => $newEmail]);

            return true;
        });
    }
}
