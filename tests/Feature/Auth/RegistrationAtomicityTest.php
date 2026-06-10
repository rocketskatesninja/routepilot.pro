<?php

use App\Actions\RegisterTenant;
use App\Models\Tenant;
use App\Models\User;

/**
 * Tenant signup must be all-or-nothing: if user creation fails after the
 * tenant row is inserted, the whole transaction rolls back — no orphan
 * tenants left behind.
 */
test('a failed signup rolls back the tenant', function () {
    expect(Tenant::count())->toBe(0);

    // Seed a user with an email that will collide and abort the insert.
    $existingTenant = Tenant::factory()->create();
    User::factory()->for($existingTenant)->create(['email' => 'taken@example.com']);

    $action = app(RegisterTenant::class);

    try {
        $action([
            'company' => 'Doomed Pools',
            'first_name' => 'Will',
            'last_name' => 'Fail',
            'email' => 'taken@example.com', // duplicate → DB unique violation
            'password' => 'password',
        ]);
        $this->fail('Expected the duplicate email to abort registration.');
    } catch (Throwable) {
        // expected
    }

    // The pre-existing tenant remains; the "Doomed Pools" tenant must not.
    expect(Tenant::where('name', 'Doomed Pools')->exists())->toBeFalse();
});
