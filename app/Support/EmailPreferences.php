<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Customer;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\URL;

/**
 * The emails a customer can turn off, and the signed links that let them do
 * it straight from an email — no login needed. Receipts, bills, failed
 * payments, email-change confirmations and answers to their own requests
 * aren't optional and always send.
 */
final class EmailPreferences
{
    /** type => [name, what it is] */
    public const TYPES = [
        'visit_reports' => ['Visit reports', 'After each visit: what was done, your water test and photos.'],
        'reminders' => ['Visit reminders', 'The day before someone comes, so you can unlock the gate.'],
        'news' => ['News and offers', 'Seasonal tips, openings and closings, and other news from us.'],
    ];

    public static function wants(Customer $customer, string $type): bool
    {
        return (bool) $customer->getAttribute(self::column($type));
    }

    /** @return array<string, bool> type => on */
    public static function for(Customer $customer): array
    {
        return array_map(fn (string $type): bool => self::wants($customer, $type), array_combine(array_keys(self::TYPES), array_keys(self::TYPES)));
    }

    /** @param  array<string, bool>  $changes  type => on */
    public static function set(Customer $customer, array $changes): void
    {
        $values = [];
        foreach ($changes as $type => $on) {
            if (isset(self::TYPES[$type])) {
                $values[self::column($type)] = (bool) $on;
            }
        }
        if ($values !== []) {
            $customer->forceFill($values)->save();
        }
    }

    public static function column(string $type): string
    {
        return 'email_'.$type;
    }

    /** The customer's email settings page, optionally opening on "stop these" for one type. */
    public static function pageUrl(int $customerId, ?string $stop = null): string
    {
        return URL::signedRoute('email-preferences.show', array_filter(['customer' => $customerId, 'stop' => $stop]));
    }

    /** The address Gmail and Yahoo post to when someone taps their built-in "Unsubscribe". */
    public static function oneClickUrl(int $customerId, string $type): string
    {
        return URL::signedRoute('email-preferences.one-click', ['customer' => $customerId, 'type' => $type]);
    }

    /** The one-click unsubscribe headers (RFC 8058) for an optional email. */
    public static function headers(int $customerId, string $type): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.self::oneClickUrl($customerId, $type).'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }
}
