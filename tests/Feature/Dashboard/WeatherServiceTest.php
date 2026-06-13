<?php

declare(strict_types=1);

use App\Services\WeatherService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

test('the forecast is shaped into current, hourly, and daily', function () {
    Carbon::setTestNow('2026-06-13 14:30:00');

    Http::fake(['api.open-meteo.com/*' => Http::response([
        'current' => [
            'time' => '2026-06-13T14:30',
            'temperature_2m' => 88.2, 'apparent_temperature' => 91.0,
            'relative_humidity_2m' => 40, 'wind_speed_10m' => 6.0, 'weather_code' => 1,
        ],
        'hourly' => [
            'time' => ['2026-06-13T13:00', '2026-06-13T14:00', '2026-06-13T15:00', '2026-06-13T16:00'],
            'temperature_2m' => [85, 88, 90, 89],
            'weather_code' => [1, 2, 3, 61],
            'precipitation_probability' => [0, 10, 30, 60],
        ],
        'daily' => [
            'time' => ['2026-06-13', '2026-06-14'],
            'temperature_2m_max' => [92, 90], 'temperature_2m_min' => [70, 68],
            'weather_code' => [1, 3], 'precipitation_probability_max' => [10, 40],
        ],
    ])]);

    $data = (new WeatherService)->forecast(30.27, -97.74);

    expect($data['current']['temp'])->toBe(88)
        ->and($data['days'])->toHaveCount(2)
        // The hourly strip starts at the current hour (14:00), skipping the past 13:00.
        ->and($data['hours'])->toHaveCount(3)
        ->and($data['hours'][0])->toMatchArray(['hour' => '2pm', 'temp' => 88, 'code' => 2, 'precip' => 10]);

    Carbon::setTestNow();
});
