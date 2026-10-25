<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A phone's push subscription, as the browser hands it over. The endpoint is
 * where the server will later send requests, so it must be one of the real
 * push services — never an address of the sender's choosing, which would let
 * anyone aim the server at internal hosts.
 */
class StorePushSubscriptionRequest extends FormRequest
{
    /** The push services browsers use: Chrome/Android, Firefox, Safari/iOS, Edge. */
    private const HOSTS = ['fcm.googleapis.com', 'push.services.mozilla.com', 'push.apple.com', 'notify.windows.com'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'max:500', 'url:https', function (string $attribute, mixed $value, Closure $fail): void {
                if (! self::isPushService((string) $value)) {
                    $fail('That isn’t a push service.');
                }
            }],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ];
    }

    private static function isPushService(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach (self::HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }
}
