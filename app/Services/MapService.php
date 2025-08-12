<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MapService
{
    /**
     * Geocode an address using OpenStreetMap Nominatim API
     *
     * @param string $address
     * @param int $cacheMinutes
     * @return array|null [lat, lng]
     */
    public static function geocodeAddress(string $address, int $cacheMinutes = 1440): ?array
    {
        // Create a cache key based on the address
        $cacheKey = 'geocode_' . md5(strtolower(trim($address)));
        
        // Try to get from cache first
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        try {
            // Use OpenStreetMap Nominatim API (free, no API key required)
            $response = Http::timeout(10)->get('https://nominatim.openstreetmap.org/search', [
                'format' => 'json',
                'q' => $address,
                'limit' => 1,
                'countrycodes' => 'us',
                'addressdetails' => 1
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (!empty($data) && isset($data[0]['lat'], $data[0]['lon'])) {
                    $coordinates = [
                        'lat' => (float) $data[0]['lat'],
                        'lng' => (float) $data[0]['lon'],
                        'display_name' => $data[0]['display_name'] ?? $address
                    ];
                    
                    // Cache the result for 24 hours (1440 minutes)
                    Cache::put($cacheKey, $coordinates, $cacheMinutes);
                    
                    Log::info("Geocoded address successfully", [
                        'address' => $address,
                        'coordinates' => $coordinates
                    ]);
                    
                    return $coordinates;
                }
            }
            
            Log::warning("Geocoding failed - no results found", ['address' => $address]);
            return null;
            
        } catch (\Exception $e) {
            Log::error("Geocoding error", [
                'address' => $address,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Get coordinates for multiple addresses efficiently
     *
     * @param array $addresses
     * @return array
     */
    public static function geocodeMultipleAddresses(array $addresses): array
    {
        $results = [];
        
        foreach ($addresses as $address) {
            $coordinates = self::geocodeAddress($address);
            if ($coordinates) {
                $results[$address] = $coordinates;
            }
        }
        
        return $results;
    }
    
    /**
     * Calculate distance between two coordinates in miles
     *
     * @param float $lat1
     * @param float $lng1
     * @param float $lat2
     * @param float $lng2
     * @return float
     */
    public static function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 3959; // Earth's radius in miles
        
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);
        
        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lngDelta / 2) * sin($lngDelta / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }
    
    /**
     * Find locations within a certain radius of a point
     *
     * @param float $centerLat
     * @param float $centerLng
     * @param float $radiusMiles
     * @param array $locations
     * @return array
     */
    public static function findLocationsInRadius(float $centerLat, float $centerLng, float $radiusMiles, array $locations): array
    {
        $nearbyLocations = [];
        
        foreach ($locations as $location) {
            if (isset($location['coordinates']['lat'], $location['coordinates']['lng'])) {
                $distance = self::calculateDistance(
                    $centerLat,
                    $centerLng,
                    $location['coordinates']['lat'],
                    $location['coordinates']['lng']
                );
                
                if ($distance <= $radiusMiles) {
                    $location['distance'] = round($distance, 1);
                    $nearbyLocations[] = $location;
                }
            }
        }
        
        // Sort by distance
        usort($nearbyLocations, function($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });
        
        return $nearbyLocations;
    }
    
    /**
     * Get map center coordinates based on locations
     *
     * @param array $locations
     * @return array [lat, lng]
     */
    public static function getMapCenter(array $locations): array
    {
        if (empty($locations)) {
            // Default to center of USA
            return [39.8283, -98.5795];
        }
        
        $validCoordinates = array_filter($locations, function($location) {
            return isset($location['coordinates']['lat'], $location['coordinates']['lng']);
        });
        
        if (empty($validCoordinates)) {
            return [39.8283, -98.5795];
        }
        
        $totalLat = 0;
        $totalLng = 0;
        $count = 0;
        
        foreach ($validCoordinates as $location) {
            $totalLat += $location['coordinates']['lat'];
            $totalLng += $location['coordinates']['lng'];
            $count++;
        }
        
        return [
            'lat' => $totalLat / $count,
            'lng' => $totalLng / $count
        ];
    }
    
    /**
     * Clear geocoding cache
     *
     * @return bool
     */
    public static function clearCache(): bool
    {
        try {
            Cache::flush();
            Log::info("Map geocoding cache cleared");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to clear map cache", ['error' => $e->getMessage()]);
            return false;
        }
    }
}
