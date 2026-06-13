<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dashboard\DashboardWidgets;
use App\Models\User;

/**
 * Persist a user's customized dashboard layout for one mode (desktop|mobile),
 * sanitized to the widgets their role may use (the trust boundary lives in
 * DashboardWidgets::sanitize). Only ever the acting user's own layout; the
 * other mode's layout is preserved.
 */
class SaveDashboardLayout
{
    /** @param  array<mixed>  $layout */
    public function handle(User $user, string $mode, array $layout): void
    {
        $mode = in_array($mode, ['desktop', 'mobile'], true) ? $mode : 'desktop';
        $allowed = DashboardWidgets::keysForRole((string) $user->getAttribute('role'));

        $layouts = DashboardWidgets::layoutsFor($user);
        $layouts[$mode] = DashboardWidgets::sanitize($layout, $allowed);

        $user->forceFill(['dashboard_layout' => $layouts])->save();
    }
}
