<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;

/**
 * Never send to an address that can't exist. Demo and sample data uses reserved
 * domains (robert@example.test), and the demo company is an active company like
 * any other, so its reminders, invoices and reports would otherwise go out and
 * bounce. Every email passes through here, whichever mail server it's leaving by.
 *
 * Returning false cancels the send; null lets it carry on.
 */
class BlockReservedAddresses
{
    /** Reserved for testing and documentation by RFC 2606 and RFC 6761: never deliverable. */
    private const DOMAINS = ['example.com', 'example.net', 'example.org'];

    private const TOP_LEVEL = ['test', 'example', 'invalid', 'localhost'];

    public function handle(MessageSending $event): ?bool
    {
        $addresses = array_map(
            fn (Address $address): string => $address->getAddress(),
            [...$event->message->getTo(), ...$event->message->getCc(), ...$event->message->getBcc()],
        );

        // Anything with a real recipient goes out as normal.
        if ($addresses === [] || array_filter($addresses, fn (string $email): bool => ! self::reserved($email)) !== []) {
            return null;
        }

        Log::info('Email not sent: reserved address', ['to' => $addresses, 'subject' => $event->message->getSubject()]);

        return false;
    }

    public static function reserved(string $email): bool
    {
        $domain = strtolower(substr((string) strrchr($email, '@'), 1));

        return in_array($domain, self::DOMAINS, true) || in_array(substr((string) strrchr('.'.$domain, '.'), 1), self::TOP_LEVEL, true);
    }
}
