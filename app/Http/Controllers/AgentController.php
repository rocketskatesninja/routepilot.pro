<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateAgent;
use App\Actions\UpdateAgent;
use App\Http\Requests\StoreAgentRequest;
use App\Http\Requests\UpdateAgentRequest;
use App\Models\AuditLog;
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

        // Audit only when a privilege flag actually changes — not for routine
        // profile edits (name / phone / colour).
        $before = $this->privilegeState($agent);
        $action->handle($agent, $request->validated());
        $after = $this->privilegeState($agent->refresh());
        if ($before !== $after) {
            AuditLog::record($request->user(), 'agent.privilege_changed', $agent, ['from' => $before, 'to' => $after]);
        }

        return back()->with('success', 'Agent updated.');
    }

    public function destroy(Request $request, User $agent): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $this->authorizeAgent($request, $agent);

        AuditLog::record($request->user(), 'agent.deleted', $agent);
        $agent->delete();

        return back()->with('success', 'Agent removed.');
    }

    /**
     * The privilege-relevant flags on an agent (the fields whose change is
     * worth an audit row).
     *
     * @return array{is_active: bool, agent_plus: bool}
     */
    private function privilegeState(User $agent): array
    {
        return [
            'is_active' => (bool) $agent->getAttribute('is_active'),
            'agent_plus' => (bool) $agent->getAttribute('agent_plus'),
        ];
    }

    /**
     * Set an agent's route colour from the schedule map (inline swatch). A
     * single sanitized hex field; the redirect refreshes the day's marker
     * colours. Tenant-scoped via authorizeAgent.
     */
    public function updateColor(Request $request, User $agent): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $this->authorizeAgent($request, $agent);

        $validated = $request->validate([
            'map_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $agent->fill(['map_color' => strtolower($validated['map_color'])])->save();

        return back();
    }

    /** Ensure the bound user is one of this tenant's agents. */
    private function authorizeAgent(Request $request, User $agent): void
    {
        abort_unless($agent->tenant_id === $request->user()?->tenant_id && $agent->role === 'agent', 404);
    }
}
