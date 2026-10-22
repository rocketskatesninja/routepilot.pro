<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AddWorkOrderComment;
use App\Actions\CreateWorkOrder;
use App\Actions\SetWorkOrderDone;
use App\Actions\UpdateWorkOrder;
use App\Http\Requests\WorkOrderCommentRequest;
use App\Http\Requests\WorkOrderRequest;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderComment;
use App\Policies\WorkOrderPolicy;
use App\Support\OptionLists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The company's work orders: a task list fed by techs from their visits and by
 * the office. Open ones sort urgent first, then by due date (overdue first),
 * then newest. Who sees and does what is WorkOrderPolicy.
 */
class WorkOrderController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', WorkOrder::class);
        /** @var User $user */
        $user = $request->user();
        $runsTheList = WorkOrderPolicy::runsTheList($user);
        $status = $request->string('status')->toString() === 'done' ? 'done' : 'open';
        $mine = $runsTheList && $request->boolean('mine');

        $orders = WorkOrder::query()
            ->with(['pool:id,name,customer_id', 'pool.customer:id,first_name,last_name', 'assignee:id,first_name,last_name'])
            ->where('status', $status)
            ->when(! $runsTheList || $mine, fn ($q) => $q->where('assigned_to', $user->id))
            ->when(
                $status === 'open',
                fn ($q) => $q->orderByDesc('urgent')->orderByRaw('due_on is null')->orderBy('due_on')->latest('id'),
                fn ($q) => $q->latest('completed_at')->latest('id'),
            )
            ->paginate($this->perPage($request, 20))
            ->withQueryString()
            ->through(fn (WorkOrder $o): array => [
                'id' => $o->id,
                'title' => $o->title,
                'urgent' => $o->urgent,
                'overdue' => $o->isOverdue(),
                'due_on' => $o->due_on?->toDateString(),
                'pool' => $o->pool?->name,
                'customer' => $o->pool?->customer?->displayName(),
                'assignee' => $o->assignee?->displayName(),
                'done_on' => Tenant::localDate($o->completed_at),
            ]);

        $selected = null;
        $selectedId = $request->integer('selected');
        if ($selectedId > 0) {
            $order = WorkOrder::query()->find($selectedId);
            // One they may not see reads as not there at all.
            if ($order !== null && Gate::allows('view', $order)) {
                $selected = $this->toDetail($order, $user);
            }
        }

        $canCreate = Gate::allows('create', WorkOrder::class);

        return Inertia::render('work-orders/Index', [
            'orders' => $orders,
            'selected' => $selected,
            'status' => $status,
            'mine' => $mine,
            'runsTheList' => $runsTheList,
            'canCreate' => $canCreate,
            // The form's pickers, only for those who can fill it in.
            'pools' => $canCreate ? OptionLists::pools() : [],
            'people' => $canCreate ? OptionLists::agents() : [],
        ]);
    }

    public function store(WorkOrderRequest $request, CreateWorkOrder $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $order = $action->handle($request->validated(), $user);

        return redirect()->route('work-orders.index', ['selected' => $order->id])->with('success', 'Work order added.');
    }

    public function update(WorkOrderRequest $request, WorkOrder $workOrder, UpdateWorkOrder $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $action->handle($workOrder, $request->validated(), $user);

        return back()->with('success', 'Work order saved.');
    }

    public function done(Request $request, WorkOrder $workOrder, SetWorkOrderDone $action): RedirectResponse
    {
        Gate::authorize('complete', $workOrder);
        /** @var User $user */
        $user = $request->user();
        $action->handle($workOrder, $user, true);

        return back()->with('success', 'Marked done.');
    }

    public function reopen(Request $request, WorkOrder $workOrder, SetWorkOrderDone $action): RedirectResponse
    {
        Gate::authorize('complete', $workOrder);
        /** @var User $user */
        $user = $request->user();
        $action->handle($workOrder, $user, false);

        return back()->with('success', 'Reopened.');
    }

    public function comment(WorkOrderCommentRequest $request, WorkOrder $workOrder, AddWorkOrderComment $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $action->handle($workOrder, (string) $request->validated('body'), $user);

        return back();
    }

    public function destroy(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        Gate::authorize('delete', $workOrder);
        AuditLog::record($request->user(), 'work_order.deleted', $workOrder, ['title' => $workOrder->title, 'status' => $workOrder->status]);
        $workOrder->delete();

        return redirect()->route('work-orders.index')->with('success', 'Work order deleted.');
    }

    /** @return array<string, mixed> */
    private function toDetail(WorkOrder $order, User $user): array
    {
        $order->load([
            'pool:id,name,customer_id', 'pool.customer:id,first_name,last_name', 'assignee:id,first_name,last_name',
            'creator:id,first_name,last_name', 'completer:id,first_name,last_name', 'visit:id,completed_at',
            'comments.author:id,first_name,last_name',
        ]);

        return [
            'id' => $order->id,
            'title' => $order->title,
            'details' => $order->details,
            'status' => $order->status,
            'urgent' => $order->urgent,
            'overdue' => $order->isOverdue(),
            'due_on' => $order->due_on?->toDateString(),
            'pool' => $order->pool?->name,
            'pool_id' => $order->pool?->getKey(),
            'customer' => $order->pool?->customer?->displayName(),
            'customer_id' => $order->pool?->customer?->getKey(),
            'assignee' => $order->assignee?->displayName(),
            'raised_by' => $order->creator?->displayName(),
            'raised_on' => Tenant::localDate($order->created_at),
            // The visit it came from, so the office can open that report.
            'visit_id' => $order->visit?->getKey(),
            'visit_on' => Tenant::localDate($order->visit?->completed_at),
            'done_by' => $order->completer?->displayName(),
            'done_on' => Tenant::localDate($order->completed_at),
            'comments' => $order->comments->map(fn (WorkOrderComment $c): array => [
                'id' => $c->id,
                'author' => $c->author?->displayName() ?? 'Someone',
                'on' => Tenant::localDate($c->created_at),
                'body' => $c->body,
            ])->all(),
            'fields' => [
                'title' => $order->title,
                'details' => $order->details,
                'urgent' => $order->urgent,
                'due_on' => $order->due_on?->toDateString(),
                'assigned_to' => $order->assigned_to,
                'pool_id' => $order->pool_id,
            ],
            'can' => [
                'update' => $user->can('update', $order),
                'complete' => $user->can('complete', $order),
                'comment' => $user->can('comment', $order),
                'delete' => $user->can('delete', $order),
            ],
        ];
    }
}
