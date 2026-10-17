<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A customer picking their dashboard: the widget grid or the simple Home page. */
class UpdateDashboardStyleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'customer';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['style' => ['required', 'in:widgets,simple']];
    }
}
