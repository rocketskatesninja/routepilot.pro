<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\RegisterTenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterTenantRequest;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/Register');
    }

    /**
     * Handle a tenant self-registration (creates tenant + first admin).
     */
    public function store(RegisterTenantRequest $request, RegisterTenant $registerTenant): RedirectResponse
    {
        $user = $registerTenant($request->validated());

        event(new Registered($user));

        Auth::login($user);

        return to_route('dashboard');
    }
}
