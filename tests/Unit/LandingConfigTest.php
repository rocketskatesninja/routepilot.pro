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

test('service_area sanitizes the ZIP override — capped, else null', function () {
    $clean = LandingConfig::sanitize([
        'sections' => [['key' => 'service_area', 'enabled' => true, 'zip' => str_repeat('9', 30)]],
    ]);
    expect(mb_strlen(collect($clean['sections'])->firstWhere('key', 'service_area')['zip']))->toBe(12);

    $none = LandingConfig::sanitize(['sections' => [['key' => 'service_area', 'enabled' => true]]]);
    expect(collect($none['sections'])->firstWhere('key', 'service_area')['zip'])->toBeNull();
});

test('title text is length-capped at 60', function () {
    $clean = LandingConfig::sanitize(['sections' => [], 'title' => ['text' => str_repeat('a', 100)]]);

    expect(mb_strlen($clean['title']['text']))->toBe(60);
});

test('fromStored includes the social block, defaulting every platform to null', function () {
    expect(LandingConfig::fromStored(null)['social'])->toBe(array_fill_keys(LandingConfig::SOCIAL_PLATFORMS, null));
});

test('sanitize keeps valid social URLs and drops invalid / non-whitelisted ones', function () {
    $clean = LandingConfig::sanitize([
        'sections' => [],
        'social' => [
            'facebook' => 'https://facebook.com/acmepools',
            'linkedin' => 'http://linkedin.com/company/acme',
            'instagram' => 'not a url',
            'twitter' => 'javascript:alert(1)',
            'youtube' => '',
            'evil' => 'https://evil.test',
        ],
    ]);

    expect($clean['social']['facebook'])->toBe('https://facebook.com/acmepools')
        ->and($clean['social']['linkedin'])->toBe('http://linkedin.com/company/acme')
        ->and($clean['social']['instagram'])->toBeNull()   // not a URL
        ->and($clean['social']['twitter'])->toBeNull()     // non-http(s) scheme rejected
        ->and($clean['social']['youtube'])->toBeNull()     // empty
        ->and($clean['social'])->not->toHaveKey('evil')    // non-whitelisted platform dropped
        ->and(array_keys($clean['social']))->toBe(LandingConfig::SOCIAL_PLATFORMS);
});

/** A section from a sanitized or stored config, by key. */
function landingSection(array $config, string $key): array
{
    return collect($config['sections'])->firstWhere('key', $key);
}

test('turning every stat off stays off: an empty list is not refilled from the defaults', function () {
    $stored = LandingConfig::sanitize(['sections' => [['key' => 'stats', 'enabled' => true, 'metrics' => []]]]);

    expect(landingSection(LandingConfig::fromStored(json_encode($stored)), 'stats')['metrics'])->toBe([]);
});

test('stats keep known metrics once each, in order, and whole-number overrides for known stats', function () {
    $stats = landingSection(LandingConfig::sanitize(['sections' => [[
        'key' => 'stats', 'enabled' => true,
        'metrics' => ['years_active', 'bogus', 'pools_serviced', 'years_active'],
        'overrides' => ['years_active' => 15, 'pools_serviced' => '120', 'water_tests' => 'lots', 'bogus' => 5, 'technicians' => 5_000_000_000],
    ]]]), 'stats');

    expect($stats['metrics'])->toBe(['years_active', 'pools_serviced'])
        ->and((array) $stats['overrides'])->toBe(['pools_serviced' => 120, 'years_active' => 15, 'technicians' => 999_999_999]);
});

test('older configs get no overrides and the stats they had', function () {
    $stats = landingSection(LandingConfig::fromStored(json_encode(['sections' => [['key' => 'stats', 'enabled' => true, 'metrics' => ['pools_serviced']]]])), 'stats');

    expect($stats['metrics'])->toBe(['pools_serviced'])->and($stats['overrides'])->toEqual(new stdClass); // {} on the page, not []
});

test('hero badges: sites start with the four old ones; picks are whitelisted, deduped and capped at six', function () {
    expect(landingSection(LandingConfig::fromStored(null), 'hero')['badges'])->toBe(['licensed', 'cpo', 'five_star', 'same_day']);
    // a config saved before badges existed shows the same four
    expect(landingSection(LandingConfig::fromStored(json_encode(['sections' => [['key' => 'hero', 'enabled' => true]]])), 'hero')['badges'])
        ->toBe(['licensed', 'cpo', 'five_star', 'same_day']);

    $hero = fn (array $badges) => landingSection(LandingConfig::sanitize(['sections' => [['key' => 'hero', 'enabled' => true, 'badges' => $badges]]]), 'hero');
    expect($hero([])['badges'])->toBe([])
        ->and($hero(['autopay', 'nope', 'autopay', 'spanish'])['badges'])->toBe(['autopay', 'spanish'])
        ->and($hero(array_slice(LandingConfig::HERO_BADGES, 0, 9))['badges'])->toHaveCount(LandingConfig::MAX_BADGES);
});

