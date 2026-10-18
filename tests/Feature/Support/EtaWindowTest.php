<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Support\EtaWindow;
use Illuminate\Support\Carbon;

test('it buckets an eta into a one-hour window', function () {
    expect(EtaWindow::for(Carbon::parse('2026-06-17 14:25')))->toBe('2:00 – 3:00 PM');
});

test('it spells both meridiems across the noon boundary', function () {
    expect(EtaWindow::for(Carbon::parse('2026-06-17 11:40')))->toBe('11:00 AM – 12:00 PM');
});

test('it is null for a null eta', function () {
    expect(EtaWindow::for(null))->toBeNull();
});

test('it reads the window on the company\'s clock', function () {
    app()->instance('tenant', Tenant::factory()->create(['timezone' => 'America/New_York']));

    // 13:25 UTC in June is 9:25 in New York.
    expect(EtaWindow::for(Carbon::parse('2026-06-17 13:25', 'UTC')))->toBe('9:00 – 10:00 AM');
});
