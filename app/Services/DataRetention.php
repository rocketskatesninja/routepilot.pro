<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceVisit;
use App\Models\VisitPhoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Data-retention sweep: finalize customer erasures past their retention window
 * and prune stale read notifications. Runs platform-wide from the console with
 * no bound tenant, so the tenant scope is a no-op and every tenant is swept;
 * correctness comes from filtering on explicit ids, not the scope.
 */
class DataRetention
{
    /**
     * @return array{customers_purged: int, photo_files_deleted: int, notifications_pruned: int}
     */
    public function purge(bool $apply = true, ?int $customerDays = null, ?int $notificationDays = null): array
    {
        $customerDays ??= (int) config('retention.customer_purge_days');
        $notificationDays ??= (int) config('retention.read_notification_days');

        $erasure = $this->finalizeCustomerErasures($customerDays, $apply);

        return [
            'customers_purged' => $erasure['customers'],
            'photo_files_deleted' => $erasure['files'],
            'notifications_pruned' => $this->pruneReadNotifications($notificationDays, $apply),
        ];
    }

    /**
     * Hard-delete customers soft-deleted longer than $days ago. The DB FK
     * cascade clears their dependent PII rows; photo FILES are removed first
     * (the cascade can't reach storage).
     *
     * @return array{customers: int, files: int}
     */
    private function finalizeCustomerErasures(int $days, bool $apply): array
    {
        if ($days <= 0) {
            return ['customers' => 0, 'files' => 0];
        }

        $cutoff = now()->subDays($days);
        $customers = Customer::onlyTrashed()->where('deleted_at', '<', $cutoff)->get();

        $files = 0;
        foreach ($customers as $customer) {
            $files += $this->purgePhotoFiles((int) $customer->id, $apply);
            if ($apply) {
                $customer->forceDelete(); // DB cascade clears all dependent PII rows
            }
        }

        return ['customers' => $customers->count(), 'files' => $files];
    }

    /**
     * Delete (or, in preview, count) the storage files for every visit photo
     * belonging to a customer's pools.
     */
    private function purgePhotoFiles(int $customerId, bool $apply): int
    {
        $poolIds = Pool::withTrashed()->where('customer_id', $customerId)->pluck('id');
        if ($poolIds->isEmpty()) {
            return 0;
        }
        $visitIds = ServiceVisit::query()->whereIn('pool_id', $poolIds)->pluck('id');
        if ($visitIds->isEmpty()) {
            return 0;
        }

        $disk = Storage::disk('public');
        $deleted = 0;
        foreach (VisitPhoto::query()->whereIn('service_visit_id', $visitIds)->get() as $photo) {
            $path = (string) $photo->getAttribute('photo_path');
            if ($path === '') {
                continue;
            }
            if (! $apply) {
                $deleted++;

                continue;
            }
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
            $deleted++;
        }

        return $deleted;
    }

    /** Prune (or count) read in-app notifications older than $days. */
    private function pruneReadNotifications(int $days, bool $apply): int
    {
        if ($days <= 0) {
            return 0;
        }

        $query = DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('created_at', '<', now()->subDays($days));

        return $apply ? $query->delete() : $query->count();
    }
}
