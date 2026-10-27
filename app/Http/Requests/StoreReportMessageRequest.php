<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A customer's message to the company, sent from a visit report email. Who may
 * send it is settled by the route: the signed link in that email.
 */
class StoreReportMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['message' => ['required', 'string', 'max:2000']];
    }
}
