<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Pool;
use App\Models\ServiceVisit;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tenant insights — revenue, visit volume, AR, tech productivity. Read-only,
 * tenant_admin only. (Super-admin MRR/churn arrives with platform billing.)
 */
class AnalyticsController extends Controller
{
    public function index(Request $request, BillingService $billing): Response
    {
        $this->authorizeAdmin($request);

        $monthStart = now()->startOfMonth();
        $weekStart = now()->startOfWeek();

        $balances = $billing->outstandingBalances();

        $visitCounts = ServiceVisit::query()
            ->where('status', 'completed')->where('completed_at', '>=', $monthStart)
            ->get(['agent_id'])
            ->countBy('agent_id');
        $agents = User::query()->whereIn('id', $visitCounts->keys())->get()->keyBy('id');
        $topAgents = $visitCounts->sortDesc()->take(5)
            ->map(fn (int $count, int|string $agentId): array => [
                'name' => $agents->get((int) $agentId)?->displayName() ?? '—',
                'visits' => $count,
            ])->values()->all();

        // ── Trends for the charts (tenant-scoped by the global scope) ──
        $revStart = now()->startOfMonth()->subMonths(11);
        $revByMonth = Payment::query()
            ->where('status', 'succeeded')
            ->where('paid_at', '>=', $revStart)
            ->get(['amount', 'paid_at'])
            ->groupBy(fn (Payment $p): string => $p->paid_at?->format('Y-m') ?? '')
            ->map(fn (Collection $rows): float => round((float) $rows->sum(fn (Payment $p): float => (float) $p->amount), 2));
        $revenueSeries = collect(range(0, 11))->map(function (int $i) use ($revStart, $revByMonth): array {
            $m = $revStart->copy()->addMonths($i);

            return ['label' => $m->format('M'), 'value' => (float) ($revByMonth[$m->format('Y-m')] ?? 0)];
        })->all();

        $visStart = now()->startOfWeek()->subWeeks(11);
        $visByWeek = ServiceVisit::query()
            ->where('status', 'completed')
            ->where('completed_at', '>=', $visStart)
            ->get(['completed_at'])
            ->groupBy(fn (ServiceVisit $v): string => $v->completed_at?->copy()->startOfWeek()->format('Y-m-d') ?? '')
            ->map(fn (Collection $rows): int => $rows->count());
        $visitsSeries = collect(range(0, 11))->map(function (int $i) use ($visStart, $visByWeek): array {
            $w = $visStart->copy()->addWeeks($i);

            return ['label' => $w->format('M j'), 'value' => (int) ($visByWeek[$w->format('Y-m-d')] ?? 0)];
        })->all();

        // AR aging — outstanding balance on issued invoices, bucketed by days past due.
        $today = now()->startOfDay();
        $ageBuckets = ['Current' => 0.0, '1–30' => 0.0, '31–60' => 0.0, '61–90' => 0.0, '90+' => 0.0];
        Invoice::query()
            ->whereNotNull('issued_at')
            ->whereColumn('amount_paid', '<', 'total')
            ->get(['total', 'amount_paid', 'due_at'])
            ->each(function (Invoice $inv) use (&$ageBuckets, $today): void {
                $bal = round((float) $inv->total - (float) $inv->amount_paid, 2);
                if ($bal <= 0) {
                    return;
                }
                $due = $inv->due_at;
                $overdue = ($due !== null && $due->lt($today)) ? (int) $due->diffInDays($today) : 0;
                $key = match (true) {
                    $overdue <= 0 => 'Current',
                    $overdue <= 30 => '1–30',
                    $overdue <= 60 => '31–60',
                    $overdue <= 90 => '61–90',
                    default => '90+',
                };
                $ageBuckets[$key] += $bal;
            });
        $arAging = collect($ageBuckets)->map(fn (float $v, string $k): array => ['label' => $k, 'value' => round($v, 2)])->values()->all();

        return Inertia::render('reports/Insights', [
            'revenue_month' => round((float) Payment::query()->where('status', 'succeeded')->where('paid_at', '>=', $monthStart)->sum('amount'), 2),
            'outstanding' => round((float) $balances->sum(fn (array $r): float => $r['balance']), 2),
            'overdue_invoices' => Invoice::query()->where('status', 'overdue')->count(),
            'visits_month' => ServiceVisit::query()->where('status', 'completed')->where('completed_at', '>=', $monthStart)->count(),
            'visits_week' => ServiceVisit::query()->where('status', 'completed')->where('completed_at', '>=', $weekStart)->count(),
            'active_pools' => Pool::query()->count(),
            'active_agents' => User::query()->where('role', 'agent')->where('is_active', true)->count(),
            'top_agents' => $topAgents,
            'revenue_series' => $revenueSeries,
            'visits_series' => $visitsSeries,
            'ar_aging' => $arAging,
            'leads' => Lead::query()->latest()->limit(50)->get()->map(fn (Lead $l): array => [
                'id' => $l->id,
                'name' => $l->name,
                'email' => $l->email,
                'phone' => $l->getAttribute('phone'),
                'message' => $l->getAttribute('message'),
                'source' => $l->source,
                'status' => $l->status,
                'on' => $l->created_at?->toDateString(),
            ])->all(),
            'new_leads' => Lead::query()->where('status', 'new')->count(),
        ]);
    }
}
