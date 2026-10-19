<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// A tenant's live schedule feed — staff of that tenant only. ($tenantId arrives
// as a string from the broadcast auth request, so cast both sides.)
Broadcast::channel('tenant.{tenantId}', function (User $user, $tenantId) {
    return $user->isStaff() && (int) $user->tenant_id === (int) $tenantId;
});
