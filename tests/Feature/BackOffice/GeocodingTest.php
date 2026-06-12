<?php

declare(strict_types=1);

use App\Actions\CreatePool;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Tenant;
use App\Models\User;
use App\Services\GeocodingService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.google.server_maps_key' => 'test-key']);
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
});

function fakeGoogleGeocode(float $lat, float $lng): void
{
    Http::fake([
        'maps.googleapis.com/*' => Http::response([
            'status' => 'OK',
            'results' => [['geometry' => ['location' => ['lat' => $lat, 'lng' => $lng]]]],
        ], 200),
    ]);
}

test('geocode returns coordinates on an OK response', function () {
    fakeGoogleGeocode(40.5, -75.25);

    expect(app(GeocodingService::class)->geocode('1600 Pennsylvania Ave, Washington, DC'))
        ->toBe(['lat' => 40.5, 'lng' => -75.25]);
});

test('geocode makes no request and returns null when the key is missing', function () {
    config(['services.google.server_maps_key' => null]);
    Http::fake();

    expect(app(GeocodingService::class)->geocode('anywhere'))->toBeNull();
    Http::assertNothingSent();
});

test('geocode returns null on a non-OK status', function () {
    Http::fake(['maps.googleapis.com/*' => Http::response(['status' => 'ZERO_RESULTS', 'results' => []], 200)]);

    expect(app(GeocodingService::class)->geocode('asdfqwer'))->toBeNull();
});

test('creating a pool with an address geocodes its service location', function () {
    fakeGoogleGeocode(33.5, -84.3);
    $customer = Customer::factory()->for($this->tenant)->create();

    $pool = app(CreatePool::class)->handle([
        'customer_id' => $customer->id,
        'name' => 'Backyard',
        'address_line1' => '123 Main St',
        'city' => 'Atlanta',
        'state' => 'GA',
        'zip' => '30301',
    ]);

    expect($pool->serviceLocation?->lat)->toBe(33.5)
        ->and($pool->serviceLocation?->lng)->toBe(-84.3);
});

test('an empty address is never geocoded', function () {
    Http::fake();
    $customer = Customer::factory()->for($this->tenant)->create();

    app(CreatePool::class)->handle(['customer_id' => $customer->id, 'name' => 'No address']);

    Http::assertNothingSent();
});

test('the backfill command geocodes locations missing coordinates', function () {
    fakeGoogleGeocode(29.7, -95.3);
    $customer = Customer::factory()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create();
    $location = $pool->serviceLocation()->create([
        'address_line1' => '500 Elm St', 'city' => 'Houston', 'lat' => null, 'lng' => null,
    ]);

    $this->artisan('app:geocode-locations')->assertSuccessful();

    expect($location->fresh()?->lat)->toBe(29.7);
});
