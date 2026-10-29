<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Models\ChemicalInventory;
use App\Models\Lead;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\TenantMailer;
use App\Support\OpsSignals;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The Advisories widget: what needs attention right now, worst first, each naming
 * the actual pools/customers/jobs (two, then "and N more") and linking to where
 * it gets fixed: the item itself when there's one, its list when there are several.
 * Admins see the company; a tech sees their own day. Tenant-scoped via the bound tenant.
 *
 * @phpstan-type Advisory array{sev: 'red'|'amber'|'info', icon: string, msg: string, href: string|null}
 */
final class Advisories
{
    private const RANK = ['red' => 0, 'amber' => 1, 'info' => 2];

    /** @var list<Advisory> */
    private array $out = [];

    /**
     * @param  array<string, mixed>|null  $weather  the dashboard's forecast (WeatherService shape)
     * @return list<Advisory>
     */
    public static function forAdmin(User $user, ?array $weather): array
    {
        $a = new self;
        $tenant = $user->tenant;
        $today = Tenant::localToday();

        if ($tenant !== null && TenantSetting::getFor($tenant->id, TenantMailer::FAILURE_KEY) !== null) {
            $a->add('red', 'MailWarning', 'Your mail server couldn’t send, so customer emails are going out from RoutePilot instead.', '/company?tab=email');
        }
        $a->missed(OpsSignals::missedYesterday(), 'yesterday');
        $a->workOrders(WorkOrder::query()->open()->where('urgent', true), 'red');

        $pools = OpsSignals::unassignedPools();
        if ($pools->isNotEmpty()) {
            $a->add('amber', 'UserX', self::names($pools->pluck('name')).' '.self::verb($pools, 'has', 'have').' a plan but no tech, so '
                .self::verb($pools, 'it', 'they').' won’t be routed.', self::link($pools, fn (Pool $p) => '/pools?selected='.$p->id, '/pools'));
        }
        if (OpsSignals::nothingTomorrow()) {
            $a->add('amber', 'CalendarClock', 'Nothing is scheduled for tomorrow.', '/schedule?date='.$today->copy()->addDay()->toDateString());
        }
        $owing = OpsSignals::overdueCustomers();
        if ($owing->isNotEmpty()) {
            $a->add('amber', 'DollarSign', self::names($owing->map->displayName()).' '.self::verb($owing, 'has a bill', 'have bills').' past due.',
                self::link($owing, fn ($c) => '/balances?selected='.$c->id, '/balances'));
        }
        $untested = OpsSignals::untestedPools();
        if ($untested->isNotEmpty()) {
            $a->add('amber', 'TestTube', self::names($untested->pluck('name')).' '.self::verb($untested, 'hasn’t', 'haven’t').' had a water test in over 2 weeks.',
                self::link($untested, fn (Pool $p) => '/pools?selected='.$p->id, '/pools'));
        }
        $waiting = ServiceRequest::query()->where('status', 'pending')->whereHas('customer')
            ->where('created_at', '<=', now()->subDays(2))->with('customer')->oldest()->get();
        if ($waiting->isNotEmpty()) {
            $from = self::names($waiting->map(fn (ServiceRequest $r) => (string) $r->customer?->displayName())->unique()->values());
            $a->add('amber', 'Inbox', ($waiting->count() === 1 ? $from.'’s request has' : 'Requests from '.$from.' have').' waited over 2 days for an answer.',
                self::link($waiting, fn (ServiceRequest $r) => '/inbox?selected='.$r->id, '/inbox'));
        }
        $leads = Lead::query()->where('status', 'new')->where('created_at', '<=', now()->subDay())->oldest()->get();
        if ($leads->isNotEmpty()) {
            $a->add('amber', 'UserPlus', ($leads->count() === 1 ? 'A website lead from ' : 'Website leads from ').self::names($leads->pluck('name'))
                .' '.self::verb($leads, 'hasn’t', 'haven’t').' been contacted for over a day.',
                self::link($leads, fn (Lead $l) => '/inbox?tab=leads&selected='.$l->id, '/inbox?tab=leads'));
        }
        $a->workOrders(WorkOrder::query()->open()->where('urgent', false)->whereDate('due_on', '<', $today), 'amber');
        $low = ChemicalInventory::query()->active()->orderBy('chemical_name')->get()->filter(fn (ChemicalInventory $c): bool => $c->isLowStock())->values();
        if ($low->isNotEmpty()) {
            $a->add('amber', 'FlaskConical', self::names($low->pluck('chemical_name')).' '.self::verb($low, 'is', 'are').' running low.',
                self::link($low, fn (ChemicalInventory $c) => '/inventory?selected='.$c->id, '/inventory'));
        }
        if ($tenant !== null && $tenant->formattedAddress() !== null && $tenant->lat === null) {
            $a->add('amber', 'MapPinOff', 'Your business address couldn’t be placed on the map, so the weather and your website’s service-area map can’t show.', '/company?tab=address');
        }

        // Techs with nothing today, on a day the others are working (a day off isn't news).
        if (Route::query()->whereDate('scheduled_date', $today)->exists()) {
            $idle = OpsSignals::idleTechs((int) $user->tenant_id);
            if ($idle->isNotEmpty()) {
                $a->add('info', 'Users', self::names($idle->map->displayName()).' '.self::verb($idle, 'has', 'have').' no stops today.', '/schedule');
            }
        }
        if ($tenant !== null && blank($tenant->logo_path)) {
            $a->add('info', 'Image', 'Add your logo: it goes on your emails, your website and the customer portal.', '/company');
        }
        if ($tenant !== null && blank($tenant->reply_to_email)) {
            $a->add('info', 'Reply', 'Set where customers’ replies go, so their answers to your emails reach you.', '/company');
        }
        $a->weather($weather);

        return $a->sorted();
    }

