<?php

declare(strict_types=1);

test('the PWA manifest, theme color and icons are wired into the document head', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('manifest.webmanifest', false)
        ->assertSee('name="theme-color"', false)
        ->assertSee('apple-touch-icon', false);
});

test('the PWA static assets are present in the public root', function () {
    $files = [
        'manifest.webmanifest',
        'sw.js',
        'offline.html',
        'assets/images/pwa/icon-192.png',
        'assets/images/pwa/icon-512.png',
    ];

    foreach ($files as $file) {
        expect(file_exists(public_path($file)))->toBeTrue("missing public/{$file}");
    }
});

test('the web manifest is valid JSON with the required PWA fields', function () {
    $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true);

    expect($manifest)->toBeArray()
        ->and($manifest['name'] ?? null)->not->toBeEmpty()
        ->and($manifest['start_url'] ?? null)->not->toBeEmpty()
        ->and($manifest['display'] ?? null)->toBe('standalone')
        ->and($manifest['icons'] ?? [])->toHaveCount(2);
});
