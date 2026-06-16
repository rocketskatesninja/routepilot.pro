<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// A tenant's live schedule feed — staff of that tenant only.
Broadcast::channel('tenant.{tenantId}', function (User $user, int $tenantId) {
    return $user->isStaff() && (int) $user->tenant_id === $tenantId;
});
