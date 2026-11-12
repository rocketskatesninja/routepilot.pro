<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceSubscription;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A look-up the in-app assistant can run for the signed-in person. It only
 * reads, and only what that person could see on their own pages: who they are
 * and which company comes from their login, never from the model. A record
 * outside their reach reads as "not found", the same as one that doesn't exist.
 *
 * Results carry ready-made links ([[type:id:Name]]) for the model to copy, and
 * wrap anything people typed (notes, messages) in [note]…[/note] so the model
 * treats it as information, never as instructions.
 */
abstract class AssistantTool extends AiTool
{
    /** The most records one look-up lists. */
    protected const ROWS = 25;

    /** Whether this person is offered the tool at all (role, Agent Plus, feature switches). */
    abstract public function availableTo(User $user): bool;

    /**
     * Run the look-up for this person and describe what it found.
     *
     * @param  array<string, mixed>  $params
     */
    abstract public function execute(array $params, User $user): string;

    /** The person's company. */
    protected function tenant(User $user): ?Tenant
    {
        return Tenant::query()->find($user->tenant_id);
    }

    /** A homeowner's own customer record (null when the login has none). */
    protected function customer(User $user): ?Customer
    {
        return Customer::query()->where('tenant_id', (int) $user->tenant_id)->where('user_id', $user->id)->first();
    }

    /** What a homeowner's look-up says when their login has no customer record. */
    protected const NO_CUSTOMER = 'There is no customer account linked to this login.';

    /** Why a name search can't run (no word of two letters or more), or null when it can. */
    protected function nameProblem(string $name): ?string
    {
        return $this->tokens($name) === [] ? 'Ask for at least part of a name (two letters or more), then search again.' : null;
    }

    /**
     * Fuzzy, order-independent name match: every word (two letters or more)
     * must appear in the first or last name, so "John Smith" and "Smith John"
     * both match. Column names are caller-fixed; values are bound. Callers
     * check nameProblem() first: with no usable word this matches nothing.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function whereNameLike(Builder $query, string $name, string $firstCol = 'first_name', string $lastCol = 'last_name'): Builder
    {
        $tokens = $this->tokens($name);
        if ($tokens === []) {
            return $query->whereRaw('1 = 0');
        }
        foreach ($tokens as $token) {
            $like = '%'.$token.'%';
            $query->where(fn ($q) => $q->where($firstCol, 'like', $like)->orWhere($lastCol, 'like', $like));
        }

        return $query;
    }

    /** A link the model copies into its answer: [[type:id:Label]] (lib/assistantLinks.ts turns it into one). */
    public static function link(string $type, int|string $id, string $label): string
    {
        $label = trim(str_replace(['[', ']', "\n", "\r"], ['(', ')', ' ', ' '], $label));

        return "[[{$type}:{$id}:".($label !== '' ? $label : ucfirst($type)).']]';
    }

    /** Text someone typed, marked as information only. Null when there's nothing. */
    protected static function note(?string $text): ?string
    {
        $text = trim(str_ireplace(['[note]', '[/note]'], '', (string) $text));

        return $text === '' ? null : "[note]{$text}[/note]";
    }

    /** "(Showing 25 of 80: ask for a narrower search…)" when a list was cut short. */
    protected static function more(int $total, int $shown): string
    {
        return $total > $shown ? "\n(Showing {$shown} of {$total}. Ask a narrower question to see the others.)" : '';
    }

