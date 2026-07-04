<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceVisit;
use App\Models\VisitPhoto;
use Illuminate\Support\Facades\Storage;

/**
 * Permanently delete an archived customer: remove their visit-photo FILES from
 * disk (DB rows cascade), then hard-delete the customer. The database's
 * ON DELETE CASCADE foreign keys clear the whole tree — pools, locations,
 * subscriptions, visits, readings, treatments, photos, invoices, payments,
 * charges — trashed or not. Irreversible (the retention purge does the same
 * after 365 days; this is the on-demand version).
 */
class PurgeCustomer
{
    public function handle(Customer $customer): void
    {
        $this->deletePhotoFiles((int) $customer->id);

        $customer->forceDelete(); // DB cascade clears every dependent row
    }

    /** Delete the on-disk photo files for a customer's pools (incl. trashed pools). */
    private function deletePhotoFiles(int $customerId): void
    {
        $poolIds = Pool::withTrashed()->where('customer_id', $customerId)->pluck('id');
        if ($poolIds->isEmpty()) {
            return;
        }
        $visitIds = ServiceVisit::query()->whereIn('pool_id', $poolIds)->pluck('id');
        if ($visitIds->isEmpty()) {
            return;
        }

        $disk = Storage::disk('public');
        foreach (VisitPhoto::query()->whereIn('service_visit_id', $visitIds)->get() as $photo) {
            $path = (string) $photo->getAttribute('photo_path');
            if ($path !== '' && $disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }
}
