<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Say no to a customer's request, with the reason they'll see. A request is
 * answered once — false when it already was.
 */
class DeclineServiceRequest
{
    public function __construct(private readonly SendRequestAnswer $answer) {}

    public function handle(ServiceRequest $request, string $reason, User $by): bool
    {
        $declined = DB::transaction(function () use ($request, $reason, $by): bool {
            $locked = ServiceRequest::query()->lockForUpdate()->find($request->id);
            if ($locked === null || $locked->status !== 'pending') {
                return false;
            }

            $locked->forceFill([
                'status' => 'declined',
                'reply' => $reason,
                'resolved_by' => $by->id,
                'resolved_at' => now(),
            ])->save();

            return true;
        });

        if ($declined) {
            $this->answer->handle($request->refresh());
        }

        return $declined;
    }
}
