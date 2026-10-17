<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;

/**
 * A company's look for email: its colour (and the soft tints the customer
 * portal uses), its logo and its name. Email clients can't do CSS colour
 * mixing, so the tints are worked out here as plain hex.
 */
final class EmailBrand
{
    private const DEFAULT_COLOR = '#0ea5e9';

    /** @return array{name: string, color: string, on_color: string, tint: string, tile: string, band: string, waterline: array{0: string, 1: string}, logo: string|null, address: string|null} */
    public static function for(?Tenant $tenant): array
    {
        $raw = (string) ($tenant?->getAttribute('brand_color') ?? '');
        $color = preg_match('/^#[0-9a-f]{6}$/i', $raw) === 1 ? strtolower($raw) : self::DEFAULT_COLOR;
        $logo = $tenant?->getAttribute('logo_path');

        return [
            'name' => $tenant !== null ? (string) $tenant->name : 'RoutePilot',
            'color' => $color,
            'on_color' => self::readableOn($color),
            'tint' => self::mix($color, 0.08),
            'tile' => self::mix($color, 0.12),
            'band' => self::mix($color, 0.3),
            'waterline' => [self::mix($color, 0.55), self::mix($color, 0.28)],
            'logo' => is_string($logo) && $logo !== '' ? Storage::disk('public')->url($logo) : null,
            'address' => $tenant?->formattedAddress(),
        ];
    }

    /** The brand colour laid over white at the given strength, as hex. */
    private static function mix(string $hex, float $amount): string
    {
        $channels = array_map(fn (string $c): int => (int) hexdec($c), str_split(substr($hex, 1), 2));

        return '#'.implode('', array_map(
            fn (int $c): string => str_pad(dechex((int) round(255 - (255 - $c) * $amount)), 2, '0', STR_PAD_LEFT),
            $channels,
        ));
    }

    /** White text on dark brand colours, near-black on light ones (buttons stay readable). */
    private static function readableOn(string $hex): string
    {
        [$r, $g, $b] = array_map(fn (string $c): float => hexdec($c) / 255, str_split(substr($hex, 1), 2));
        $lin = fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $luminance = 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);

        return $luminance > 0.45 ? '#111827' : '#ffffff';
    }
}
