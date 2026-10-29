<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Delete customer requests or website leads for good, each one audit-logged.
 * The lookups are tenant-scoped, so ids from another company are ignored.
 * What they led to stays: a converted lead's customer, an approved request's plan.
 * The audit keeps what kind of item it was, not the person's details.
 */
class DeleteFromInbox
{
    /**
     * @param  list<int>  $ids
     * @return int how many were deleted
     */
    public function requests(array $ids, User $by): int
    {
        return DB::transaction(function () use ($ids, $by): int {
            $requests = ServiceRequest::query()->whereKey($ids)->lockForUpdate()->get();
            foreach ($requests as $request) {
                AuditLog::record($by, 'request.deleted', $request, ['type' => $request->type, 'status' => $request->status]);
                $request->delete();
            }

            return $requests->count();
        });
    }

    /**
     * @param  list<int>  $ids
     * @return int how many were deleted
     */
    public function leads(array $ids, User $by): int
    {
        return DB::transaction(function () use ($ids, $by): int {
            $leads = Lead::query()->whereKey($ids)->lockForUpdate()->get();
            foreach ($leads as $lead) {
                AuditLog::record($by, 'lead.deleted', $lead, ['source' => $lead->source, 'status' => $lead->status]);
                $lead->delete();
            }

            return $leads->count();
        });
    }
}
