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
