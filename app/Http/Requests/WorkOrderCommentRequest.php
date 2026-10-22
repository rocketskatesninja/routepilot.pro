<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** A note on a work order's thread, from anyone who can see the work order. */
class WorkOrderCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('workOrder');

        return $order instanceof WorkOrder && Gate::allows('comment', $order);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:2000']];
    }
}
