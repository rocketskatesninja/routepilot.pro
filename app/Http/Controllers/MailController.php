<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SendCampaign;
use App\Http\Requests\SendCampaignRequest;
use App\Models\MailCampaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Back-office mailing — an inline composer (audience presets that honour
 * opt-outs) and a campaign history log. tenant_admin only.
 */
class MailController extends Controller
{
    /** @var list<array{key: string, label: string}> */
    private const AUDIENCES = [
        ['key' => 'customers', 'label' => 'All customers'],
        ['key' => 'agents', 'label' => 'All agents'],
        ['key' => 'overdue', 'label' => 'Overdue balance'],
        ['key' => 'no_recent_visit', 'label' => 'No visit in 30 days'],
    ];

    public function index(Request $request, SendCampaign $sender): Response
    {
        $user = $request->user();
        abort_unless($user?->role === 'tenant_admin', 403);
        $tenantId = (int) $user->tenant_id;

        $campaigns = MailCampaign::query()
            ->with('creator:id,first_name,last_name')
            ->latest('sent_at')->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (MailCampaign $c): array => [
                'id' => $c->id,
                'subject' => $c->subject,
                'audience' => $c->audience,
                'recipients' => $c->recipient_count,
                'sent' => $c->sent_count,
                'failed' => $c->failed_count,
                'sent_on' => $c->sent_at?->toDateString(),
                'by' => $c->creator?->displayName(),
            ])->all();

        $audiences = array_map(fn (array $a): array => [
            ...$a,
            'count' => $sender->count($a['key'], $tenantId),
        ], self::AUDIENCES);

        return Inertia::render('mail/Index', [
            'campaigns' => $campaigns,
            'audiences' => $audiences,
        ]);
    }

    public function send(SendCampaignRequest $request, SendCampaign $action): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $campaign = $action->handle($request->validated(), $user);

        return back()->with('success', "Sent to {$campaign->recipient_count} recipient(s).");
    }
}
