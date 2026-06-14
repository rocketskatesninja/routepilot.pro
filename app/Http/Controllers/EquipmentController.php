<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateEquipment;
use App\Actions\LogEquipmentService;
use App\Actions\UpdateEquipment;
use App\Http\Requests\LogEquipmentServiceRequest;
use App\Http\Requests\StoreEquipmentRequest;
use App\Http\Requests\UpdateEquipmentRequest;
use App\Models\PoolEquipment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pool equipment + service log. {equipment} is tenant-scoped (foreign → 404);
 * requests gate to tenant_admin.
 */
class EquipmentController extends Controller
{
    public function store(StoreEquipmentRequest $request, CreateEquipment $action): RedirectResponse
    {
        $action->handle($request->validated());

        return back()->with('success', 'Equipment added.');
    }

    public function update(UpdateEquipmentRequest $request, PoolEquipment $equipment, UpdateEquipment $action): RedirectResponse
    {
        $action->handle($equipment, $request->validated());

        return back()->with('success', 'Equipment updated.');
    }

    public function destroy(Request $request, PoolEquipment $equipment): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $equipment->delete();

        return back()->with('success', 'Equipment removed.');
    }

    public function logService(LogEquipmentServiceRequest $request, PoolEquipment $equipment, LogEquipmentService $action): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $action->handle($equipment, $request->validated(), (int) $user->id);

        return back()->with('success', 'Service logged.');
    }
}
