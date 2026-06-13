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
        'stats' => ['label' => 'Stats overview', 'icon' => 'LayoutGrid', 'minW' => 4, 'minH' => 2, 'w' => 12, 'h' => 3, 'roles' => ['tenant_admin', 'agent']],
        'my_route' => ['label' => 'My route', 'icon' => 'Map', 'minW' => 3, 'minH' => 3, 'w' => 6, 'h' => 5, 'roles' => ['tenant_admin']],
        'requests' => ['label' => 'Customer requests', 'icon' => 'Inbox', 'minW' => 3, 'minH' => 3, 'w' => 6, 'h' => 5, 'roles' => ['tenant_admin']],
        'recent_visits' => ['label' => 'Recent visits', 'icon' => 'FileText', 'minW' => 3, 'minH' => 3, 'w' => 12, 'h' => 4, 'roles' => ['tenant_admin']],
    ];

    /** Default starter grid per role (12-col grid). */
    private const DEFAULTS = [
        'tenant_admin' => [
            ['i' => 'stats', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 3],
            ['i' => 'my_route', 'x' => 0, 'y' => 3, 'w' => 6, 'h' => 5],
            ['i' => 'requests', 'x' => 6, 'y' => 3, 'w' => 6, 'h' => 5],
            ['i' => 'recent_visits', 'x' => 0, 'y' => 8, 'w' => 12, 'h' => 4],
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
     * The user's saved layout (sanitized to their role's widgets) or the default.
     *
     * @return list<array<string, int|string>>
     */
    public static function layoutFor(User $user): array
    {
        $role = (string) $user->getAttribute('role');
        $allowed = self::keysForRole($role);

        $saved = $user->getAttribute('dashboard_layout');
        if (is_array($saved) && $saved !== []) {
            $clean = self::sanitize($saved, $allowed);
            if ($clean !== []) {
                return $clean;
            }
        }

        $out = [];
        foreach (self::DEFAULTS[$role] ?? [] as $item) {
            if (in_array($item['i'], $allowed, true)) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * Widgets available to ADD (role-allowed, not already placed).
     *
     * @param  list<string>  $enabled
     * @return list<array<string, mixed>>
     */
    public static function available(User $user, array $enabled): array
    {
        $role = (string) $user->getAttribute('role');
        $out = [];
        foreach (self::CATALOG as $key => $w) {
            if (in_array($role, $w['roles'], true) && ! in_array($key, $enabled, true)) {
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