    /**
     * The pools this person can see: the office (and an Agent Plus tech) sees
     * the company's, a tech the ones on their active plans or that they've
     * visited, a customer their own. Archived pools are out.
     *
     * @return Builder<Pool>
     */
    protected function poolsFor(User $user): Builder
    {
        $query = Pool::query()->where('pools.tenant_id', (int) $user->tenant_id);

        return match ($user->role) {
            'tenant_admin' => $query,
            'agent' => $user->isAgentPlus() ? $query : $query->where(fn ($q) => $q
                ->whereHas('subscriptions', fn ($s) => $s->where('assigned_agent_id', $user->id)->where('status', 'active'))
                ->orWhereHas('visits', fn ($v) => $v->where('agent_id', $user->id))),
            'customer' => $query->whereHas('customer', fn ($c) => $c->where('user_id', $user->id)),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * The pools a look-up is about: by pool_id, or by customer name (customers
     * just get their own) and an optional pool name. When the name fits more
     * than one customer, the answer is the choices, never a guess.
     *
     * @param  array<string, mixed>  $params
     * @return Collection<int, Pool>|string
     */
    protected function resolvePools(User $user, array $params): Collection|string
    {
        $query = $this->poolsFor($user)->with('customer');
        $poolId = is_numeric($params['pool_id'] ?? null) ? (int) $params['pool_id'] : 0;
        if ($poolId > 0) {
            $pool = $query->whereKey($poolId)->first();

            return $pool !== null ? new Collection([$pool]) : 'No pool found with that id.';
        }

        $name = trim((string) ($params['customer_name'] ?? ''));
        $poolName = trim((string) ($params['pool_name'] ?? ''));
        if ($user->role !== 'customer') {
            if (($problem = $this->nameProblem($name)) !== null) {
                return $problem;
            }
            $query->whereHas('customer', fn ($c) => $this->whereNameLike($c, $name));
        }
        if ($poolName !== '') {
            $query->where('name', 'like', '%'.$poolName.'%');
        }

        $pools = $query->orderBy('name')->limit(self::ROWS)->get();
        if ($pools->isEmpty()) {
            return 'No pool found'.($name !== '' ? " for \"{$name}\"" : '').($poolName !== '' ? " named \"{$poolName}\"" : '').'.';
        }
        $customers = $pools->map(fn (Pool $p) => $p->customer)->filter()->unique('id');
        if ($customers->count() > 1) {
            return "More than one customer matches \"{$name}\":\n"
                .$customers->map(fn (Customer $c): string => '- '.self::link('customer', $c->id, $c->displayName()))->join("\n")
                ."\nAsk which one they mean, then search again with the full name.";
        }

        return $pools;
    }

    /**
     * One pool out of resolvePools(), or the question "which one?".
     *
     * @param  Collection<int, Pool>  $pools
     */
    protected function onePool(Collection $pools): Pool|string
    {
        if ($pools->count() === 1) {
            return $pools->first();
        }

        return ($pools->first()?->customer?->displayName() ?? 'They')." has {$pools->count()} pools:\n"
            .$pools->map(fn (Pool $p): string => '- '.self::link('pool', $p->id, $p->name)." (pool_id {$p->id})")->join("\n")
            ."\nAsk which one, then look it up again with its pool_id.";
    }

    /** "Weekly Cleaning (every Tuesday), $45.00/visit, tech Marcus Bennett, paused Nov 20 – Dec 4". */
    protected function planLine(ServiceSubscription $s, bool $withPrice = true): string
    {
        $parts = [
            ($s->serviceType?->getAttribute('name') ?? 'Service').' ('.$s->scheduleLabel().')',
            $withPrice ? '$'.number_format((float) $s->price, 2).($s->frequency === 'one_time' ? '' : '/visit') : null,
            'tech '.($s->agent?->displayName() ?? 'not assigned'),
            $s->status !== 'active' ? $s->status : null,
            $s->hold_starts_at !== null ? 'paused '.$s->hold_starts_at->format('M j').($s->hold_ends_at !== null ? ' – '.$s->hold_ends_at->format('M j') : '') : null,
            $s->ends_on !== null ? 'ends '.$s->ends_on->format('M j, Y') : null,
        ];

        return implode(', ', array_filter($parts));
    }

    /** A tech without Agent Plus sees only their own visits (as on the Reports page). */
    protected function ownVisitsOnly(User $user): bool
    {
        return $user->role === 'agent' && ! $user->isAgentPlus();
    }

    /**
     * The day asked for, in the company's time zone: a weekday word is the
     * coming one (today if it's today), so the model never does date sums.
     * Null when it can't be read.
     */
    protected function resolveDay(string $raw): ?Carbon
    {
        $today = Tenant::localToday();
        $raw = (string) preg_replace('/^(next|this|on|coming)\s+/', '', strtolower(trim($raw)));

        if ($raw === '' || $raw === 'today') {
            return $today;
        }
        if ($raw === 'tomorrow' || $raw === 'yesterday') {
            return $today->copy()->addDays($raw === 'tomorrow' ? 1 : -1);
        }
        foreach (['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as $index => $name) {
            if (strlen($raw) >= 3 && str_starts_with($name, $raw)) {
                return $today->dayOfWeek === $index ? $today : $today->copy()->next($index);
            }
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            return Carbon::parse($raw, $today->getTimezone());
        }

        return null;
    }

    /** What a day parameter accepts, for tool schemas. */
    protected const DAY_HELP = 'The day: a weekday as a word ("monday" = the coming Monday), "today", "tomorrow", "yesterday", or a date as YYYY-MM-DD. Pass weekdays as words: the date is worked out for you. Leave out for today.';

    /** A stored moment as the company's local date, e.g. "Nov 17, 2026". */
    protected static function day(?CarbonInterface $at, string $format = 'M j, Y'): string
    {
        return $at !== null ? Tenant::local($at)->format($format) : 'unknown date';
    }

    /** @return list<string> */
    private function tokens(string $name): array
    {
        return array_values(array_filter(
            preg_split('/\s+/', trim($name)) ?: [],
            fn (string $t): bool => mb_strlen($t) >= 2,
        ));
    }
}
