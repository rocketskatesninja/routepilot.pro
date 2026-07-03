<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Apply a super-admin's platform-billing change to a tenant: the complimentary
 * comp (`billing_free` + note) and the trial end date. All three are privilege
 * fields (not `$fillable`) so they're set via forceFill at this guarded call
 * site, wrapped in a transaction and audited.
 */
class UpdateTenantBilling
{
    /**
     * @param  array{billing_free: bool, billing_note?: string|null, trial_ends_at?: string|null}  $data
     */
    public function handle(User $actor, Tenant $tenant, array $data): void
    {
        DB::transaction(function () use ($actor, $tenant, $data): void {
            $before = $this->snapshot($tenant);

            $tenant->forceFill([
                'billing_free' => $data['billing_free'],
                'billing_note' => $data['billing_note'] ?? null,
                'trial_ends_at' => filled($data['trial_ends_at'] ?? null)
                    ? Carbon::parse((string) $data['trial_ends_at'])->endOfDay()
                    : null,
            ])->save();

            AuditLog::record($actor, 'tenant.billing_updated', $tenant, [
                'from' => $before,
                'to' => $this->snapshot($tenant->refresh()),
            ]);
        });
    }

    /** @return array{billing_free: bool, trial_ends_at: string|null} */
    private function snapshot(Tenant $tenant): array
    {
        return [
            'billing_free' => $tenant->billing_free,
            'trial_ends_at' => $tenant->trial_ends_at?->toDateString(),
        ];
    }
}
