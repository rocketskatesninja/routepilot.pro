<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateAgent;
use App\Actions\UpdateAgent;
use App\Http\Requests\StoreAgentRequest;
use App\Http\Requests\UpdateAgentRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Agent (field tech) management. User is NOT globally tenant-scoped, so every
 * {agent} binding is verified to belong to this tenant + be an agent.
 */
class AgentController extends Controller
{
    public function store(StoreAgentRequest $request, CreateAgent $action): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $action->handle($request->validated(), (int) $user->tenant_id);

        return back()->with('success', 'Agent added.');
    }

    public function update(UpdateAgentRequest $request, User $agent, UpdateAgent $action): RedirectResponse
    {
        $this->authorizeAgent($request, $agent);
        $action->handle($agent, $request->validated());

        return back()->with('success', 'Agent updated.');
    }

    public function destroy(Request $request, User $agent): RedirectResponse
    {
        abort_unless($request->user()?->role === 'tenant_admin', 403);
        $this->authorizeAgent($request, $agent);
        $agent->delete();

        return back()->with('success', 'Agent removed.');
    }

    /** Ensure the bound user is one of this tenant's agents. */
    private function authorizeAgent(Request $request, User $agent): void
    {
        abort_unless($agent->tenant_id === $request->user()?->tenant_id && $agent->role === 'agent', 404);
    }
}
