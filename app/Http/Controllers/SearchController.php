<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Pool;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backing endpoint for the ⌘K command palette. Returns a few grouped, tenant-
 * scoped matches (customers, pools, agents). Staff-only; each result carries the
 * URL the palette navigates to. Customers/Pools are tenant-scoped by the global
 * scope; User is not, so agents are filtered by tenant_id explicitly.
 */
class SearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);

        $q = trim((string) $request->string('q'));
        if (mb_strlen($q) < 2) {
            return response()->json(['groups' => []]);
        }
        $like = '%'.$q.'%';
        $tenantId = (int) $request->user()?->tenant_id;

        $customers = Customer::query()
            ->where(fn ($w) => $w->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('email', 'like', $like))
            ->orderBy('first_name')->limit(6)->get()
            ->map(fn (Customer $c): array => [
                'type' => 'customer',
                'label' => $c->displayName(),
                'sublabel' => $c->getAttribute('email') ?? $c->getAttribute('city'),
                'url' => '/people?selected='.$c->id.'&selected_type=customer',
            ])->all();

        $pools = Pool::query()
            ->where('name', 'like', $like)
            ->orderBy('name')->limit(6)->get()
            ->map(fn (Pool $p): array => [
                'type' => 'pool',
                'label' => (string) $p->getAttribute('name'),
                'sublabel' => null,
                'url' => '/pools?selected='.$p->id,
            ])->all();

        $agents = User::query()
            ->where('tenant_id', $tenantId)->where('role', 'agent')
            ->where(fn ($w) => $w->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('email', 'like', $like))
            ->orderBy('first_name')->limit(6)->get()
            ->map(fn (User $u): array => [
                'type' => 'agent',
                'label' => $u->displayName(),
                'sublabel' => $u->getAttribute('email'),
                'url' => '/people?selected='.$u->id.'&selected_type=agent',
            ])->all();

        $groups = [];
        if ($customers !== []) {
            $groups[] = ['label' => 'Customers', 'items' => $customers];
        }
        if ($pools !== []) {
            $groups[] = ['label' => 'Pools', 'items' => $pools];
        }
        if ($agents !== []) {
            $groups[] = ['label' => 'Agents', 'items' => $agents];
        }

        return response()->json(['groups' => $groups]);
    }
}
