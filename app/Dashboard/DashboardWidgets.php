<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Models\User;

/**
 * The dashboard widget catalog + per-user layout resolution (modernizes the
 * legacy DashboardSections). Each widget declares its grid sizing + which roles
 * may use it. A layout is a list of grid items {i,x,y,w,h} stored per-user on
 * `users.dashboard_layout`; a null/empty layout falls back to defaults().
 */
class DashboardWidgets
{
    /** key => {label, icon (lucide), minW, minH, w, h (defaults), roles[]}. */
    private const CATALOG = [
        'stats' => ['label' => 'Stats overview', 'icon' => 'LayoutGrid', 'minW' => 4, 'minH' => 2, 'w' => 12, 'h' => 3, 'roles' => ['tenant_admin']],
        'route_map' => ['label' => "Today's route map", 'icon' => 'Map', 'minW' => 4, 'minH' => 4, 'w' => 8, 'h' => 6, 'roles' => ['tenant_admin']],
        'my_route' => ['label' => 'My route', 'icon' => 'Map', 'minW' => 3, 'minH' => 3, 'w' => 6, 'h' => 5, 'roles' => ['tenant_admin']],
        'requests' => ['label' => 'Customer requests', 'icon' => 'Inbox', 'minW' => 3, 'minH' => 3, 'w' => 6, 'h' => 5, 'roles' => ['tenant_admin']],
        'recent_visits' => ['label' => 'Recent activity', 'icon' => 'FileText', 'minW' => 3, 'minH' => 3, 'w' => 12, 'h' => 4, 'roles' => ['tenant_admin']],
        'week_strip' => ['label' => 'Week at a glance', 'icon' => 'CalendarRange', 'minW' => 4, 'minH' => 2, 'w' => 8, 'h' => 3, 'roles' => ['tenant_admin']],
        'today_stops' => ['label' => "Today's stops", 'icon' => 'ListChecks', 'minW' => 3, 'minH' => 3, 'w' => 4, 'h' => 6, 'roles' => ['tenant_admin']],
        'weather' => ['label' => 'Weather', 'icon' => 'CloudSun', 'minW' => 3, 'minH' => 3, 'w' => 4, 'h' => 5, 'roles' => ['tenant_admin', 'agent']],
        'billing_summary' => ['label' => 'Outstanding balances', 'icon' => 'DollarSign', 'minW' => 3, 'minH' => 3, 'w' => 4, 'h' => 5, 'roles' => ['tenant_admin']],
        'notifications' => ['label' => 'Notifications', 'icon' => 'Bell', 'minW' => 3, 'minH' => 3, 'w' => 4, 'h' => 5, 'roles' => ['tenant_admin', 'agent']],

        // agent (field PWA)
        'agent_stats' => ['label' => 'My day', 'icon' => 'LayoutGrid', 'minW' => 4, 'minH' => 2, 'w' => 12, 'h' => 3, 'roles' => ['agent']],
        'agent_route' => ['label' => 'My route', 'icon' => 'Map', 'minW' => 3, 'minH' => 3, 'w' => 8, 'h' => 6, 'roles' => ['agent']],
        'agent_visits' => ['label' => 'My recent activity', 'icon' => 'FileText', 'minW' => 3, 'minH' => 3, 'w' => 8, 'h' => 4, 'roles' => ['agent']],

        // customer (portal)
        'my_pools' => ['label' => 'My pools', 'icon' => 'Waves', 'minW' => 3, 'minH' => 3, 'w' => 12, 'h' => 4, 'roles' => ['customer']],
        'next_visit' => ['label' => 'Next visit', 'icon' => 'CalendarDays', 'minW' => 3, 'minH' => 2, 'w' => 6, 'h' => 3, 'roles' => ['customer']],
        'account_balance' => ['label' => 'Account balance', 'icon' => 'Banknote', 'minW' => 3, 'minH' => 2, 'w' => 6, 'h' => 3, 'roles' => ['customer']],
        'customer_visits' => ['label' => 'Recent activity', 'icon' => 'FileText', 'minW' => 3, 'minH' => 3, 'w' => 12, 'h' => 4, 'roles' => ['customer']],

        // super_admin (platform)
        'platform_stats' => ['label' => 'Platform overview', 'icon' => 'LayoutGrid', 'minW' => 4, 'minH' => 2, 'w' => 12, 'h' => 3, 'roles' => ['super_admin']],
        'recent_tenants' => ['label' => 'Recent tenants', 'icon' => 'Building2', 'minW' => 3, 'minH' => 3, 'w' => 12, 'h' => 5, 'roles' => ['super_admin']],
    ];