test('the lists in components/landing match LandingConfig (keys, presets, effects, social, default headings)', function () {
    // The quoted values of `field: '...'` inside `export const NAME ... ];` in a components/landing file.
    $list = function (string $file, string $const, string $field = 'key'): array {
        $src = (string) file_get_contents(__DIR__."/../../resources/js/components/landing/{$file}");
        preg_match('/export const '.$const.'\b.*?\n\];?/s', $src, $block);
        preg_match_all($field === '' ? "/'([a-z_]+)'/" : "/{$field}: '([^']+)'/", $block[0] ?? '', $m);

        return $m[1];
    };
    $sorted = fn (array $a): array => collect($a)->sort()->values()->all();

    expect($list('badges.ts', 'HERO_BADGES'))->toBe(LandingConfig::HERO_BADGES)
        ->and($list('icons.ts', 'LANDING_ICONS'))->toBe(LandingConfig::ICONS)
        ->and($sorted($list('stats.ts', 'STAT_METRICS')))->toBe($sorted(LandingConfig::METRICS))
        ->and($list('meta.ts', 'HERO_PRESETS', ''))->toBe(LandingConfig::HERO_PRESETS)
        ->and($list('meta.ts', 'HERO_EFFECTS', 'value'))->toBe(LandingConfig::HERO_EFFECTS)
        ->and($list('meta.ts', 'SOCIAL_LINKS', 'value'))->toBe(LandingConfig::SOCIAL_PLATFORMS);

    // A blank heading falls back to the same text a new site starts with.
    $defaults = collect(LandingConfig::defaults()['sections'])
        ->mapWithKeys(fn (array $s): array => [$s['key'] => $s['heading'] ?? $s['headline'] ?? null])
        ->except(['hero'])->filter()->all();
    expect(collect($list('meta.ts', 'SECTION_META', 'heading'))->values()->all())->toBe(array_values($defaults));
});

test('hero and stats line up left by default; only left, center or right is kept', function () {
    $defaults = LandingConfig::fromStored(null);
    expect(landingSection($defaults, 'hero')['align'])->toBe('left')
        ->and(landingSection($defaults, 'stats')['align'])->toBe('left');

    $clean = LandingConfig::sanitize(['sections' => [
        ['key' => 'hero', 'enabled' => true, 'align' => 'center', 'headline' => "Crystal-clear water,\nevery week"],
        ['key' => 'stats', 'enabled' => true, 'align' => 'justify'],
    ]]);
    expect(landingSection($clean, 'hero')['align'])->toBe('center')
        ->and(landingSection($clean, 'hero')['headline'])->toBe("Crystal-clear water,\nevery week") // the line break survives
        ->and(landingSection($clean, 'stats')['align'])->toBe('left');
});

test('the header shows the logo beside the name by default, including for configs saved before this setting', function () {
    expect(LandingConfig::fromStored(null)['title'])->toMatchArray(['icon' => 'logo', 'display' => 'icon_title'])
        ->and(LandingConfig::fromStored(json_encode(['title' => ['font' => 'Poppins']]))['title'])->toMatchArray(['icon' => 'logo', 'display' => 'icon_title']);
});

test('the title keeps a known icon, the logo or none, and one of three displays', function () {
    $title = fn (array $t) => LandingConfig::sanitize(['title' => $t])['title'];

    expect($title(['icon' => 'palmtree', 'display' => 'icon']))->toMatchArray(['icon' => 'palmtree', 'display' => 'icon'])
        ->and($title(['icon' => 'logo'])['icon'])->toBe('logo')
        ->and($title(['icon' => null])['icon'])->toBeNull()
        ->and($title(['icon' => '<svg onload=x>'])['icon'])->toBeNull()
        ->and($title([])['icon'])->toBe('logo')
        ->and($title(['display' => 'huge'])['display'])->toBe('icon_title');
});

test('reading a stored config cleans every block, not just the sections', function () {
    $config = LandingConfig::fromStored(json_encode([
        'title' => ['font' => '</style><script>', 'color' => 'red', 'evil' => 1],
        'seo' => ['title' => str_repeat('x', 500), 'og_image' => '../../.env'],
        'social' => ['facebook' => 'javascript:alert(1)', 'myspace' => 'https://myspace.com/x'],
    ]));

    expect($config['title'])->toMatchArray(['font' => 'Inter', 'color' => null])->not->toHaveKey('evil')
        ->and(mb_strlen((string) $config['seo']['title']))->toBe(70)
        ->and($config['seo']['og_image'])->toBeNull()
        ->and($config['social'])->toBe(array_fill_keys(LandingConfig::SOCIAL_PLATFORMS, null));
});
