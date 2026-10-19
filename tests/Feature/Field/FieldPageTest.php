<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;

test('the field app renders for an agent', function () {
    $agent = User::factory()->agent()->for(Tenant::factory())->create();

    $this->actingAs($agent)->get('/field')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('field/Index'));
});

test('the field app requires authentication', function () {
    $this->get('/field')->assertRedirect('/login');
});

test('the field app renders for an office admin too (for owners who run a route)', function () {
    $admin = User::factory()->for(Tenant::factory())->create();

    $this->actingAs($admin)->get('/field')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('field/Index'));
});

test('customers can\'t open the field app', function () {
    $customer = User::factory()->customer()->for(Tenant::factory())->create();

    $this->actingAs($customer)->get('/field')->assertForbidden();
});
