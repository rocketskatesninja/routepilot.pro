<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\MailHost;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMailConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'tenant_admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Only a public mail server on a mail port: never something inside RoutePilot's network.
            'host' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail): void {
                if (! MailHost::allowed((string) $value, (int) $this->input('port'))) {
                    $fail('That mail server address can’t be used. Use your provider’s public SMTP address.');
                }
            }],
            'port' => ['required', 'integer', 'in:'.implode(',', MailHost::PORTS)],
            'encryption' => ['nullable', 'in:tls,ssl'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            // Their server sends as this address (it rarely allows anyone else's).
            'from_address' => ['required', 'string', 'email:rfc', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
