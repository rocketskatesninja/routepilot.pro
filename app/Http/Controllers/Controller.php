<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

abstract class Controller
{
    /** Staff-only guard (tenant_admin / agent of a bound tenant) — the single definition of the staff trust boundary. */
    protected function authorizeStaff(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user !== null && $user->tenant_id !== null && in_array($user->role, ['tenant_admin', 'agent'], true),
            403,
        );
    }

    /** Public URL for a stored photo path, or null when unset. */
    protected function photoUrl(mixed $path): ?string
    {
        return is_string($path) && $path !== '' ? Storage::disk('public')->url($path) : null;
    }
}
