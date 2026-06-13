<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dashboard\DashboardWidgets;
use App\Models\User;

/**
 * Persist a user's customized dashboard layout — sanitized to the widgets their
 * role may use (the trust boundary lives in DashboardWidgets::sanitize). Only
 * ever the acting user's own layout.
 */
class SaveDashboardLayout
{
    /** @param  array<mixed>  $layout */
    public function handle(User $user, array $layout): void
    {
        $allowed = DashboardWidgets::keysForRole((string) $user->getAttribute('role'));
        $user->forceFill(['dashboard_layout' => DashboardWidgets::sanitize($layout, $allowed)])->save();
    }
}
