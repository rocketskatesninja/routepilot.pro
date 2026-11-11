<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Blanks out card numbers and passwords someone types into the assistant,
 * before the message is saved or sent to the AI provider. A card number is
 * 13–19 digits (spaces or dashes allowed between them) that pass the Luhn
 * check, so phone numbers and most other numbers are left alone.
 */
final class SensitiveText
{
    public const CARD = '[card number removed]';

    public const PASSWORD = '[password removed]';

    public static function redact(string $text): string
    {
        $text = preg_replace_callback(
            '/(?<!\d)(?:\d[ -]?){12,18}\d(?!\d)/',
            fn (array $m): string => self::luhn((string) preg_replace('/\D/', '', $m[0])) ? self::CARD : $m[0],
            $text,
        ) ?? $text;

        return preg_replace('/\b(password)(\s*(?:is|:|=)\s*)\S+/i', '$1$2'.self::PASSWORD, $text) ?? $text;
    }

    private static function luhn(string $digits): bool
    {
        $sum = 0;
        $double = false;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $d = (int) $digits[$i];
            if ($double) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
            $double = ! $double;
        }

        return $sum % 10 === 0;
    }
}
