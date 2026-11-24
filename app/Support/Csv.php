<?php

declare(strict_types=1);

namespace App\Support;

/** Shared by every CSV export, so no exporter forgets the formula guard. */
final class Csv
{
    /**
     * A text cell made safe for spreadsheets: Excel and Sheets run a cell that
     * starts with = + - @ (or a tab / carriage return) as a formula, and names
     * can come from the public lead form.
     */
    public static function cell(?string $value): string
    {
        $value ??= '';

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
