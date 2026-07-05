<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The tenant landing-page config: canonical defaults, a forward-compatible
 * merge of the stored JSON over those defaults (so partial/old documents always
 * render), and sanitize() — the trust boundary applied on every save AND on
 * read (whitelist section keys + fields, cap arrays, clean image paths) so the
 * public renderer never trusts stored JSON blindly.
 */
class LandingConfig
{
    /** Whitelisted section keys, in default render order. */
    public const SECTION_KEYS = [
        'hero', 'stats', 'services', 'quote', 'gallery', 'team',
        'service_area', 'booking', 'testimonials', 'faq', 'cta', 'contact',
    ];

    /** Live metric keys the stats section may surface. */
    public const METRICS = ['pools_serviced', 'visits_completed', 'years_active'];

    /** Hero background presets (images in public/assets/images/hero-presets). */
    public const HERO_PRESETS = ['backyard', 'cityscape', 'infinity', 'islands', 'night', 'patio', 'resort', 'skyline', 'sunset', 'tiles', 'underwater', 'water'];

    /** Fonts the header company title may use (loaded from bunny.net on the public page). */
    public const TITLE_FONTS = ['Inter', 'Poppins', 'Montserrat', 'Raleway', 'Nunito', 'Lato', 'Roboto', 'Oswald', 'Bebas Neue', 'Playfair Display', 'Merriweather'];

    private const CAP = ['items' => 20, 'faq' => 30, 'gallery' => 24, 'team' => 12];

    /**
     * The canonical starter document — the single source of section defaults.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'version' => 1,
            'seo' => ['title' => null, 'description' => null, 'og_image' => null],
            'theme' => ['accent' => 'brand', 'hero_style' => 'image-right', 'show_logo' => true, 'chatbot' => false],
            // Header company-title styling. `text` null = the tenant name; `color` null = inherited
            // foreground (so an un-customized title looks exactly like today).
            'title' => [
                'text' => null,
                'font' => 'Inter',
                'size' => 'md',
                'weight' => '700',
                'tracking' => 'normal',
                'color_type' => 'solid',
                'color' => null,
                'gradient_start' => '#0ea5e9',
                'gradient_via' => '#38bdf8',
                'gradient_end' => '#f97316',
                'gradient_angle' => 135,
                'outline' => false,
                'outline_color' => '#000000',
                'outline_width' => 1,
                'shadow' => 'none',
            ],
            'sections' => [
                ['key' => 'hero', 'enabled' => true, 'headline' => 'Crystal-clear water, every single week', 'subhead' => 'Reliable, professional pool care so you can skip the chemistry and just enjoy the swim.', 'cta_label' => 'Get a free quote', 'cta_anchor' => 'contact', 'bg_type' => 'preset', 'preset' => 'backyard', 'image_path' => null, 'gradient_start' => '#0f172a', 'gradient_end' => '#0369a1', 'headline_size' => 'lg', 'headline_max_width' => 56, 'effects' => ['dark_overlay' => true, 'overlay_opacity' => 40, 'cta_glow' => true, 'scroll_cue' => true, 'ken_burns' => true]],
                ['key' => 'stats', 'enabled' => true, 'heading' => 'By the numbers', 'metrics' => self::METRICS],
                ['key' => 'services', 'enabled' => true, 'heading' => 'What we do', 'items' => [
                    ['title' => 'Weekly maintenance', 'body' => 'Full chemical balancing, skimming, and equipment checks on a dependable schedule.', 'icon' => 'droplet'],
                    ['title' => 'Repairs & equipment', 'body' => 'Pumps, filters, heaters, and automation — diagnosed and fixed right the first time.', 'icon' => 'wrench'],
                    ['title' => 'Green-to-clean', 'body' => 'Neglected or algae-green pool? We bring it back to sparkling.', 'icon' => 'sparkles'],
                ]],
                ['key' => 'quote', 'enabled' => true, 'heading' => 'Get an instant estimate', 'blurb' => 'Tell us about your pool for a ballpark price — we’ll confirm the exact quote after a quick look.'],
                ['key' => 'gallery', 'enabled' => true, 'heading' => 'Recent work', 'limit' => 12],
                ['key' => 'team', 'enabled' => false, 'heading' => 'Meet the team', 'members' => []],
                ['key' => 'service_area', 'enabled' => true, 'heading' => 'Where we serve', 'radius_label' => 'Proudly serving your neighborhood', 'zip' => null],
                ['key' => 'booking', 'enabled' => false, 'heading' => 'Request your first visit', 'blurb' => 'Pick a service and a date that works — we’ll confirm by phone or email.'],
                ['key' => 'testimonials', 'enabled' => true, 'heading' => 'Loved by homeowners', 'items' => []],
                ['key' => 'faq', 'enabled' => true, 'heading' => 'Common questions', 'items' => []],
                ['key' => 'cta', 'enabled' => true, 'headline' => 'Ready for a worry-free pool?', 'button_label' => 'Get started', 'button_anchor' => 'contact'],
                ['key' => 'contact', 'enabled' => true, 'heading' => 'Get in touch', 'blurb' => 'Tell us about your pool and we’ll get right back to you.', 'show_phone' => true],
            ],
        ];
    }

    /**
     * Decode stored JSON + deep-merge over defaults → always a complete,
     * field-whitelisted config (each section re-sanitized, so a tampered stored
     * doc can't smuggle unknown keys/fields into the render).
     *
     * @return array<string, mixed>
     */
    public static function fromStored(?string $json): array
    {
        $defaults = self::defaults();
        $decoded = is_string($json) && $json !== '' ? json_decode($json, true) : null;
        if (! is_array($decoded)) {
            return $defaults;
        }

        return [
            'version' => 1,
            'seo' => array_merge($defaults['seo'], is_array($decoded['seo'] ?? null) ? $decoded['seo'] : []),
            'theme' => array_merge($defaults['theme'], is_array($decoded['theme'] ?? null) ? $decoded['theme'] : []),
            'title' => array_merge($defaults['title'], is_array($decoded['title'] ?? null) ? $decoded['title'] : []),
            'sections' => self::mergeSections(is_array($decoded['sections'] ?? null) ? $decoded['sections'] : []),
        ];
    }

