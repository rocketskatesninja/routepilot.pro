<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Gates the dashboard-layout save. Any authenticated user may save their OWN
 * layout; the per-widget cleaning happens in DashboardWidgets::sanitize.
 */
class UpdateDashboardLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'layout' => ['present', 'array', 'max:40'],
            'layout.*.i' => ['required', 'string', 'max:40'],
            'layout.*.x' => ['required', 'integer'],
            'layout.*.y' => ['required', 'integer'],
            'layout.*.w' => ['required', 'integer'],
            'layout.*.h' => ['required', 'integer'],
        ];
    }
}
