<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Services\WeatherService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function fakeForecast(): array
{
    return [
        'current' => ['time' => '2026-07-03T10:00', 'temperature_2m' => 80, 'apparent_temperature' => 82, 'relative_humidity_2m' => 50, 'wind_speed_10m' => 5, 'weather_code' => 1],
        'hourly' => ['time' => ['2026-07-03T10:00'], 'temperature_2m' => [80], 'weather_code' => [1], 'precipitation_probability' => [0]],
        'daily' => ['time' => ['2026-07-03'], 'temperature_2m_max' => [90], 'temperature_2m_min' => [70], 'weather_code' => [1], 'precipitation_probability_max' => [10]],
    ];
}

beforeEach(fn () => Cache::flush());

test('warming an active tenant location means forecast() serves it without a fresh API call', function () {
    Http::fake(['api.open-meteo.com/*' => Http::response(fakeForecast())]);
    $tenant = Tenant::factory()->create(['status' => 'active']);
    $tenant->forceFill(['lat' => 31.18, 'lng' => -81.49])->save();

    $this->artisan('app:warm-weather')->assertSuccessful();

    $data = app(WeatherService::class)->forecast(31.18, -81.49);
    expect($data)->not->toBeNull()
        ->and($data['current']['temp'])->toBe(80);
    Http::assertSentCount(1); // warmed once; the visitor read hit the cache
});

test('the warmer skips tenants without coordinates and dedupes a shared location', function () {
    Http::fake(['api.open-meteo.com/*' => Http::response(fakeForecast())]);
    Tenant::factory()->create(['status' => 'active'])->forceFill(['lat' => 10.0, 'lng' => 20.0])->save();
    Tenant::factory()->create(['status' => 'active'])->forceFill(['lat' => 10.0, 'lng' => 20.0])->save(); // same rounded coords
    Tenant::factory()->create(['status' => 'active']); // no coordinates → skipped

    $this->artisan('app:warm-weather')->assertSuccessful();

    Http::assertSentCount(1); // shared location fetched once; the address-less tenant ignored
});

test('a failed fetch leaves any last-good forecast in place', function () {
    // Seed a good forecast, then a warm run that fails must not clobber it.
    Cache::put('weather:5,6', ['current' => ['temp' => 72]], now()->addMinutes(45));
    Http::fake(['api.open-meteo.com/*' => Http::response([], 503)]);
    Tenant::factory()->create(['status' => 'active'])->forceFill(['lat' => 5.0, 'lng' => 6.0])->save();

    $this->artisan('app:warm-weather')->assertSuccessful();

    expect(Cache::get('weather:5,6'))->toBe(['current' => ['temp' => 72]]);
});
