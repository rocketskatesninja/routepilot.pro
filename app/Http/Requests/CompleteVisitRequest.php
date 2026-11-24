<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\VisitReportRules;
use App\Models\RouteStop;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class CompleteVisitRequest extends FormRequest
{
    use VisitReportRules;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->readingRules(),
            ...$this->lineRules(),
            ...$this->noteRules(),
            // A second address that gets a copy of the report, remembered on the pool. Sent
            // blank to clear it; left out (older copies of the app) to leave it alone.
            'report_cc_email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            // The office typing a report in for a tech can hold back the customer's email.
            'email_customer' => ['boolean'],
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
            'notes' => 'note to the homeowner', 'office_notes' => 'office note', 'report_cc_email' => 'copy-to email', 'photos' => 'photos', 'photos.*' => 'photo',
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
