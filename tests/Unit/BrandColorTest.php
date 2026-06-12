<?php

declare(strict_types=1);

use App\Support\BrandColor;

test('it converts brand hex to HSL channels matching useBrand.ts', function () {
    expect(BrandColor::toHslChannels('#0ea5e9'))->toBe('199 89% 48%'); // sky brand default
    expect(BrandColor::toHslChannels('0ea5e9'))->toBe('199 89% 48%');  // leading # optional
    expect(BrandColor::toHslChannels('#f97316'))->toBe('25 95% 53%');  // orange accent
    expect(BrandColor::toHslChannels('#FFFFFF'))->toBe('0 0% 100%');   // case-insensitive
    expect(BrandColor::toHslChannels('#000000'))->toBe('0 0% 0%');
});

test('it returns null for an invalid hex', function () {
    expect(BrandColor::toHslChannels('not-a-color'))->toBeNull();
    expect(BrandColor::toHslChannels('#fff'))->toBeNull(); // 3-digit shorthand unsupported (matches JS)
    expect(BrandColor::toHslChannels(''))->toBeNull();
});
