<?php

declare(strict_types=1);

namespace App\Services\Chat\Tools;

use App\Models\Lead;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Chat\AssistantTool;

/** Office: customers' requests and website leads waiting on the Requests page. */
class LookupRequests extends AssistantTool
{
    public function name(): string
    {
        return 'lookup_requests';
    }

    public function description(): string
    {
        return 'Look up what\'s on the Requests page: customer requests (add a service, pause, stop, a message) or website '
            .'leads. Open ones by default, or the closed ones.';
    }

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'kind' => ['type' => 'string', 'enum' => ['requests', 'leads'], 'description' => 'Customer requests (default) or website leads.'],
                'status' => ['type' => 'string', 'enum' => ['open', 'closed'], 'description' => 'Still to do (default) or already dealt with.'],
                'customer_name' => ['type' => 'string', 'description' => 'Just this customer\'s requests (optional).'],
            ],
            'required' => [],
        ];
    }

    public function availableTo(User $user): bool
    {
        return $user->role === 'tenant_admin';
    }

    public function execute(array $params, User $user): string
    {
        $open = ($params['status'] ?? 'open') !== 'closed';

        return ($params['kind'] ?? 'requests') === 'leads'
            ? $this->leads($user, $open)
            : $this->requests($user, $open, trim((string) ($params['customer_name'] ?? '')));
    }

    private function requests(User $user, bool $open, string $name): string
    {
        if ($name !== '' && ($problem = $this->nameProblem($name)) !== null) {
            return $problem;
        }

        // Requests from archived customers drop out, as on the Requests page.
        $query = ServiceRequest::query()
            ->where('service_requests.tenant_id', (int) $user->tenant_id)
            ->whereIn('status', $open ? ['pending'] : ['approved', 'declined'])
            ->whereHas('customer', fn ($c) => $name !== '' ? $this->whereNameLike($c, $name) : $c);
        $total = (clone $query)->count();
        $rows = $query->with('customer', 'pool', 'serviceType')->latest('id')->limit(self::ROWS)->get();

        if ($rows->isEmpty()) {
            return 'No '.($open ? 'open' : 'closed').' customer requests'.($name !== '' ? " from \"{$name}\"" : '').'.';
        }

        return ($open ? 'Open' : 'Closed')." customer requests ({$total}):\n".$rows->map(fn (ServiceRequest $r): string => '- '
            .self::link('request', $r->id, $r->title()).' — '.($r->customer?->displayName() ?? '?')
            .($r->pool !== null ? ' — '.$r->pool->name : '')
            .($r->preferred_date !== null ? ' — wants '.$r->preferred_date->format('M j') : '')
            .($r->ends_on !== null ? ' until '.$r->ends_on->format('M j') : '')
            .' — '.$r->statusLabel().', sent '.self::day($r->created_at, 'M j')
            .(($m = self::note($r->message)) !== null ? "\n  Message: {$m}" : ''))->join("\n")
            .self::more($total, $rows->count());
    }

    private function leads(User $user, bool $open): string
    {
        $query = Lead::query()
            ->where('tenant_id', (int) $user->tenant_id)
            ->whereIn('status', $open ? ['new', 'contacted'] : ['converted', 'archived']);
        $total = (clone $query)->count();
        $rows = $query->with('serviceType')->latest('id')->limit(self::ROWS)->get();

        if ($rows->isEmpty()) {
            return 'No '.($open ? 'open' : 'closed').' website leads.';
        }

        return ($open ? 'Open' : 'Closed')." website leads ({$total}):\n".$rows->map(fn (Lead $l): string => '- '
            .self::link('lead', $l->id, $l->name)
            .' — '.collect([$l->email, $l->getAttribute('phone')])->filter()->join(', ')
            .($l->serviceType !== null ? ' — wants '.$l->serviceType->getAttribute('name') : '')
            ." — {$l->status}, from the {$l->source}, ".self::day($l->created_at, 'M j')
            .(($m = self::note($l->getAttribute('message'))) !== null ? "\n  Message: {$m}" : ''))->join("\n")
            .self::more($total, $rows->count());
    }
}