    /**
     * @param  array<string, mixed>|null  $weather  the dashboard's forecast (WeatherService shape)
     * @return list<Advisory>
     */
    public static function forAgent(User $user, ?array $weather): array
    {
        $a = new self;
        $mine = WorkOrder::query()->open()->visibleTo($user);

        $a->workOrders((clone $mine)->where('urgent', true), 'red');
        $a->missed(OpsSignals::missedYesterday($user->id), 'yesterday, on your route');
        $a->workOrders((clone $mine)->where('urgent', false)->whereDate('due_on', '<', Tenant::localToday()), 'amber');

        $stops = RouteStop::query()
            ->whereHas('route', fn ($q) => $q->where('agent_id', $user->id)->whereDate('scheduled_date', Tenant::localToday()))
            ->with('pool.serviceLocation')->orderBy('stop_order')->get();
        $pools = $stops->map->pool->filter()->unique('id')->values();

        $unplaced = $pools->filter(fn (Pool $p): bool => $p->coordinates() === null)->values();
        if ($unplaced->isNotEmpty()) {
            $a->add('amber', 'MapPinOff', self::names($unplaced->pluck('name')).' '.self::verb($unplaced, 'has', 'have').' no map position, so directions won’t work.',
                self::link($unplaced, fn (Pool $p) => '/pools?selected='.$p->id, '/pools'));
        }
        $jobPools = WorkOrder::query()->open()->whereIn('pool_id', $pools->pluck('id'))->pluck('pool_id')->all();
        $withJobs = $pools->filter(fn (Pool $p): bool => in_array($p->id, $jobPools, true))->values();
        if ($withJobs->isNotEmpty()) {
            $a->add('info', 'Wrench', self::names($withJobs->pluck('name')).', on today’s route, '.self::verb($withJobs, 'has an open work order', 'have open work orders')
                .': you’ll see '.self::verb($withJobs, 'it', 'them').' when you open the visit.', '/field');
        }
        if ($stops->isEmpty()) {
            $a->add('info', 'CalendarClock', 'You have no stops today.', null);
        }
        $a->weather($weather);

        return $a->sorted();
    }

    /** @param  Collection<int, RouteStop>  $stops */
    private function missed(Collection $stops, string $when): void
    {
        if ($stops->isEmpty()) {
            return;
        }
        $this->add('red', 'CalendarX', self::names($stops->map(fn (RouteStop $s) => (string) $s->pool?->name)).' '.self::verb($stops, 'was', 'were')
            .' missed '.$when.': nobody finished or skipped '.self::verb($stops, 'it', 'them').'.', '/schedule?date='.Tenant::localToday()->subDay()->toDateString());
    }

