<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\RouteStop;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class CompleteVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'free_chlorine' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'total_chlorine' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'ph' => ['nullable', 'numeric', 'min:0', 'max:14'],
            'alkalinity' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'calcium_hardness' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'cyanuric_acid' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'salt' => ['nullable', 'numeric', 'min:0', 'max:20000'],
            'tds' => ['nullable', 'numeric', 'min:0', 'max:50000'],
            'phosphates' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'water_temperature' => ['nullable', 'numeric', 'min:0', 'max:120'],
            'tasks' => ['array'],
            'tasks.*.name' => ['nullable', 'string', 'max:255'],
            'tasks.*.done' => ['boolean'],
            'treatments' => ['array'],
            // A chemical picked from the company's list (another company's id is ignored);
            // name/unit are only sent for chemicals typed before the list existed.
            'treatments.*.chemical_id' => ['nullable', 'integer'],
            'treatments.*.name' => ['nullable', 'string', 'max:255'],
            'treatments.*.amount' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'treatments.*.unit' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // For the company only; the homeowner never sees it.
            'office_notes' => ['nullable', 'string', 'max:2000'],
            // Something that needs doing later; becomes a work order on the company's list.
            'work_order_title' => ['nullable', 'string', 'max:160'],
            'work_order_details' => ['nullable', 'string', 'max:5000'],
            'work_order_urgent' => ['boolean'],
            'work_order_price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            // The single box older copies of the app still send (split by CreateWorkOrder::fromText).
            'work_order' => ['nullable', 'string', 'max:2000'],
            'photos' => ['nullable', 'array', 'max:6'],
            'photos.*' => ['image', 'max:5120'],
            // Best-effort GPS proof-of-presence from the field app; idempotency key for offline replays.
            'completed_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'completed_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }

    /** Names a tech reads in the field app when a visit is refused ("The photo must be an image."). */
    public function attributes(): array
    {
        return [
            'free_chlorine' => 'free chlorine', 'total_chlorine' => 'total chlorine', 'ph' => 'pH',
            'calcium_hardness' => 'calcium', 'cyanuric_acid' => 'CYA', 'water_temperature' => 'temperature',
            'treatments.*.amount' => 'chemical amount', 'work_order_title' => 'work order title',
            'work_order_details' => 'work order details', 'work_order_price' => 'work order price',
            'notes' => 'note to the homeowner', 'office_notes' => 'office note', 'photos' => 'photos', 'photos.*' => 'photo',
        ];
    }

    /**
     * A refused visit stays stuck in the tech's offline queue, so record why: the
     * stop and the messages (field names and limits, never the values sent).
     */
    protected function failedValidation(Validator $validator): void
    {
        $stop = $this->route('stop');
        Log::warning('Field visit refused', [
            'user_id' => $this->user()?->id,
            'stop_id' => $stop instanceof RouteStop ? $stop->id : $stop,
            'errors' => $validator->errors()->toArray(),
        ]);

        parent::failedValidation($validator);
    }
}
