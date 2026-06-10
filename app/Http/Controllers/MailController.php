<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SendCampaign;
use App\Http\Requests\SendCampaignRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Sends a campaign composed from the People screen. The audience set + scope
 * are role-resolved in SendCampaign (super-admin = platform-wide).
 */
class MailController extends Controller
{
    public function send(SendCampaignRequest $request, SendCampaign $action): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $campaign = $action->handle($request->validated(), $user);

        return back()->with('success', "Sent to {$campaign->recipient_count} recipient(s).");
    }
}
