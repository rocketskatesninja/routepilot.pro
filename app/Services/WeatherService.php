<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Current conditions + a short daily forecast from Open-Meteo (free, no API
 * key). Cached 30 minutes per coordinate (rounded to 2 decimals) so dashboard
 * loads don't hammer the API. Returns null on any failure (bad coords, network,
 * API down) so the weather widget can hide gracefully instead of erroring.
 */
class WeatherService
{
    /** @return array<string, mixed>|null */
    public function forecast(float $lat, float $lng): ?array
    {
        $key = 'weather:'.round($lat, 2).','.round($lng, 2);

        return Cache::remember($key, now()->addMinutes(30), function () use ($lat, $lng): ?array {
            try {
                $res = Http::timeout(5)->get('https://api.open-meteo.com/v1/forecast', [
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'current' => 'temperature_2m,apparent_temperature,relative_humidity_2m,weather_code,wind_speed_10m',
                    'daily' => 'temperature_2m_max,temperature_2m_min,weather_code,precipitation_probability_max',
                    'temperature_unit' => 'fahrenheit',
                    'wind_speed_unit' => 'mph',
                    'timezone' => 'auto',
                    'forecast_days' => 5,
                ]);

                return $res->ok() ? $this->shape($res->json()) : null;
            } catch (\Throwable $e) {
                Log::warning('WeatherService: '.$e->getMessage());

                return null;
            }
        });
    }

    /**
     * Reduce the raw Open-Meteo payload to the flat, display-ready shape the
     * widget renders (WMO codes are mapped to labels/icons on the front-end).
     *
     * @return array<string, mixed>|null
     */
    private function shape(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }
        $current = $raw['current'] ?? null;
        $daily = $raw['daily'] ?? null;
        if (! is_array($current) || ! is_array($daily)) {
            return null;
        }

        $times = is_array($daily['time'] ?? null) ? $daily['time'] : [];
        $max = is_array($daily['temperature_2m_max'] ?? null) ? $daily['temperature_2m_max'] : [];
        $min = is_array($daily['temperature_2m_min'] ?? null) ? $daily['temperature_2m_min'] : [];
        $codes = is_array($daily['weather_code'] ?? null) ? $daily['weather_code'] : [];
        $precip = is_array($daily['precipitation_probability_max'] ?? null) ? $daily['precipitation_probability_max'] : [];

        $days = [];
        foreach ($times as $i => $time) {
            if (! is_string($time)) {
                continue;
            }
            $date = Carbon::parse($time);
            $days[] = [
                'date' => $date->toDateString(),
                'dow' => $date->isToday() ? 'Today' : $date->isoFormat('ddd'),
                'high' => (int) round($this->num($max[$i] ?? null)),
                'low' => (int) round($this->num($min[$i] ?? null)),
                'code' => (int) $this->num($codes[$i] ?? null),
                'precip' => (int) $this->num($precip[$i] ?? null),
            ];
        }

        return [
            'current' => [
                'temp' => (int) round($this->num($current['temperature_2m'] ?? null)),
                'feels' => (int) round($this->num($current['apparent_temperature'] ?? null)),
                'humidity' => (int) round($this->num($current['relative_humidity_2m'] ?? null)),
                'wind' => (int) round($this->num($current['wind_speed_10m'] ?? null)),
                'code' => (int) $this->num($current['weather_code'] ?? null),
            ],
            'days' => $days,
        ];
    }

    private function num(mixed $v): float
    {
        return is_numeric($v) ? (float) $v : 0.0;
    }
}
