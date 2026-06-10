<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mail\CampaignMail;
use App\Models\Customer;
use App\Models\MailCampaign;
use App\Models\ServiceVisit;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Resolve a campaign's audience (honouring marketing opt-outs), record the
 * MailCampaign, and queue a branded email to each recipient.
 */
class SendCampaign
{
    public function __construct(private BillingService $billing) {}

    /**
     * @param  array<string, mixed>  $data  validated {audience, subject, body}
     */
    public function handle(array $data, User $sender): MailCampaign
    {
        $audience = (string) $data['audience'];
        $subject = (string) $data['subject'];
        $body = (string) $data['body'];
        $recipients = $this->recipients($audience, (int) $sender->tenant_id);

        $campaign = MailCampaign::create([
            'created_by' => $sender->id,
            'subject' => $subject,
            'body' => $body,
            'audience' => $audience === 'agents' ? 'agents' : 'customers',
            'recipient_count' => count($recipients),
            'sent_count' => count($recipients),
            'failed_count' => 0,
            'sent_at' => now(),
        ]);

        foreach ($recipients as $recipient) {
            $unsubscribe = $recipient['customer_id'] !== null
                ? URL::signedRoute('unsubscribe', ['customer' => $recipient['customer_id']])
                : null;

            Mail::to($recipient['email'])->queue(new CampaignMail($subject, $body, $recipient['name'], $unsubscribe));
        }

        return $campaign;
    }

    /** Live recipient count for the composer. */
    public function count(string $audience, int $tenantId): int
    {
        return count($this->recipients($audience, $tenantId));
    }

    /**
     * @return list<array{email: string, name: string, customer_id: int|null}>
     */
    private function recipients(string $audience, int $tenantId): array
    {
        if ($audience === 'agents') {
            return User::query()
                ->where('tenant_id', $tenantId)->where('role', 'agent')->where('is_active', true)
                ->whereNotNull('email')
                ->get()
                ->map(fn (User $u): array => ['email' => (string) $u->getAttribute('email'), 'name' => $u->displayName(), 'customer_id' => null])
                ->all();
        }

        // Customer audiences (marketing) — exclude opt-outs.
        $customers = Customer::query()->whereNotNull('email')->where('email_opt_out', false)->with('pools')->get();

        $customers = match ($audience) {
            'overdue' => $customers->filter(fn (Customer $c): bool => $this->billing->outstandingForCustomer($c) > 0),
            'no_recent_visit' => $customers->filter(fn (Customer $c): bool => $this->noVisitSince($c, 30)),
            default => $customers,
        };

        return $customers->map(fn (Customer $c): array => ['email' => (string) $c->email, 'name' => $c->displayName(), 'customer_id' => $c->id])->values()->all();
    }

    private function noVisitSince(Customer $customer, int $days): bool
    {
        $poolIds = $customer->pools->pluck('id');
        if ($poolIds->isEmpty()) {
            return true;
        }

        return ! ServiceVisit::query()
            ->whereIn('pool_id', $poolIds)
            ->where('status', 'completed')
            ->where('completed_at', '>=', Carbon::now()->subDays($days))
            ->exists();
    }
}
