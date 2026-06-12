<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Pool;
use App\Services\GeocodingService;
use Illuminate\Support\Facades\DB;

/**
 * Create a pool for a customer plus its service location, in one transaction.
 */
class CreatePool
{
    public function __construct(private readonly GeocodingService $geocoder) {}

    /**
     * @param  array<string, mixed>  $data  validated pool + location fields
     */
    public function handle(array $data): Pool
    {
        $pool = DB::transaction(function () use ($data): Pool {
            $pool = Pool::create([
                'customer_id' => $data['customer_id'],
                'name' => $data['name'],
                'type' => $data['type'] ?? 'inground',
                'volume_gallons' => $data['volume_gallons'] ?? null,
                'surface_type' => $data['surface_type'] ?? null,
                'sanitizer_type' => $data['sanitizer_type'] ?? 'chlorine',
                'filter_type' => $data['filter_type'] ?? null,
                'pump_type' => $data['pump_type'] ?? null,
                'has_heater' => $data['has_heater'] ?? false,
                'has_automation' => $data['has_automation'] ?? false,
                'has_pool_cleaner' => $data['has_pool_cleaner'] ?? false,
                'has_cover' => $data['has_cover'] ?? false,
                'has_water_feature' => $data['has_water_feature'] ?? false,
                'has_auto_fill' => $data['has_auto_fill'] ?? false,
                'notes' => $data['notes'] ?? null,
            ]);

            $pool->serviceLocation()->create([
                'address_line1' => $data['address_line1'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'zip' => $data['zip'] ?? null,
                'gate_code' => $data['gate_code'] ?? null,
                'access_notes' => $data['access_notes'] ?? null,
            ]);

            return $pool;
        });

        // Geocode outside the transaction so a slow API call never holds a lock.
        $location = $pool->serviceLocation;
        if ($location !== null) {
            $this->geocoder->locate($location);
        }

        return $pool;
    }
}
