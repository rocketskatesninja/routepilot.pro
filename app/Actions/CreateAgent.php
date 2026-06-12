<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Services\PhotoService;
use Illuminate\Http\UploadedFile;

/**
 * Create a field agent. Privilege/identity fields (tenant_id, role,
 * is_active, agent_plus, email_verified_at) are set via forceFill — never
 * mass-assigned. password is hashed by the model cast.
 */
class CreateAgent
{
    public function __construct(private readonly PhotoService $photos) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, int $tenantId): User
    {
        $user = new User;
        $user->fill([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'map_color' => $data['map_color'] ?? null,
            'password' => $data['password'],
        ]);
        $user->forceFill([
            'tenant_id' => $tenantId,
            'role' => 'agent',
            'is_active' => true,
            'agent_plus' => (bool) ($data['agent_plus'] ?? false),
            'email_verified_at' => now(),
        ]);
        $user->save();

        $photo = $data['photo'] ?? null;
        if ($photo instanceof UploadedFile) {
            $user->forceFill(['avatar_path' => $this->photos->store($photo, 'agents')])->save();
        }

        return $user;
    }
}
