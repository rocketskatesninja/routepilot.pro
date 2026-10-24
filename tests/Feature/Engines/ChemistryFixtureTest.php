<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\Tenant;
use App\Services\ChemistryService;

/**
 * The chemistry engine's shared fixture. The field app's TS port must reproduce it
 * (resources/js/lib/chemistry.test.ts), and so must this PHP engine, the
 * authoritative one, so the two can't drift apart. After an intentional engine
 * change, rewrite it from the PHP engine and check the TS port follows:
 *
 *   UPDATE_CHEMISTRY_FIXTURE=1 ./vendor/bin/pest --filter=ChemistryFixture && npx prettier --write resources/js/lib/__fixtures__
 */
test('the PHP engine reproduces the shared chemistry fixture', function () {
    $path = resource_path('js/lib/__fixtures__/chemistry-vectors.json');
    /** @var list<array{name: string, reading: array<string, float|int|null>, pool: array<string, mixed>, weather: array<string, mixed>|null, expected: array<string, mixed>}> $vectors */
    $vectors = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $update = (bool) getenv('UPDATE_CHEMISTRY_FIXTURE');

    $tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $tenant->id);
    $customer = Customer::factory()->for($tenant)->create();

    foreach ($vectors as $i => $vector) {
        $pool = Pool::factory()->for($tenant)->for($customer)->create([
            'volume_gallons' => $vector['pool']['volume_gallons'],
            'sanitizer_type' => $vector['pool']['sanitizer'],
            'custom_target_ranges' => $vector['pool']['custom_target_ranges'],
        ]);
        $actual = app(ChemistryService::class)->fullAnalysis($vector['reading'], $pool, $vector['weather']);

        if ($update) {
            $vectors[$i]['expected'] = $actual;
        } else {
            expect($actual)->toEqual($vector['expected'], $vector['name']);
        }
    }

    if ($update) {
        file_put_contents($path, json_encode($vectors, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        expect(json_decode((string) file_get_contents($path), true))->toHaveCount(count($vectors));
    }
});
