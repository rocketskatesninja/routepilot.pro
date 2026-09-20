<?php

declare(strict_types=1);

use App\Mail\EmailChangeVerificationMail;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
});

/**
 * A customer whose login and contact record share one email.
 *
 * @return array{0: User, 1: Customer}
 */
function customerWithPortal(Tenant $tenant, string $email): array
{
    $user = User::factory()->customer()->for($tenant)->create(['email' => $email]);
    $customer = Customer::factory()->for($tenant)->create(['email' => $email]);
    $customer->forceFill(['user_id' => $user->id])->save();

    return [$user, $customer];
}

test('a customer email change is not applied until it is confirmed', function () {
    Mail::fake();
    [$user, $customer] = customerWithPortal($this->tenant, 'old@portal.test');

    $this->actingAs($user)
        ->patch('/settings/profile', ['first_name' => $user->first_name, 'last_name' => $user->last_name, 'email' => 'new@portal.test'])
        ->assertRedirect('/settings/profile')
        ->assertSessionHas('status', 'email-change-sent');

    expect($user->fresh()?->email)->toBe('old@portal.test');
    expect($customer->fresh()?->email)->toBe('old@portal.test');
    Mail::assertSent(EmailChangeVerificationMail::class, fn (EmailChangeVerificationMail $m): bool => $m->hasTo('new@portal.test'));
});

test('confirming the signed link switches both the login and the contact email', function () {
    [$user, $customer] = customerWithPortal($this->tenant, 'old@portal.test');

    $url = URL::temporarySignedRoute('email-change.confirm', now()->addHour(), ['user' => $user->id, 'email' => 'new@portal.test']);

    $this->get($url)->assertRedirect();

    expect($user->fresh()?->email)->toBe('new@portal.test');
    expect($user->fresh()?->email_verified_at)->not->toBeNull();
    expect($customer->fresh()?->email)->toBe('new@portal.test');
});

test('an unsigned confirm link is rejected and changes nothing', function () {
    [$user] = customerWithPortal($this->tenant, 'old@portal.test');

    $this->get('/settings/email/confirm?user='.$user->id.'&email=hacker@portal.test')->assertForbidden();

    expect($user->fresh()?->email)->toBe('old@portal.test');
});

test('confirm is a graceful no-op when the target email was taken in the meantime', function () {
    [$user, $customer] = customerWithPortal($this->tenant, 'old@portal.test');
    User::factory()->for($this->tenant)->create(['email' => 'taken@portal.test']);

    $url = URL::temporarySignedRoute('email-change.confirm', now()->addHour(), ['user' => $user->id, 'email' => 'taken@portal.test']);
    $this->get($url)->assertRedirect();

    expect($user->fresh()?->email)->toBe('old@portal.test');
    expect($customer->fresh()?->email)->toBe('old@portal.test');
});

test('a staff email change still applies immediately (no verification round-trip)', function () {
    Mail::fake();
    $admin = User::factory()->for($this->tenant)->create(['email' => 'admin-old@portal.test']);

    $this->actingAs($admin)
        ->patch('/settings/profile', ['first_name' => $admin->first_name, 'last_name' => $admin->last_name, 'email' => 'admin-new@portal.test'])
        ->assertRedirect('/settings/profile');

    expect($admin->fresh()?->email)->toBe('admin-new@portal.test');
    Mail::assertNothingSent();
});

test('a tenant admin editing a customer email propagates to the linked login at once', function () {
    $admin = User::factory()->for($this->tenant)->create();
    [$portalUser, $customer] = customerWithPortal($this->tenant, 'before@portal.test');

    $this->actingAs($admin)
        ->patch("/customers/{$customer->id}", [
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'email' => 'after@portal.test',
            'bill_chemicals' => false,
        ])
        ->assertRedirect();

    expect($customer->fresh()?->email)->toBe('after@portal.test');
    expect($portalUser->fresh()?->email)->toBe('after@portal.test');
});

test('a customer email that collides with another login is rejected', function () {
    $admin = User::factory()->for($this->tenant)->create();
    [$portalUser, $customer] = customerWithPortal($this->tenant, 'mine@portal.test');
    User::factory()->for($this->tenant)->create(['email' => 'someoneelse@portal.test']);

    $this->actingAs($admin)
        ->patch("/customers/{$customer->id}", [
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'email' => 'someoneelse@portal.test',
            'bill_chemicals' => false,
        ])
        ->assertSessionHasErrors('email');

    expect($portalUser->fresh()?->email)->toBe('mine@portal.test');
});
