<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Hex brand color → the "H S% L%" channel triple the Tailwind token system
 * uses (e.g. "#0ea5e9" → "199 89% 48%"). A faithful PHP port of useBrand.ts's
 * hexToHslChannels so the server (SSR / first paint) and the client agree
 * exactly — a parity test pins them together.
 */
class BrandColor
{
    /** @return string|null "H S% L%" channels, or null when the hex is invalid. */
    public static function toHslChannels(string $hex): ?string
    {
        if (! preg_match('/^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i', trim($hex), $m)) {
            return null;
        }

        $r = (int) hexdec($m[1]) / 255;
        $g = (int) hexdec($m[2]) / 255;
        $b = (int) hexdec($m[3]) / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $h = 0.0;
        $s = 0.0;

        if ($max !== $min) {
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
            if ($max === $r) {
                $h = ($g - $b) / $d + ($g < $b ? 6 : 0);
            } elseif ($max === $g) {
                $h = ($b - $r) / $d + 2;
            } else {
                $h = ($r - $g) / $d + 4;
            }
            $h /= 6;
        }

        return sprintf('%d %d%% %d%%', (int) round($h * 360), (int) round($s * 100), (int) round($l * 100));
    }
}
