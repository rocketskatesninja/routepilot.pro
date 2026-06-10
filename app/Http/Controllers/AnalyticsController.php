<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pool;
use App\Models\ServiceVisit;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Http\Request;
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
        abort_unless($request->user()?->role === 'tenant_admin', 403);

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

        return Inertia::render('reports/Insights', [
            'revenue_month' => round((float) Payment::query()->where('status', 'succeeded')->where('paid_at', '>=', $monthStart)->sum('amount'), 2),
            'outstanding' => round((float) $balances->sum(fn (array $r): float => $r['balance']), 2),
            'overdue_invoices' => Invoice::query()->where('status', 'overdue')->count(),
            'visits_month' => ServiceVisit::query()->where('status', 'completed')->where('completed_at', '>=', $monthStart)->count(),
            'visits_week' => ServiceVisit::query()->where('status', 'completed')->where('completed_at', '>=', $weekStart)->count(),
            'active_pools' => Pool::query()->count(),
            'active_agents' => User::query()->where('role', 'agent')->where('is_active', true)->count(),
            'top_agents' => $topAgents,
        ]);
    }
}
