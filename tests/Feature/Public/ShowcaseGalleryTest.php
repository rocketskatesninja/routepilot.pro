<?php

declare(strict_types=1);

use App\Models\Pool;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitPhoto;
use Inertia\Testing\AssertableInertia as Assert;

/** Create a completed visit + one photo for a tenant (binds tenant_id so scoped models fill it). */
function makeVisitPhoto(int $tenantId, bool $showcase, string $path): VisitPhoto
{
    app()->instance('tenant_id', $tenantId);
    $pool = Pool::factory()->create();
    $agent = User::factory()->create(['tenant_id' => $tenantId]);
    $visit = ServiceVisit::factory()->create(['pool_id' => $pool->id, 'agent_id' => $agent->id, 'status' => 'completed']);
    $photo = VisitPhoto::create(['service_visit_id' => $visit->id, 'photo_path' => $path, 'is_showcase' => $showcase]);
    app()->forgetInstance('tenant_id');

    return $photo;
}

test('the gallery shows only this tenant\'s showcase photos', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    makeVisitPhoto($tenant->id, true, 'visit-photos/featured.jpg');
    makeVisitPhoto($tenant->id, false, 'visit-photos/private.jpg');

    $other = Tenant::factory()->create(['slug' => 'rival']);
    makeVisitPhoto($other->id, true, 'visit-photos/rival.jpg');

    $this->get('/t/acme')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/Landing')
            ->where('live.gallery', function ($gallery) {
                $urls = collect($gallery)->pluck('url')->implode(' ');

                return str_contains($urls, 'featured.jpg')   // own showcase shown
                    && ! str_contains($urls, 'private.jpg')   // own non-showcase hidden
                    && ! str_contains($urls, 'rival.jpg');    // other tenant's showcase hidden
            })
        );
});

test('only an owning tenant_admin can toggle a photo showcase flag', function () {
    $tenant = Tenant::factory()->create();
    $photo = makeVisitPhoto($tenant->id, false, 'visit-photos/x.jpg');

    // The owning admin can feature it.
    $admin = User::factory()->for($tenant)->create();
    $this->actingAs($admin)->post("/photos/{$photo->id}/showcase", ['is_showcase' => true])->assertRedirect();
    expect($photo->fresh()?->is_showcase)->toBeTrue();

    // An agent cannot.
    $agent = User::factory()->agent()->for($tenant)->create();
    $this->actingAs($agent)->post("/photos/{$photo->id}/showcase", ['is_showcase' => false])->assertForbidden();

    // A foreign tenant_admin gets a 404 (the photo's visit is scoped away).
    $foreignAdmin = User::factory()->for(Tenant::factory()->create())->create();
    $this->actingAs($foreignAdmin)->post("/photos/{$photo->id}/showcase", ['is_showcase' => false])->assertNotFound();
});
