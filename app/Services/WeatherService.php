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

        // Cache the OUTCOME — success or failure — so an unreachable API doesn't
        // stall every dashboard load with a fresh timeout. `false` marks a
        // recently-failed lookup; Laravel's Cache::remember never caches null,
        // which is why a down API otherwise re-hit the timeout on every request.
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached === false ? null : $cached;
        }

        $forecast = $this->fetch($lat, $lng);

        // Success is good for 30 min; a failure is cached 10 min so we back off
        // the down API instead of re-timing-out on the next visitor.
        Cache::put($key, $forecast ?? false, now()->addMinutes($forecast !== null ? 30 : 10));

        return $forecast;
    }

    /**
     * Proactively refresh a location's cached forecast — called by the scheduled
     * warmer (app:warm-weather) so visitor dashboard loads always hit a warm
     * cache and never make the API call themselves. Only overwrites on success,
     * so a transient failure keeps the last-good forecast on screen. The 45-min
     * TTL comfortably outlives the 30-min warm interval, so the cache stays fresh
     * between runs. Returns the forecast, or null if the fetch failed.
     *
     * @return array<string, mixed>|null
     */
    public function warm(float $lat, float $lng): ?array
    {
        $forecast = $this->fetch($lat, $lng);
        if ($forecast !== null) {
            Cache::put('weather:'.round($lat, 2).','.round($lng, 2), $forecast, now()->addMinutes(45));
        }

        return $forecast;
    }

    /** @return array<string, mixed>|null */
    private function fetch(float $lat, float $lng): ?array
    {
        try {
            $res = Http::timeout(3)->get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => $lat,
                'longitude' => $lng,
                'current' => 'temperature_2m,apparent_temperature,relative_humidity_2m,weather_code,wind_speed_10m',
                'hourly' => 'temperature_2m,weather_code,precipitation_probability',
                'daily' => 'temperature_2m_max,temperature_2m_min,weather_code,precipitation_probability_max',
                'temperature_unit' => 'fahrenheit',
                'wind_speed_unit' => 'mph',
                'timezone' => 'auto',
                'forecast_days' => 7,
            ]);

            return $res->ok() ? $this->shape($res->json()) : null;
        } catch (\Throwable $e) {
            Log::warning('WeatherService: '.$e->getMessage());

            return null;
        }
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
            'hours' => $this->hours($raw['hourly'] ?? null, is_string($current['time'] ?? null) ? $current['time'] : null),
            'days' => $days,
        ];
    }

    /**
     * The next ~8 hourly entries from the current hour onward.
     *
     * @return list<array<string, int|string>>
     */
    private function hours(mixed $hourly, ?string $now): array
    {
        if (! is_array($hourly)) {
            return [];
        }
        $times = is_array($hourly['time'] ?? null) ? $hourly['time'] : [];
        $temps = is_array($hourly['temperature_2m'] ?? null) ? $hourly['temperature_2m'] : [];
        $codes = is_array($hourly['weather_code'] ?? null) ? $hourly['weather_code'] : [];
        $precip = is_array($hourly['precipitation_probability'] ?? null) ? $hourly['precipitation_probability'] : [];

        // Skip past hours: keep entries at or after the current hour.
        $nowHour = $now !== null ? substr($now, 0, 13) : null;
        $start = 0;
        if ($nowHour !== null) {
            foreach ($times as $i => $time) {
                if (is_string($time) && substr($time, 0, 13) >= $nowHour) {
                    $start = (int) $i;
                    break;
                }
            }
        }

        $out = [];
        foreach (array_slice(array_keys($times), $start, 8) as $i) {
            $time = $times[$i] ?? null;
            if (! is_string($time)) {
                continue;
            }
            $out[] = [
                'hour' => Carbon::parse($time)->format('ga'),
                'temp' => (int) round($this->num($temps[$i] ?? null)),
                'code' => (int) $this->num($codes[$i] ?? null),
                'precip' => (int) $this->num($precip[$i] ?? null),
            ];
        }

        return $out;
    }

    private function num(mixed $v): float
    {
        return is_numeric($v) ? (float) $v : 0.0;
    }
}
