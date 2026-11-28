<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Make the database agree with the app about what time it is.
 *
 * The app keeps UTC. The database connection never said so, so MySQL took every time it was
 * handed as the server's own local time. On production that is harmless, because the
 * production server's clock IS UTC. On the standby (serv) the clock is US Eastern: the same
 * digits came back, so nothing looked wrong, but a TIMESTAMP column is an instant, and local
 * time has an hour every March that does not exist — 02:00 to 03:00 on the night the clocks
 * go forward. A UTC time with those digits (nine to ten in the evening, Eastern) was refused
 * outright ("Incorrect datetime value"). If production ever failed over to the standby, a
 * technician finishing a visit in that hour would have got an error instead of a saved visit.
 *
 * The connection now declares UTC (config/database.php). On a server whose clock is not UTC,
 * that alone would shift every time already stored, so the stored values are carried across:
 *
 *   1. Under the old session, each TIMESTAMP becomes a DATETIME. A DATETIME has no zone; it
 *      keeps exactly the digits that were being read — the UTC digits the app wrote.
 *   2. Under a UTC session, each becomes a TIMESTAMP again, and those digits are now taken
 *      as the UTC they always were.
 *
 * Every value reads the same before and after; no arithmetic is done on any of them.
 *
 * WHERE THE SERVER'S CLOCK IS ALREADY UTC — PRODUCTION — THIS DOES NOTHING AT ALL. It checks
 * first and returns without altering a table. It also does nothing on SQLite (the tests).
 *
 * Where it does run, run it with the site down and the worker stopped: between the two
 * steps, and until the new config is loaded, a running process would read and write the
 * old way.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->carry(from: 'SYSTEM', to: '+00:00');
    }

    public function down(): void
    {
        $this->carry(from: '+00:00', to: 'SYSTEM');
    }

    private function carry(string $from, string $to): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) || $this->serverClockIsUtc()) {
            return;
        }

        $tables = collect(DB::select(
            "select table_name as t, column_name as c, is_nullable as n, column_default as d, extra as e
             from information_schema.columns
             where table_schema = database() and data_type = 'timestamp'
             order by table_name, ordinal_position",
        ))->groupBy('t');

        $restate = function (string $type) use ($tables): void {
            foreach ($tables as $table => $columns) {
                $changes = $columns->map(fn (object $column): string => sprintf(
                    'modify `%s` %s %s%s%s',
                    $column->c,
                    $type,
                    $column->n === 'YES' ? 'null' : 'not null',
                    // A column's own default and on-update rule are kept exactly as they were.
                    $column->d !== null && strtoupper((string) $column->d) !== 'NULL' ? ' default '.$column->d : '',
                    stripos((string) $column->e, 'on update') !== false ? ' '.$column->e : '',
                ))->implode(', ');

                DB::statement("alter table `{$table}` {$changes}");
            }
        };

        try {
            DB::statement('set time_zone = ?', [$from]);
            $restate('datetime');

            DB::statement('set time_zone = ?', [$to]);
            $restate('timestamp');
        } finally {
            // Leave the session as the connection is configured to have it.
            DB::statement('set time_zone = ?', [config('database.connections.'.DB::getDefaultConnection().'.timezone') ?: 'SYSTEM']);
        }
    }

    /**
     * Does the database server itself already keep UTC?
     *
     * Asked of a winter date and a summer one, so a zone that merely sits at UTC for half
     * the year is not mistaken for it. If both noons are read as the UTC instants they
     * name, there is no skipped hour and nothing stored needs carrying.
     */
    private function serverClockIsUtc(): bool
    {
        DB::statement("set time_zone = 'SYSTEM'");

        try {
            $read = DB::selectOne("select unix_timestamp('2027-01-15 12:00:00') as winter, unix_timestamp('2027-07-15 12:00:00') as summer");

            return (int) $read->winter === gmmktime(12, 0, 0, 1, 15, 2027)
                && (int) $read->summer === gmmktime(12, 0, 0, 7, 15, 2027);
        } finally {
            DB::statement('set time_zone = ?', [config('database.connections.'.DB::getDefaultConnection().'.timezone') ?: 'SYSTEM']);
        }
    }
};
