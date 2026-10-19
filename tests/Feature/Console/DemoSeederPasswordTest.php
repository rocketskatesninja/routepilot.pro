<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

/**
 * The demo logins' password: easy on a dev box, never the well-known one on the
 * live site. (The live site's demo accounts once still opened with "password",
 * and the public README listed them.)
 */
const DEMO_LOGINS = ['admin@routepilot.pro', 'tenant@routepilot.pro', 'agent@routepilot.pro', 'ashley@sunshine.test', 'customer@routepilot.pro'];

/** Seed the demo company as `php artisan db:seed` would, returning what it printed. */
function seedDemo(): string
{
    Artisan::call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

    return Artisan::output();
}

function demoUser(string $email): User
{
    return User::withoutGlobalScopes()->where('email', $email)->sole();
}

test('on a dev box the demo logins use "password"', function () {
    seedDemo();

    foreach (DEMO_LOGINS as $email) {
        expect(Hash::check('password', demoUser($email)->password))->toBeTrue();
    }
});

test('on a production box each demo login gets its own random password, shown once', function () {
    app()['env'] = 'production';
    config(['app.demo_password' => null]);

    $out = seedDemo();

    $seen = [];
    foreach (DEMO_LOGINS as $email) {
        expect(preg_match('/'.preg_quote($email, '/').'\s*\|\s*\S+\s*\|\s*([A-Za-z0-9]{24})\s/', $out, $m))->toBe(1);
        $user = demoUser($email);
        expect(Hash::check('password', $user->password))->toBeFalse()
            ->and(Hash::check($m[1], $user->password))->toBeTrue();
        $seen[] = $m[1];
    }
    expect(array_unique($seen))->toHaveCount(count(DEMO_LOGINS))
        ->and($out)->toContain('shown once');
});

test('a box that sets DEMO_PASSWORD (staging) keeps that one, even in production', function () {
    app()['env'] = 'production';
    config(['app.demo_password' => 'staging-demo']);

    $out = seedDemo();

    expect(Hash::check('staging-demo', demoUser('tenant@routepilot.pro')->password))->toBeTrue()
        ->and($out)->toContain('password "staging-demo"');
});

test('plain db:seed runs the demo seeder, so the same rules apply', function () {
    app()['env'] = 'production';
    config(['app.demo_password' => null]);

    Artisan::call('db:seed', ['--force' => true]);

    expect(Hash::check('password', demoUser('admin@routepilot.pro')->password))->toBeFalse()
        ->and(User::withoutGlobalScopes()->where('email', 'test@example.com')->exists())->toBeFalse();
});
