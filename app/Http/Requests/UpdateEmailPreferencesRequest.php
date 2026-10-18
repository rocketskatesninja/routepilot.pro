<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\EmailPreferences;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Turning optional emails on or off: any of visit_reports / reminders / news,
 * each true or false. Who may do it is settled by the route — a signed email
 * link, the customer in their portal, or the company's office.
 */
class UpdateEmailPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_fill_keys(array_keys(EmailPreferences::TYPES), ['sometimes', 'boolean']);
    }

    /** @return array<string, bool> */
    public function changes(): array
    {
        return array_map(fn ($v): bool => (bool) $v, $this->validated());
    }
}
