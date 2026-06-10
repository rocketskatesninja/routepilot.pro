<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Create a new tenant and its first tenant_admin user, atomically.
 *
 * Single-purpose invokable Action (coding charter). The whole signup is
 * wrapped in one DB transaction so a half-built tenant can never be left
 * behind if any step fails (registration atomicity — locked by a test).
 * Privilege fields (tenant_id, role) are set via forceFill at this single
 * controlled call site, never mass-assigned.
 */
class RegisterTenant
{
    /**
     * @param  array{company:string,first_name:string,last_name?:string|null,email:string,password:string}  $data
     */
    public function __invoke(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $tenant = Tenant::create([
                'name' => $data['company'],
                'slug' => $this->uniqueSlug($data['company']),
            ]);

            $user = new User;
            $user->fill([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
            $user->forceFill([
                'tenant_id' => $tenant->id,
                'role' => 'tenant_admin',
            ])->save();

            return $user;
        });
    }

    /**
     * Build a unique, non-reserved subdomain slug from the company name.
     */
    protected function uniqueSlug(string $company): string
    {
        $base = Str::slug($company) ?: 'company';
        if (in_array($base, Tenant::RESERVED_SLUGS, true)) {
            $base .= '-co';
        }

        $slug = $base;
        $n = 1;
        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }
}
