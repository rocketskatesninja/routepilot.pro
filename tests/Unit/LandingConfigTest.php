<?php

declare(strict_types=1);

use App\Support\LandingConfig;

test('defaults list every section key exactly once, in order', function () {
    $keys = collect(LandingConfig::defaults()['sections'])->pluck('key');

    expect($keys->all())->toEqual(LandingConfig::SECTION_KEYS);
    expect($keys->duplicates())->toBeEmpty();
});

test('fromStored(null) returns the defaults', function () {
    expect(LandingConfig::fromStored(null))->toEqual(LandingConfig::defaults());
});

test('fromStored fills missing fields + sections from defaults', function () {
    $config = LandingConfig::fromStored(json_encode([
        'sections' => [['key' => 'hero', 'enabled' => true, 'headline' => 'Custom headline']],
    ]));

    $hero = collect($config['sections'])->firstWhere('key', 'hero');
    expect($hero['headline'])->toBe('Custom headline');
    expect($hero)->toHaveKey('subhead'); // filled from defaults
    expect(collect($config['sections'])->pluck('key')->sort()->values()->all())
        ->toEqual(collect(LandingConfig::SECTION_KEYS)->sort()->values()->all());
});

test('fromStored strips unknown sections + unknown fields from a tampered doc', function () {
    $config = LandingConfig::fromStored(json_encode([
        'sections' => [
            ['key' => 'hero', 'enabled' => true, 'headline' => 'Hi', 'evil' => '<script>'],
            ['key' => '__evil', 'enabled' => true, 'heading' => 'x'],
        ],
    ]));

    $keys = collect($config['sections'])->pluck('key');
    expect($keys)->not->toContain('__evil');
    expect(collect($config['sections'])->firstWhere('key', 'hero'))->not->toHaveKey('evil');
});

test('sanitize drops unknown keys, dedupes, caps arrays, and keeps the full set', function () {
    $clean = LandingConfig::sanitize([
        'sections' => [
            ['key' => 'hero', 'enabled' => true, 'headline' => 'Hi', 'evil' => 'x'],
            ['key' => 'hero', 'enabled' => false, 'headline' => 'Dupe'], // duplicate → dropped
            ['key' => '__evil', 'enabled' => true],                      // unknown → dropped
            ['key' => 'faq', 'enabled' => true, 'items' => array_fill(0, 50, ['q' => 'Q', 'a' => 'A'])],
        ],
    ]);

    $keys = collect($clean['sections'])->pluck('key');
    expect($keys)->not->toContain('__evil');
    expect($keys->duplicates())->toBeEmpty();
    expect($keys->sort()->values()->all())->toEqual(collect(LandingConfig::SECTION_KEYS)->sort()->values()->all());

    $hero = collect($clean['sections'])->firstWhere('key', 'hero');
    expect($hero)->not->toHaveKey('evil');
    expect($hero['headline'])->toBe('Hi'); // first occurrence wins

    $faq = collect($clean['sections'])->firstWhere('key', 'faq');
    expect($faq['items'])->toHaveCount(30); // capped
});

test('sanitize rejects an out-of-tree image path', function () {
    $clean = LandingConfig::sanitize([
        'sections' => [['key' => 'hero', 'enabled' => true, 'image_path' => '../../etc/passwd']],
    ]);

    expect(collect($clean['sections'])->firstWhere('key', 'hero')['image_path'])->toBeNull();
});

test('enabledOrdered returns only enabled sections', function () {
    $config = LandingConfig::defaults();
    $config['sections'][0]['enabled'] = false; // disable hero

    $ordered = LandingConfig::enabledOrdered($config);
    expect(collect($ordered)->pluck('key'))->not->toContain('hero');
    expect(collect($ordered)->every(fn ($s) => $s['enabled'] === true))->toBeTrue();
});

test('fromStored includes the header title block with defaults', function () {
    $config = LandingConfig::fromStored(null);

    expect($config['title']['font'])->toBe('Inter');
    expect($config['title']['color'])->toBeNull(); // inherit foreground until customized
    expect($config['title']['color_type'])->toBe('solid');
});

test('sanitize keeps valid title styling and clamps out-of-range values', function () {
    $clean = LandingConfig::sanitize([
        'sections' => [],
        'title' => [
            'text' => 'Acme Pools',
            'font' => 'Poppins',
            'size' => 'xl',
            'weight' => '800',
            'tracking' => 'wide',
            'color_type' => 'gradient',
            'color' => 'not-a-hex',
            'gradient_start' => '#123456',
            'gradient_angle' => 999,
            'outline' => true,
            'outline_width' => 9,
            'shadow' => 'glow',
        ],
    ]);

    $t = $clean['title'];
    expect($t['text'])->toBe('Acme Pools');
    expect($t['font'])->toBe('Poppins');
    expect($t['size'])->toBe('xl');
    expect($t['weight'])->toBe('800');
    expect($t['tracking'])->toBe('wide');
    expect($t['color_type'])->toBe('gradient');
    expect($t['color'])->toBeNull();          // invalid hex → null (inherit)
    expect($t['gradient_start'])->toBe('#123456');
    expect($t['gradient_angle'])->toBe(360);  // clamped to 0..360
    expect($t['outline'])->toBeTrue();
    expect($t['outline_width'])->toBe(3);     // clamped to 0..3
    expect($t['shadow'])->toBe('glow');
});

test('sanitize rejects unknown title font / enum values, using defaults', function () {
    $clean = LandingConfig::sanitize([
        'sections' => [],
        'title' => ['font' => 'ComicSans', 'size' => 'huge', 'weight' => '123', 'tracking' => 'x', 'color_type' => 'rainbow', 'shadow' => 'sparkle'],
    ]);

    $t = $clean['title'];
    expect($t['font'])->toBe('Inter');
    expect($t['size'])->toBe('md');
    expect($t['weight'])->toBe('700');
    expect($t['tracking'])->toBe('normal');
    expect($t['color_type'])->toBe('solid');
    expect($t['shadow'])->toBe('none');
});

test('title text is length-capped at 60', function () {
    $clean = LandingConfig::sanitize(['sections' => [], 'title' => ['text' => str_repeat('a', 100)]]);

    expect(mb_strlen($clean['title']['text']))->toBe(60);
});
