<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Lead;
use App\Models\User;
use App\Notifications\LeadSubmitted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Save a message sent from a company's website (contact/quote/booking form or
 * the chat assistant) as a lead, and tell the company's admins.
 *
 * One sender can have at most MAX_UNANSWERED leads waiting on a reply (still
 * "new") — after that they wait until the company marks one contacted,
 * converts or archives it. A sender is matched by email, phone or IP, so new
 * addresses from the same machine don't get round it. Null when refused.
 */
class CaptureLead
{
    public const MAX_UNANSWERED = 3;

    /** What a refused sender is told, by the website form or the chat assistant. */
    public const WAIT_FOR_REPLY = 'You’ve already sent us '.self::MAX_UNANSWERED.' messages. We’ll get back to you soon, so please wait for our reply before sending another.';

    /** @param  array<string, mixed>  $data  validated lead fields */
    public function handle(int $tenantId, array $data, ?string $ip): ?Lead
    {
        // Serialized per company so a burst of submissions can't all pass the count at once.
        $lead = Cache::lock('lead-capture:'.$tenantId, 10)->block(5, function () use ($tenantId, $data, $ip): ?Lead {
            if ($this->unanswered($tenantId, $data, $ip) >= self::MAX_UNANSWERED) {
                return null;
            }

            $lead = new Lead($data);
            $lead->forceFill(['tenant_id' => $tenantId, 'ip_address' => $ip])->save();

            return $lead;
        });

        if ($lead !== null) {
            Notification::send(User::adminsOf($tenantId), new LeadSubmitted($lead));
        }

        return $lead;
    }

    /** @param  array<string, mixed>  $data */
    private function unanswered(int $tenantId, array $data, ?string $ip): int
    {
        $email = is_string($data['email'] ?? null) && trim($data['email']) !== '' ? mb_strtolower(trim($data['email'])) : null;
        $phone = self::digits($data['phone'] ?? null);

        return Lead::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('status', 'new')
            ->get(['email', 'phone', 'ip_address'])
            ->filter(fn (Lead $l): bool => ($ip !== null && $l->ip_address === $ip)
                || ($email !== null && mb_strtolower(trim((string) $l->email)) === $email)
                || ($phone !== null && self::digits($l->getAttribute('phone')) === $phone))
            ->count();
    }

    /** A phone number's digits, so "(912) 555-0104" matches "912.555.0104"; too short to tell people apart = null. */
    private static function digits(mixed $phone): ?string
    {
        $digits = is_string($phone) ? preg_replace('/\D/', '', $phone) : '';

        return is_string($digits) && strlen($digits) >= 7 ? $digits : null;
    }
}
