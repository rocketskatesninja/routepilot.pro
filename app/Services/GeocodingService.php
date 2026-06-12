<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ServiceLocation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Address → lat/lng geocoding via the Google Maps Geocoding API.
 *
 * The server-side key lives in config('services.google.server_maps_key')
 * (GOOGLE_MAPS_API_KEY). All tenants share one key — simpler than per-tenant
 * management and bills back to a single RoutePilot Google Cloud project.
 *
 * Every method fails soft: a missing key, unresolvable address, quota, or
 * network error returns null / false and leaves coordinates untouched, so a
 * geocode failure never blocks saving a pool. Coordinates can be backfilled
 * later with `app:geocode-locations`.
 */
class GeocodingService
{
    private const GOOGLE_URL = 'https://maps.googleapis.com/maps/api/geocode/json';

    /**
     * Geocode a freeform address to ['lat' => float, 'lng' => float], or null
     * on any failure (missing key, no result, quota, network).
     *
     * @return array{lat: float, lng: float}|null
     */
    public function geocode(string $address): ?array
    {
        $address = trim($address);
        if ($address === '') {
            return null;
        }

        $apiKey = config('services.google.server_maps_key');
        if (! is_string($apiKey) || $apiKey === '') {
            Log::warning('Geocoding skipped — GOOGLE_MAPS_API_KEY is not configured.');

            return null;
        }

        try {
            $response = Http::timeout(8)->get(self::GOOGLE_URL, ['address' => $address, 'key' => $apiKey]);
        } catch (\Throwable $e) {
            Log::warning('Geocoding request failed', ['address' => $address, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Geocoding HTTP error', ['status' => $response->status(), 'address' => $address]);

            return null;
        }

        $data = $response->json();
        $status = data_get($data, 'status');
        if ($status !== 'OK') {
            Log::warning('Geocoding non-OK status', ['status' => $status, 'address' => $address]);

            return null;
        }

        $loc = data_get($data, 'results.0.geometry.location');
        if (! is_array($loc) || ! isset($loc['lat'], $loc['lng'])) {
            return null;
        }

        return ['lat' => (float) $loc['lat'], 'lng' => (float) $loc['lng']];
    }

    /**
     * Geocode a service location from its address parts and persist lat/lng.
     * Returns true only when coordinates were resolved + saved; no-ops (false)
     * on an empty address or a geocode failure, leaving existing coords intact.
     */
    public function locate(ServiceLocation $location): bool
    {
        $address = $this->assemble($location);
        if ($address === '') {
            return false;
        }

        $coords = $this->geocode($address);
        if ($coords === null) {
            return false;
        }

        $location->update(['lat' => $coords['lat'], 'lng' => $coords['lng']]);

        return true;
    }

    /** Build a single-line address string from a location's parts. */
    private function assemble(ServiceLocation $location): string
    {
        $parts = array_filter([
            (string) $location->getAttribute('address_line1'),
            (string) $location->getAttribute('city'),
            trim((string) $location->getAttribute('state').' '.(string) $location->getAttribute('zip')),
        ], fn (string $part): bool => trim($part) !== '');

        return implode(', ', $parts);
    }
}
