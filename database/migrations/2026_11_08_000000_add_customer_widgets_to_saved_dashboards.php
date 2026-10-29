<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Put the new "Pay your bill" and "Request a service" widgets on every customer's
 * saved dashboard (new ones get them from the defaults), at the end so nothing
 * they arranged moves. Once only, so a customer who removes one keeps it removed.
 */
return new class extends Migration
{
    /** Where each goes on each layout: [x, w, h] (stacked at the bottom on a phone). */
    private const PLACE = [
        'desktop' => ['pay_bill' => [0, 6, 4], 'request_service' => [6, 6, 5]],
        'mobile' => ['pay_bill' => [0, 12, 4], 'request_service' => [0, 12, 5]],
    ];

    public function up(): void
    {
        DB::table('users')->where('role', 'customer')->whereNotNull('dashboard_layout')
            ->select(['id', 'dashboard_layout'])->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    $saved = json_decode((string) $user->dashboard_layout, true);
                    if (! is_array($saved)) {
                        continue;
                    }
                    if (array_is_list($saved)) { // the older shape: one desktop layout
                        $next = $this->withWidgets($saved, 'desktop');
                    } else {
                        $next = $saved;
                        foreach (array_keys(self::PLACE) as $mode) {
                            if (is_array($saved[$mode] ?? null)) {
                                $next[$mode] = $this->withWidgets($saved[$mode], $mode);
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
        // Nothing to undo: the widgets stay wherever they are (Customize removes them).
    }

    /**
     * The layout with whichever of the two it lacks added below everything; an empty
     * layout is left alone (it shows the defaults, which have them).
     *
     * @param  array<int, mixed>  $items
     * @return array<int, mixed>
     */
    private function withWidgets(array $items, string $mode): array
    {
        if ($items === []) {
            return $items;
        }
        $have = array_column($items, 'i');
        $bottom = max(array_map(fn ($i) => is_array($i) ? (int) ($i['y'] ?? 0) + (int) ($i['h'] ?? 0) : 0, $items));
        foreach (self::PLACE[$mode] as $key => [$x, $w, $h]) {
            if (in_array($key, $have, true)) {
                continue;
            }
            $items[] = ['i' => $key, 'x' => $x, 'y' => $bottom, 'w' => $w, 'h' => $h];
            if ($mode === 'mobile') {
                $bottom += $h; // a phone's widgets stack
            }
        }

        return $items;
    }
};