    /**
     * Enabled sections, in their stored order — exactly what the public page
     * renders.
     *
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     */
    public static function enabledOrdered(array $config): array
    {
        $sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];

        return array_values(array_filter(
            $sections,
            static fn ($s): bool => is_array($s)
                && ($s['enabled'] ?? false) === true
                && in_array($s['key'] ?? null, self::SECTION_KEYS, true),
        ));
    }

    /**
     * The save-time trust boundary: drop non-whitelisted section keys, dedupe,
     * whitelist each section's fields, cap arrays, clean image paths, and ensure
     * every section key is present exactly once (so the editor always has the
     * full set to toggle).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function sanitize(array $input): array
    {
        $clean = [];
        $seen = [];
        foreach (is_array($input['sections'] ?? null) ? $input['sections'] : [] as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $key = $raw['key'] ?? null;
            if (! is_string($key) || ! in_array($key, self::SECTION_KEYS, true) || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $clean[] = self::sanitizeSection($key, $raw);
        }
        foreach (self::defaults()['sections'] as $def) {
            if (! isset($seen[$def['key']])) {
                $clean[] = $def;
            }
        }

        $seo = is_array($input['seo'] ?? null) ? $input['seo'] : [];
        $theme = is_array($input['theme'] ?? null) ? $input['theme'] : [];
        $title = is_array($input['title'] ?? null) ? $input['title'] : [];

        return [
            'version' => 1,
            'seo' => [
                'title' => self::str($seo['title'] ?? null, 70),
                'description' => self::str($seo['description'] ?? null, 200),
                'og_image' => self::imagePath($seo['og_image'] ?? null),
            ],
            'theme' => [
                'accent' => self::str($theme['accent'] ?? null, 20) ?? 'brand',
                'hero_style' => in_array($theme['hero_style'] ?? null, ['image-right', 'image-left', 'centered'], true) ? $theme['hero_style'] : 'image-right',
                'show_logo' => (bool) ($theme['show_logo'] ?? true),
                'chatbot' => (bool) ($theme['chatbot'] ?? false),
            ],
            'title' => [
                'text' => self::str($title['text'] ?? null, 60),
                'font' => in_array($title['font'] ?? null, self::TITLE_FONTS, true) ? $title['font'] : 'Inter',
                'size' => in_array($title['size'] ?? null, ['sm', 'md', 'lg', 'xl'], true) ? $title['size'] : 'md',
                'weight' => in_array($title['weight'] ?? null, ['400', '500', '600', '700', '800'], true) ? $title['weight'] : '700',
                'tracking' => in_array($title['tracking'] ?? null, ['tight', 'normal', 'wide', 'wider'], true) ? $title['tracking'] : 'normal',
                'color_type' => in_array($title['color_type'] ?? null, ['solid', 'gradient'], true) ? $title['color_type'] : 'solid',
                'color' => self::hexOrNull($title['color'] ?? null),
                'gradient_start' => self::hex($title['gradient_start'] ?? null, '#0ea5e9'),
                'gradient_via' => self::hex($title['gradient_via'] ?? null, '#38bdf8'),
                'gradient_end' => self::hex($title['gradient_end'] ?? null, '#f97316'),
                'gradient_angle' => self::int($title['gradient_angle'] ?? null, 0, 360, 135),
                'outline' => (bool) ($title['outline'] ?? false),
                'outline_color' => self::hex($title['outline_color'] ?? null, '#000000'),
                'outline_width' => self::int($title['outline_width'] ?? null, 0, 3, 1),
                'shadow' => in_array($title['shadow'] ?? null, ['none', 'soft', 'glow'], true) ? $title['shadow'] : 'none',
            ],
            'sections' => $clean,
        ];
    }

    /**
     * Merge stored sections over the defaults, by key, re-sanitizing each and
     * appending any section type the stored doc omitted.
     *
     * @param  list<mixed>  $stored
     * @return list<array<string, mixed>>
     */
    private static function mergeSections(array $stored): array
    {
        $byKey = [];
        foreach (self::defaults()['sections'] as $d) {
            $byKey[$d['key']] = $d;
        }

        $merged = [];
        $seen = [];
        foreach ($stored as $s) {
            if (! is_array($s)) {
                continue;
            }
            $key = $s['key'] ?? null;
            if (! is_string($key) || ! isset($byKey[$key]) || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $merged[] = self::sanitizeSection($key, array_merge($byKey[$key], $s));
        }
        foreach (self::defaults()['sections'] as $d) {
            if (! isset($seen[$d['key']])) {
                $merged[] = $d;
            }
        }

        return $merged;
    }

    /**
     * Clean a single section to its whitelisted fields for its key.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private static function sanitizeSection(string $key, array $raw): array
    {
        $base = ['key' => $key, 'enabled' => (bool) ($raw['enabled'] ?? false)];
        $heading = self::str($raw['heading'] ?? null, 120);

        return match ($key) {
            'hero' => $base + [
                'headline' => self::str($raw['headline'] ?? null, 120),
                'subhead' => self::str($raw['subhead'] ?? null, 240),
                'cta_label' => self::str($raw['cta_label'] ?? null, 40),
                'cta_anchor' => self::str($raw['cta_anchor'] ?? null, 40),
                'bg_type' => in_array($raw['bg_type'] ?? null, ['preset', 'image', 'gradient'], true) ? $raw['bg_type'] : 'preset',
                'preset' => in_array($raw['preset'] ?? null, self::HERO_PRESETS, true) ? $raw['preset'] : 'backyard',
                'image_path' => self::imagePath($raw['image_path'] ?? null),
                'gradient_start' => self::hex($raw['gradient_start'] ?? null, '#0f172a'),
                'gradient_end' => self::hex($raw['gradient_end'] ?? null, '#0369a1'),
                'headline_size' => in_array($raw['headline_size'] ?? null, ['sm', 'md', 'lg', 'xl'], true) ? $raw['headline_size'] : 'lg',
                'headline_max_width' => self::int($raw['headline_max_width'] ?? null, 32, 80, 56),
                'effects' => self::effects($raw['effects'] ?? null),
            ],
            'stats' => $base + [
                'heading' => $heading,
                'metrics' => array_values(array_filter(
                    is_array($raw['metrics'] ?? null) ? $raw['metrics'] : [],
                    static fn ($m): bool => in_array($m, self::METRICS, true),
                )),
            ],
            'services' => $base + ['heading' => $heading, 'items' => self::items($raw['items'] ?? null, ['title' => 80, 'body' => 280, 'icon' => 40], self::CAP['items'])],
            'quote', 'booking' => $base + ['heading' => $heading, 'blurb' => self::str($raw['blurb'] ?? null, 240)],
            'gallery' => $base + ['heading' => $heading, 'limit' => self::int($raw['limit'] ?? null, 1, self::CAP['gallery'], 12)],
            'team' => $base + ['heading' => $heading, 'members' => self::members($raw['members'] ?? null)],
            'service_area' => $base + ['heading' => $heading, 'radius_label' => self::str($raw['radius_label'] ?? null, 80), 'zip' => self::str($raw['zip'] ?? null, 12)],
            'testimonials' => $base + ['heading' => $heading, 'items' => self::items($raw['items'] ?? null, ['quote' => 400, 'author' => 80, 'location' => 80], self::CAP['items'])],
            'faq' => $base + ['heading' => $heading, 'items' => self::items($raw['items'] ?? null, ['q' => 160, 'a' => 600], self::CAP['faq'])],
            'cta' => $base + [
                'headline' => self::str($raw['headline'] ?? null, 120),
                'button_label' => self::str($raw['button_label'] ?? null, 40),
                'button_anchor' => self::str($raw['button_anchor'] ?? null, 40),
            ],
            'contact' => $base + ['heading' => $heading, 'blurb' => self::str($raw['blurb'] ?? null, 240), 'show_phone' => (bool) ($raw['show_phone'] ?? true)],
            default => $base,
        };
    }

    /**
     * Clean a list of `{field => maxLen}` string rows, dropping empty rows and
     * capping the count.
     *
     * @param  array<string, int>  $fields
     * @return list<array<string, string|null>>
     */
    private static function items(mixed $raw, array $fields, int $cap): array
    {
        $out = [];
        foreach (is_array($raw) ? $raw : [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $row = [];
            foreach ($fields as $f => $max) {
                $row[$f] = self::str($item[$f] ?? null, $max);
            }
            if (array_filter($row, static fn ($v): bool => $v !== null && $v !== '') === []) {
                continue;
            }
            $out[] = $row;
            if (count($out) >= $cap) {
                break;
            }
        }

        return $out;
    }

    /**
     * Clean team members: a positive integer user_id + optional title/bio.
     *
     * @return list<array<string, string|int|null>>
     */
    private static function members(mixed $raw): array
    {
        $out = [];
        foreach (is_array($raw) ? $raw : [] as $m) {
            if (! is_array($m)) {
                continue;
            }
            $id = $m['user_id'] ?? null;
            if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
                continue;
            }
            $out[] = [
                'user_id' => (int) $id,
                'title' => self::str($m['title'] ?? null, 80),
                'bio' => self::str($m['bio'] ?? null, 400),
            ];
            if (count($out) >= self::CAP['team']) {
                break;
            }
        }

        return $out;
    }

    private static function str(mixed $v, int $max): ?string
    {
        if (! is_string($v)) {
            return null;
        }
        $v = trim($v);

        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    private static function int(mixed $v, int $min, int $max, int $default): int
    {
        if (! is_int($v) && ! (is_string($v) && ctype_digit($v))) {
            return $default;
        }

        return max($min, min($max, (int) $v));
    }

    private static function imagePath(mixed $v): ?string
    {
        return is_string($v) && preg_match('#^landing/[\w/-]+\.(jpe?g|png|webp)$#i', $v) === 1 ? $v : null;
    }

    private static function hex(mixed $v, string $default): string
    {
        return is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) === 1 ? $v : $default;
    }

    /** Like hex() but preserves null — a null title color means "inherit the theme foreground". */
    private static function hexOrNull(mixed $v): ?string
    {
        return is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) === 1 ? $v : null;
    }

    /**
     * Hero effect toggles (+ overlay opacity).
     *
     * @return array<string, bool|int>
     */
    private static function effects(mixed $raw): array
    {
        $e = is_array($raw) ? $raw : [];

        return [
            'dark_overlay' => (bool) ($e['dark_overlay'] ?? false),
            'overlay_opacity' => self::int($e['overlay_opacity'] ?? null, 0, 90, 40),
            'cta_glow' => (bool) ($e['cta_glow'] ?? false),
            'scroll_cue' => (bool) ($e['scroll_cue'] ?? false),
            'ken_burns' => (bool) ($e['ken_burns'] ?? false),
            'dot_matrix' => (bool) ($e['dot_matrix'] ?? false),
            'vignette' => (bool) ($e['vignette'] ?? false),
        ];
    }
}
