<?php

declare(strict_types=1);

namespace App\Actions;

use App\Jobs\SendCampaignEmail;
use App\Models\Customer;
use App\Models\MailCampaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
        $recipients = $audience === 'selected'
            ? $this->resolveSelected(is_array($data['recipients'] ?? null) ? $data['recipients'] : [], $sender)
            : $this->recipients($audience, $sender);

        $campaign = MailCampaign::create([
            'created_by' => $sender->id,
            'subject' => $subject,
            'body' => $body,
            'audience' => $audience,
            'recipient_count' => count($recipients),
            'sent_count' => 0,   // tallied for real by the queue as each email lands
            'failed_count' => 0,
            'sent_at' => now(),
        ]);

        foreach ($recipients as $recipient) {
            // One tracked delivery row per address; the job flips it sent/failed.
            $row = $campaign->recipients()->create([
                'email' => $recipient['email'],
                'name' => $recipient['name'],
                'status' => 'queued',
            ]);

            $unsubscribe = $recipient['customer_id'] !== null
                ? URL::signedRoute('unsubscribe', ['customer' => $recipient['customer_id']])
                : null;

            SendCampaignEmail::dispatch($recipient['email'], $subject, $body, $recipient['name'], $unsubscribe, $sender->tenant_id, $row->id);
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
     * Resolve hand-picked recipient keys ("customer:5" / "agent:3" /
     * "tenant:2") to emails — ownership-checked: a tenant admin only resolves
     * their own customers/agents; tenant keys are super-admin only.
     *
     * @param  array<int, mixed>  $keys
     * @return list<array{email: string, name: string, customer_id: int|null}>
     */
    private function resolveSelected(array $keys, User $sender): array
    {
        $customerIds = [];
        $agentIds = [];
        $tenantIds = [];
        foreach ($keys as $key) {
            if (! is_string($key) || ! str_contains($key, ':')) {
                continue;
            }
            [$type, $raw] = explode(':', $key, 2);
            $id = (int) $raw;
            if ($id <= 0) {
                continue;
            }
            match ($type) {
                'customer' => $customerIds[] = $id,
                'agent' => $agentIds[] = $id,
                'tenant' => $tenantIds[] = $id,
                default => null,
            };
        }

        $out = [];

        if ($customerIds !== []) {
            // Customer global scope restricts a tenant admin to their own.
            foreach ($this->customerQuery()->whereKey($customerIds)->get() as $c) {
                $out[] = ['email' => (string) $c->email, 'name' => $c->displayName(), 'customer_id' => $c->id];
            }
        }

        if ($agentIds !== []) {
            foreach ($this->staffQuery('agent', $sender)->whereKey($agentIds)->get() as $u) {
                $out[] = ['email' => (string) $u->getAttribute('email'), 'name' => $u->displayName(), 'customer_id' => null];
            }
        }

        if ($tenantIds !== [] && $sender->isSuperAdmin()) {
            $admins = User::query()
                ->where('role', 'tenant_admin')->where('is_active', true)->whereNotNull('email')
                ->whereIn('tenant_id', $tenantIds)->get();
            foreach ($admins as $u) {
                $out[] = ['email' => (string) $u->getAttribute('email'), 'name' => $u->displayName(), 'customer_id' => null];
            }
        }

        return $out;
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
