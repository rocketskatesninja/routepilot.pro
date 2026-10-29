<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Put the new Advisories widget on every admin's and tech's saved dashboard (new
 * ones get it from the defaults): at the top, full width, everything else moved
 * down. Once only, so anyone who removes it afterwards keeps it removed.
 */
return new class extends Migration
{
    /** Its height on each layout, in grid rows. */
    private const HEIGHT = ['desktop' => 4, 'mobile' => 5];

    public function up(): void
    {
        DB::table('users')->whereIn('role', ['tenant_admin', 'agent'])->whereNotNull('dashboard_layout')
            ->select(['id', 'dashboard_layout'])->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    $saved = json_decode((string) $user->dashboard_layout, true);
                    if (! is_array($saved)) {
                        continue;
                    }
                    if (array_is_list($saved)) { // the older shape: one desktop layout
                        $next = $this->withAdvisories($saved, 'desktop');
                    } else {
                        $next = $saved;
                        foreach (array_keys(self::HEIGHT) as $mode) {
                            if (is_array($saved[$mode] ?? null)) {
                                $next[$mode] = $this->withAdvisories($saved[$mode], $mode);
                            }
                        }
                    }
                    if ($next !== $saved) {
                        DB::table('users')->where('id', $user->id)->update(['dashboard_layout' => json_encode($next)]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Nothing to undo: the widget stays wherever it is (Customize removes it).
    }

    /**
     * The layout with Advisories on top; unchanged when it's already there, or when
     * the layout is empty (an empty one shows the defaults, which have it).
     *
     * @param  array<int, mixed>  $items
     * @return array<int, mixed>
     */
    private function withAdvisories(array $items, string $mode): array
    {
        if ($items === [] || in_array('advisories', array_column($items, 'i'), true)) {
            return $items;
        }
        $height = self::HEIGHT[$mode];
        $moved = array_map(fn ($item) => is_array($item) && isset($item['y']) ? array_replace($item, ['y' => (int) $item['y'] + $height]) : $item, $items);

        return [['i' => 'advisories', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => $height], ...$moved];
    }
};