    /** Default starter grid per role, desktop (12-col grid). */
    private const DEFAULTS = [
        'tenant_admin' => [
            ['i' => 'stats', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 3],
            ['i' => 'route_map', 'x' => 0, 'y' => 3, 'w' => 8, 'h' => 6],
            ['i' => 'requests', 'x' => 8, 'y' => 3, 'w' => 4, 'h' => 6],
            ['i' => 'my_route', 'x' => 0, 'y' => 9, 'w' => 6, 'h' => 5],
            ['i' => 'recent_visits', 'x' => 6, 'y' => 9, 'w' => 6, 'h' => 5],
        ],
        'agent' => [
            ['i' => 'agent_stats', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 3],
            ['i' => 'agent_route', 'x' => 0, 'y' => 3, 'w' => 8, 'h' => 6],
            ['i' => 'weather', 'x' => 8, 'y' => 3, 'w' => 4, 'h' => 6],
            ['i' => 'agent_visits', 'x' => 0, 'y' => 9, 'w' => 8, 'h' => 4],
            ['i' => 'notifications', 'x' => 8, 'y' => 9, 'w' => 4, 'h' => 4],
        ],
        'customer' => [
            ['i' => 'my_pools', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 4],
            ['i' => 'next_visit', 'x' => 0, 'y' => 4, 'w' => 6, 'h' => 3],
            ['i' => 'account_balance', 'x' => 6, 'y' => 4, 'w' => 6, 'h' => 3],
            ['i' => 'customer_visits', 'x' => 0, 'y' => 7, 'w' => 12, 'h' => 4],
        ],
        'super_admin' => [
            ['i' => 'platform_stats', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 3],
            ['i' => 'recent_tenants', 'x' => 0, 'y' => 3, 'w' => 12, 'h' => 5],
        ],
    ];

    /** Default starter grid per role, mobile — widgets stacked full-width. */
    private const DEFAULTS_MOBILE = [
        'tenant_admin' => [
            ['i' => 'stats', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 5],
            ['i' => 'route_map', 'x' => 0, 'y' => 5, 'w' => 12, 'h' => 6],
            ['i' => 'requests', 'x' => 0, 'y' => 11, 'w' => 12, 'h' => 5],
            ['i' => 'my_route', 'x' => 0, 'y' => 16, 'w' => 12, 'h' => 5],
            ['i' => 'recent_visits', 'x' => 0, 'y' => 21, 'w' => 12, 'h' => 4],
        ],
        'agent' => [
            ['i' => 'agent_stats', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 4],
            ['i' => 'agent_route', 'x' => 0, 'y' => 4, 'w' => 12, 'h' => 7],
            ['i' => 'weather', 'x' => 0, 'y' => 11, 'w' => 12, 'h' => 5],
            ['i' => 'agent_visits', 'x' => 0, 'y' => 16, 'w' => 12, 'h' => 4],
            ['i' => 'notifications', 'x' => 0, 'y' => 20, 'w' => 12, 'h' => 5],
        ],
        'customer' => [
            ['i' => 'my_pools', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 4],
            ['i' => 'next_visit', 'x' => 0, 'y' => 4, 'w' => 12, 'h' => 3],
            ['i' => 'account_balance', 'x' => 0, 'y' => 7, 'w' => 12, 'h' => 3],
            ['i' => 'customer_visits', 'x' => 0, 'y' => 10, 'w' => 12, 'h' => 4],
        ],
        'super_admin' => [
            ['i' => 'platform_stats', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 5],
            ['i' => 'recent_tenants', 'x' => 0, 'y' => 5, 'w' => 12, 'h' => 6],
        ],
    ];

    /**
     * Display metadata for every widget (label/icon + sizing) — a lookup the
     * front-end grid uses to render a placed widget's card chrome + size it.
     *
     * @return array<string, array{label: string, icon: string, w: int, h: int, minW: int, minH: int}>
     */
    public static function meta(): array
    {
        $out = [];
        foreach (self::CATALOG as $key => $w) {
            $out[$key] = ['label' => $w['label'], 'icon' => $w['icon'], 'w' => $w['w'], 'h' => $w['h'], 'minW' => $w['minW'], 'minH' => $w['minH']];
        }

        return $out;
    }

