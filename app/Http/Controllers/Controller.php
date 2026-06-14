<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

abstract class Controller
{
    /**
     * Apply a request-driven sort to a list query, validated against an
     * allowlist (so the public sort key can't reach an arbitrary column). Each
     * allowlist value is either a column name or a Closure(query, dir) for
     * aggregates / multi-column orders. Returns the resolved sort so the view
     * can render the active column's caret.
     *
     * @param  Builder<*>  $query
     * @param  array<string, string|Closure>  $allowed  public key => column | fn(Builder, 'asc'|'desc')
     * @return array{key: string, dir: string}
     */
    protected function applySort(Builder $query, Request $request, array $allowed, string $defaultKey, string $defaultDir = 'asc'): array
    {
        $key = (string) $request->string('sort');
        if ($key === '' || ! array_key_exists($key, $allowed)) {
            $key = $defaultKey;
            $dir = $defaultDir;
        } else {
            $dir = strtolower((string) $request->string('dir')) === 'desc' ? 'desc' : 'asc';
        }

        $target = $allowed[$key];
        if ($target instanceof Closure) {
            $target($query, $dir);
        } else {
            $query->orderBy($target, $dir);
        }

        return ['key' => $key, 'dir' => $dir];
    }

    /** Staff-only guard (tenant_admin / agent of a bound tenant) — the single definition of the staff trust boundary. */
    protected function authorizeStaff(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user !== null && $user->tenant_id !== null && in_array($user->role, ['tenant_admin', 'agent'], true),
            403,
        );
    }

    /** Admin-only guard — the single definition of the manage trust boundary. */
    protected function authorizeAdmin(Request $request): void
    {
        abort_unless($this->canManage($request->user()), 403);
    }

    /** Whether the user may manage (tenant_admin). */
    protected function canManage(?User $user): bool
    {
        return $user?->role === 'tenant_admin';
    }

    /** Public URL for a stored photo path, or null when unset. */
    protected function photoUrl(mixed $path): ?string
    {
        return is_string($path) && $path !== '' ? Storage::disk('public')->url($path) : null;
    }

    /**
     * How many rows a list should request. The front-end measures how many fit
     * the viewport and sends `?perPage=N` (also cached in a cookie so the first
     * paint seeds close to the fit); both are clamped to a sane range.
     */
    protected function perPage(Request $request, int $default = 12, int $max = 40): int
    {
        $n = $request->integer('perPage');
        if ($n <= 0) {
            $cookie = (int) $request->cookie('rp_per_page');
            $n = $cookie > 0 ? $cookie : $default;
        }

        return max(5, min($max, $n));
    }
}
