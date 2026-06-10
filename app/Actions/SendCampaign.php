<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mail\CampaignMail;
use App\Models\Customer;
use App\Models\MailCampaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Resolve a campaign's audience (role-scoped) and queue a branded email to
 * each recipient. A super-admin can blast every tenant admin / agent /
 * customer across the platform; a tenant admin only their own customers +
 * agents. Customer audiences honour marketing opt-outs.
 */
class SendCampaign
{
    /**
     * @param  array<string, mixed>  $data  validated {audience, subject, body}
     */
    public function handle(array $data, User $sender): MailCampaign
    {
        $audience = (string) $data['audience'];
        $subject = (string) $data['subject'];
        $body = (string) $data['body'];
        $recipients = $this->recipients($audience, $sender);

        $campaign = MailCampaign::create([
            'created_by' => $sender->id,
            'subject' => $subject,
            'body' => $body,
            'audience' => $audience,
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

    /**
     * Audiences this sender may target, with live recipient counts.
     *
     * @return list<array{key: string, label: string, count: int}>
     */
    public function audiencesFor(User $sender): array
    {
        $defs = $sender->isSuperAdmin()
            ? [['tenants', 'All tenant admins'], ['agents', 'All agents'], ['customers', 'All customers']]
            : [['customers', 'All customers'], ['agents', 'All agents']];

        return array_map(fn (array $d): array => [
            'key' => $d[0],
            'label' => $d[1],
            'count' => $this->count($d[0], $sender),
        ], $defs);
    }

    public function count(string $audience, User $sender): int
    {
        return match ($audience) {
            'tenants' => $sender->isSuperAdmin() ? $this->staffQuery('tenant_admin', $sender)->count() : 0,
            'agents' => $this->staffQuery('agent', $sender)->count(),
            'customers' => $this->customerQuery()->count(),
            default => 0,
        };
    }

    /**
     * @return list<array{email: string, name: string, customer_id: int|null}>
     */
    private function recipients(string $audience, User $sender): array
    {
        return match ($audience) {
            'tenants' => $sender->isSuperAdmin() ? $this->mapStaff($this->staffQuery('tenant_admin', $sender)) : [],
            'agents' => $this->mapStaff($this->staffQuery('agent', $sender)),
            'customers' => $this->customerQuery()->get()->map(fn (Customer $c): array => [
                'email' => (string) $c->email,
                'name' => $c->displayName(),
                'customer_id' => $c->id,
            ])->all(),
            default => [],
        };
    }

    /**
     * Active staff of a role with an email. A super-admin spans all tenants;
     * a tenant admin is restricted to their own.
     *
     * @return Builder<User>
     */
    private function staffQuery(string $role, User $sender): Builder
    {
        return User::query()
            ->where('role', $role)->where('is_active', true)->whereNotNull('email')
            ->when(! $sender->isSuperAdmin(), fn (Builder $q) => $q->where('tenant_id', $sender->tenant_id));
    }

    /**
     * Emailable, non-opted-out customers. The global TenantScope restricts a
     * tenant admin to their own; for an (unscoped) super-admin it spans all.
     *
     * @return Builder<Customer>
     */
    private function customerQuery(): Builder
    {
        return Customer::query()->whereNotNull('email')->where('email_opt_out', false);
    }

    /**
     * @param  Builder<User>  $query
     * @return list<array{email: string, name: string, customer_id: null}>
     */
    private function mapStaff(Builder $query): array
    {
        return $query->get()->map(fn (User $u): array => [
            'email' => (string) $u->getAttribute('email'),
            'name' => $u->displayName(),
            'customer_id' => null,
        ])->all();
    }
}
