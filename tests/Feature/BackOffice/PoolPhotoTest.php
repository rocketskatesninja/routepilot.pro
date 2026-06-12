<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PhotoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
});

test('creating a pool with a photo stores it', function () {
    $customer = Customer::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)->post('/pools', [
        'customer_id' => $customer->id,
        'name' => 'Backyard',
        'photo' => UploadedFile::fake()->image('pool.jpg', 1600, 1200),
    ])->assertRedirect();

    $pool = Pool::query()->where('name', 'Backyard')->firstOrFail();
    expect($pool->getAttribute('photo_path'))->not->toBeNull();
    Storage::disk('public')->assertExists($pool->getAttribute('photo_path'));
});

test('a pool can be created without a photo', function () {
    $customer = Customer::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)->post('/pools', [
        'customer_id' => $customer->id,
        'name' => 'No photo',
    ])->assertRedirect();

    expect(Pool::query()->where('name', 'No photo')->firstOrFail()->getAttribute('photo_path'))->toBeNull();
});

test('updating a pool with a new photo replaces the old one', function () {
    $customer = Customer::factory()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create();
    $old = app(PhotoService::class)->store(UploadedFile::fake()->image('old.jpg'), 'pools');
    $pool->forceFill(['photo_path' => $old])->save();

    $this->actingAs($this->admin)->patch("/pools/{$pool->id}", [
        'name' => $pool->name,
        'photo' => UploadedFile::fake()->image('new.jpg', 800, 600),
    ])->assertRedirect();

    $pool->refresh();
    expect($pool->getAttribute('photo_path'))->not->toBe($old);
    Storage::disk('public')->assertMissing($old);
    Storage::disk('public')->assertExists($pool->getAttribute('photo_path'));
});
