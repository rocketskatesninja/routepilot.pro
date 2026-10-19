<?php

declare(strict_types=1);

/**
 * Rule: scheduling decides calendar days by the company's local date
 * (Tenant::today() / Tenant::localToday()), never the server clock — the server
 * runs on UTC, so now()/today() is already "tomorrow" after 8 pm Eastern.
 */
test('scheduling code never reads the server clock to decide a day', function () {
    $root = dirname(__DIR__, 2);
    $files = [
        'app/Services/SubscriptionMaterializer.php',
        'app/Actions/CreateSubscription.php',
        'app/Actions/UpdateSubscription.php',
        'app/Actions/DeleteSubscription.php',
        'app/Actions/MarkMissedStops.php',
        'app/Actions/SkipStop.php',
        'app/Actions/UnskipStop.php',
        'app/Actions/UnassignAgentPlans.php',
        'app/Console/Commands/MaterializeSchedules.php',
        ...array_map(fn (string $f): string => 'app/Services/Chat/Tools/'.basename($f), glob($root.'/app/Services/Chat/Tools/*.php') ?: []),
    ];

    $offenders = [];
    foreach ($files as $file) {
        $code = (string) file_get_contents($root.'/'.$file);
        if (preg_match('/(?<![\w>:$])(now|today)\(\)|Carbon::(now|today)\(/', $code)) {
            $offenders[] = $file;
        }
    }

    expect($offenders)->toBe([]);
});
