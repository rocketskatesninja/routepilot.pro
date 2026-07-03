<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Declarative role backstop at the route layer: `role:agent,tenant_admin` aborts
 * 403 unless the signed-in user's role is one of the listed roles. This mirrors
 * (and never widens) the inline `authorize*` guards each controller already
 * enforces — it's a coarse net so a forgotten inline check can't open a hole,
 * NOT the fine-grained authority (controllers keep enforcing agent-vs-admin,
 * ownership, tenant scope). Runs after `auth`, so the user is always present.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = $request->user()?->role;

        abort_unless($role !== null && in_array($role, $roles, true), 403);

        return $next($request);
    }
}
