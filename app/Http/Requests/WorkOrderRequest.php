<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Add or edit a work order (admins and Agent+, per WorkOrderPolicy). The pool
 * and the assignee must belong to the same company.
 */
class WorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('workOrder');

        return $order instanceof WorkOrder ? Gate::allows('update', $order) : Gate::allows('create', WorkOrder::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'title' => ['required', 'string', 'max:160'],
            'details' => ['nullable', 'string', 'max:5000'],
            'urgent' => ['boolean'],
            'due_on' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)->whereIn('role', ['agent', 'tenant_admin'])->where('is_active', true)->whereNull('deleted_at')],
            'pool_id' => ['nullable', 'integer', Rule::exists('pools', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['title.required' => 'Say what needs doing.'];
    }
}
