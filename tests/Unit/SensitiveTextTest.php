<?php

declare(strict_types=1);

use App\Support\SensitiveText;

test('card numbers are blanked out, however they are spaced', function (string $typed) {
    expect(SensitiveText::redact("my card is {$typed} thanks"))->toBe('my card is [card number removed] thanks');
})->with(['4242424242424242', '4242 4242 4242 4242', '4000-0566-5566-5556', '3782 822463 10005']);

test('other numbers are left alone', function (string $typed) {
    expect(SensitiveText::redact($typed))->toBe($typed);
})->with(['call me on 555-867-5309', 'gate code 4821', 'invoice INV-2026-0042', '1234 5678 9012 3456', 'pool is 20000 gallons']);

test('a typed password is blanked out', function () {
    expect(SensitiveText::redact('my password is hunter2!'))->toBe('my password is [password removed]')
        ->and(SensitiveText::redact('Password: hunter2'))->toBe('Password: [password removed]');
});
