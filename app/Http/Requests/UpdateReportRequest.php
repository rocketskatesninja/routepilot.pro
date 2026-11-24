<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\VisitReportRules;
use App\Models\ServiceVisit;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edit a completed service report. Tenant admins may edit any report; an agent
 * may edit only their own (the visit's agent_id). The {visit} binding is
 * tenant-scoped, so a foreign report never resolves here.
 */
class UpdateReportRequest extends FormRequest
{
    use VisitReportRules;

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
            ...$this->noteRules(),
            'reading' => ['array'],
            ...$this->readingRules('reading.'),
            ...$this->lineRules(),
        ];
    }
}
