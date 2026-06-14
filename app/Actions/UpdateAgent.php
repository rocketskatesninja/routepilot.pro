<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Services\PhotoService;

/**
 * Update an agent's profile + the admin-controlled flags (active, Agent+).
 * Role/tenant are never changed here.
 */
class UpdateAgent
{
    public function __construct(private readonly PhotoService $photos) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $agent, array $data): User
    {
        $agent->fill([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'map_color' => $data['map_color'] ?? null,
        ]);
        if (! empty($data['password'])) {
            $agent->fill(['password' => $data['password']]);
        }
        $agent->forceFill([
            'is_active' => (bool) ($data['is_active'] ?? true),
            'agent_plus' => (bool) ($data['agent_plus'] ?? false),
        ]);
        $agent->save();

        $this->photos->attach($agent, $data['photo'] ?? null, 'avatar_path', 'agents');

        return $agent;
    }
}