    /**
     * Widget keys a role may use.
     *
     * @return list<string>
     */
    public static function keysForRole(string $role): array
    {
        $out = [];
        foreach (self::CATALOG as $key => $w) {
            if (in_array($role, $w['roles'], true)) {
                $out[] = $key;
            }
        }

        return $out;
    }

    /**
     * The user's desktop + mobile layouts (sanitized to their role's widgets),
     * or the per-mode defaults. Tolerates the legacy flat-array shape (treated
     * as the desktop layout).
     *
     * @return array{desktop: list<array<string, int|string>>, mobile: list<array<string, int|string>>}
     */
    public static function layoutsFor(User $user): array
    {
        $role = (string) $user->getAttribute('role');
        $allowed = self::keysForRole($role);
        $saved = $user->getAttribute('dashboard_layout');

        // Legacy flat-array shape → treat as the desktop layout.
        if (is_array($saved) && array_is_list($saved)) {
            $desktop = self::sanitize($saved, $allowed);

            return [
                'desktop' => $desktop !== [] ? $desktop : self::defaultLayout($role, $allowed, 'desktop'),
                'mobile' => self::defaultLayout($role, $allowed, 'mobile'),
            ];
        }

        $savedDesktop = is_array($saved) && is_array($saved['desktop'] ?? null) ? self::sanitize($saved['desktop'], $allowed) : [];
        $savedMobile = is_array($saved) && is_array($saved['mobile'] ?? null) ? self::sanitize($saved['mobile'], $allowed) : [];

        return [
            'desktop' => $savedDesktop !== [] ? $savedDesktop : self::defaultLayout($role, $allowed, 'desktop'),
            'mobile' => $savedMobile !== [] ? $savedMobile : self::defaultLayout($role, $allowed, 'mobile'),
        ];
    }

    /**
     * The per-mode default layout for a role, role-filtered.
     *
     * @param  list<string>  $allowed
     * @return list<array<string, int|string>>
     */
    private static function defaultLayout(string $role, array $allowed, string $mode): array
    {
        $source = $mode === 'mobile' ? (self::DEFAULTS_MOBILE[$role] ?? []) : (self::DEFAULTS[$role] ?? []);

        $out = [];
        foreach ($source as $item) {
            if (in_array($item['i'], $allowed, true)) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * Every widget a role may place, with display meta (the front-end filters
     * out the ones already in the active layout).
     *
     * @return list<array<string, mixed>>
     */
    public static function palette(User $user): array
    {
        $role = (string) $user->getAttribute('role');
        $out = [];
        foreach (self::CATALOG as $key => $w) {
            if (in_array($role, $w['roles'], true)) {
                $out[] = ['key' => $key, 'label' => $w['label'], 'icon' => $w['icon'], 'w' => $w['w'], 'h' => $w['h'], 'minW' => $w['minW'], 'minH' => $w['minH']];
            }
        }

        return $out;
    }

    /**
     * The trust boundary on save: known + role-allowed keys only, deduped,
     * clamped to the 12-col grid (sizes ≥ the widget's min).
     *
     * @param  array<mixed>  $layout
     * @param  list<string>  $allowed
     * @return list<array<string, int|string>>
     */
    public static function sanitize(array $layout, array $allowed): array
    {
        $out = [];
        $seen = [];
        foreach ($layout as $item) {
            if (! is_array($item)) {
                continue;
            }
            $key = $item['i'] ?? null;
            if (! is_string($key) || ! in_array($key, $allowed, true) || isset($seen[$key]) || ! isset(self::CATALOG[$key])) {
                continue;
            }
            $seen[$key] = true;
            $w = self::CATALOG[$key];
            $width = self::clampInt($item['w'] ?? null, $w['minW'], 12, $w['w']);
            $out[] = [
                'i' => $key,
                'x' => self::clampInt($item['x'] ?? null, 0, 12 - $width, 0),
                'y' => self::clampInt($item['y'] ?? null, 0, 9999, 0),
                'w' => $width,
                'h' => self::clampInt($item['h'] ?? null, $w['minH'], 60, $w['h']),
            ];
        }

        return $out;
    }

    private static function clampInt(mixed $v, int $min, int $max, int $default): int
    {
        $n = is_int($v) ? $v : (is_numeric($v) ? (int) $v : $default);

        return max($min, min($max, $n));
    }
}
