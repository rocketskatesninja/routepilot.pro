<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Support\NotificationTypes;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Turning in-app notification types on or off, each true or false. Only the
 * types the signed-in user actually gets are accepted.
 */
class UpdateNotificationTypesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return array_fill_keys(array_keys(NotificationTypes::for($user)), ['sometimes', 'boolean']);
    }

    /** @return array<string, bool> */
    public function changes(): array
    {
        return array_map(fn ($v): bool => (bool) $v, $this->validated());
    }
}
