<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ServiceVisit;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edit a completed service report. Tenant admins may edit any report; an agent
 * may edit only their own (the visit's agent_id). The {visit} binding is
 * tenant-scoped, so a foreign report never resolves here.
 */
class UpdateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $visit = $this->route('visit');
        if ($user === null || ! $visit instanceof ServiceVisit) {
            return false;
        }

        return $user->role === 'tenant_admin' || (int) $visit->getAttribute('agent_id') === $user->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'completed_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'reading' => ['array'],
            'reading.free_chlorine' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'reading.total_chlorine' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'reading.ph' => ['nullable', 'numeric', 'min:0', 'max:14'],
            'reading.alkalinity' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'reading.calcium_hardness' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'reading.cyanuric_acid' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'reading.salt' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'reading.water_temperature' => ['nullable', 'numeric', 'min:0', 'max:150'],
            'treatments' => ['array'],
            'treatments.*.name' => ['nullable', 'string', 'max:120'],
            'treatments.*.amount' => ['nullable', 'numeric', 'min:0'],
            'treatments.*.unit' => ['nullable', 'string', 'max:30'],
            'tasks' => ['array'],
            'tasks.*.name' => ['nullable', 'string', 'max:160'],
            'tasks.*.done' => ['boolean'],
        ];
    }
}