    /** @param  Builder<WorkOrder>  $query */
    private function workOrders($query, string $sev): void
    {
        $orders = $query->pressingFirst()->get(['id', 'title']);
        if ($orders->isEmpty()) {
            return;
        }
        $titles = self::names($orders->map(fn (WorkOrder $o) => '“'.$o->title.'”'));
        $msg = $sev === 'red'
            ? ($orders->count() === 1 ? 'Urgent work order: ' : 'Urgent work orders: ').$titles.'.'
            : ($orders->count() === 1 ? 'Work order '.$titles.' is past its due date.' : 'Work orders '.$titles.' are past their due dates.');
        $this->add($sev === 'red' ? 'red' : 'amber', 'Wrench', $msg, self::link($orders, fn (WorkOrder $o) => '/work-orders?selected='.$o->id, '/work-orders'));
    }

    /**
     * Storms, rain or snow coming in the next hours, and heat or frost, from the forecast.
     *
     * @param  array<string, mixed>|null  $weather
     */
    private function weather(?array $weather): void
    {
        $hours = collect(is_array($weather['hours'] ?? null) ? $weather['hours'] : [])->filter(fn ($h) => is_array($h))->values();
        if ($hours->isEmpty()) {
            return;
        }
        $storm = $hours->first(fn (array $h) => (int) $h['code'] >= 95);
        $wet = $hours->first(fn (array $h) => (int) $h['precip'] >= 50);
        if ($storm !== null) {
            $this->add('amber', 'CloudLightning', 'Thunderstorms likely around '.$storm['hour'].': stop work while there’s lightning.', null);
        } elseif ($wet !== null) {
            $snow = in_array((int) $wet['code'], [71, 73, 75, 77, 85, 86], true);
            $this->add('info', $snow ? 'CloudSnow' : 'CloudRain', ($snow ? 'Snow' : 'Rain').' likely around '.$wet['hour'].' ('.$wet['precip'].'%).', null);
        }
        $high = (int) $hours->max('temp');
        $low = (int) $hours->min('temp');
        if ($high >= 95) {
            $this->add('amber', 'ThermometerSun', 'Up to '.$high.'° in the next few hours: drink water and take breaks in the shade.', null);
        } elseif ($low <= 32) {
            $this->add('amber', 'Snowflake', 'Down to '.$low.'° in the next few hours: watch for ice and check freeze protection.', null);
        }
    }

    private function add(string $sev, string $icon, string $msg, ?string $href): void
    {
        /** @var 'red'|'amber'|'info' $sev */
        $this->out[] = ['sev' => $sev, 'icon' => $icon, 'msg' => $msg, 'href' => $href];
    }

    /** @return list<Advisory> */
    private function sorted(): array
    {
        $out = $this->out;
        usort($out, fn (array $a, array $b): int => self::RANK[$a['sev']] <=> self::RANK[$b['sev']]); // stable: keeps each group's order

        return $out;
    }

    /**
     * "Cruz", "Cruz and Park", "Cruz, Park and 3 more".
     *
     * @param  Collection<int, mixed>  $names
     */
    private static function names(Collection $names): string
    {
        $names = $names->map(fn ($n) => (string) $n)->values();

        return match (true) {
            $names->count() <= 1 => (string) $names->first(),
            $names->count() === 2 => $names[0].' and '.$names[1],
            default => $names[0].', '.$names[1].' and '.($names->count() - 2).' more',
        };
    }

    /** @param  Collection<int, mixed>  $items */
    private static function verb(Collection $items, string $one, string $many): string
    {
        return $items->count() === 1 ? $one : $many;
    }

    /**
     * The one item's own page, or the list when there are several.
     *
     * @template T
     *
     * @param  Collection<int, T>  $items
     * @param  callable(T): string  $one
     */
    private static function link(Collection $items, callable $one, string $list): string
    {
        return $items->count() === 1 ? $one($items->first()) : $list;
    }
}
