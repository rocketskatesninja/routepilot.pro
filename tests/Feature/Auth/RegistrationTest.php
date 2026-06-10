<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_tenant_can_register()
    {
        $response = $this->post('/register', [
            'company' => 'Sunshine Pools',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $tenant = Tenant::where('name', 'Sunshine Pools')->first();
        $this->assertNotNull($tenant);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertSame($tenant->id, $user->tenant_id);
        $this->assertSame('tenant_admin', $user->role);
    }
}
