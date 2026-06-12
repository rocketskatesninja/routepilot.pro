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
