<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\TenantSetting;
use Illuminate\Support\Facades\Storage;

/**
 * A company's landing-page config: the canonical defaults, and sanitize() — the
 * trust boundary run on every save AND every read, so the page never trusts
 * stored JSON blindly. A stored config is laid over the defaults first, so one
 * saved before a setting existed gets that setting's default.
 *
 * Lists of keys here are mirrored by the site's labels in components/landing
 * (badges.ts, icons.ts, stats.ts, meta.ts); a unit test keeps them in step.
 *
 * @phpstan-type Config array{
 *     version: int,
 *     seo: array{title: string|null, description: string|null, og_image: string|null},
 *     theme: array{chatbot: bool},
 *     title: array<string, mixed>,
 *     social: array<string, string|null>,
 *     sections: list<array<string, mixed>>,
 * }
 */
class LandingConfig
{
    /** Section keys, in default render order. */
    public const SECTION_KEYS = [
        'hero', 'stats', 'services', 'quote', 'gallery', 'team',
        'service_area', 'booking', 'testimonials', 'faq', 'cta', 'contact',
    ];

    /** Live counts the stats strip may show. */
    public const METRICS = ['pools_serviced', 'visits_completed', 'years_active', 'happy_customers', 'gallons_maintained', 'water_tests', 'technicians'];

    /** Hero badges a company may pick, at most MAX_BADGES. */
    public const HERO_BADGES = [
        'licensed', 'cpo', 'five_star', 'same_day', 'free_estimates', 'no_contracts', 'satisfaction', 'locally_owned', 'family_owned',
        'veteran_owned', 'photo_reports', 'pay_online', 'autopay', 'background_checked', 'eco_friendly', 'salt_systems', 'emergency', 'spanish',
    ];

    public const MAX_BADGES = 6;

    /** Icons a company can pick (for its services and the mark beside its name). */
    public const ICONS = [
        'droplet', 'waves', 'sun', 'sparkles', 'shield', 'wrench', 'flask', 'thermometer', 'filter',
        'lifebuoy', 'palmtree', 'umbrella', 'shell', 'leaf', 'house',
    ];

    /** What the header shows: the mark and the name, the name alone, or the mark alone. */
    public const TITLE_DISPLAYS = ['icon_title', 'title', 'icon'];

    /** How the hero text and the stats strip line up. */
    public const ALIGNS = ['left', 'center', 'right'];

    /** Hero background presets (images in public/assets/images/hero-presets). */
    public const HERO_PRESETS = ['backyard', 'cityscape', 'infinity', 'islands', 'night', 'patio', 'resort', 'skyline', 'sunset', 'tiles', 'underwater', 'water'];

    /** Hero effects a company can switch on (plus the overlay's opacity). */
    public const HERO_EFFECTS = ['ken_burns', 'cta_glow', 'scroll_cue', 'dark_overlay', 'dot_matrix', 'vignette'];

    /** Fonts the header title (and so every heading) may use, loaded from bunny.net. */
    public const TITLE_FONTS = ['Inter', 'Poppins', 'Montserrat', 'Raleway', 'Nunito', 'Lato', 'Roboto', 'Oswald', 'Bebas Neue', 'Playfair Display', 'Merriweather'];

    /** Footer social platforms a company can link (each a profile URL or null). */
    public const SOCIAL_PLATFORMS = ['facebook', 'instagram', 'twitter', 'linkedin', 'youtube'];

    /** Small / medium / large / extra large: the title and the hero headline. */
    private const SIZES = ['sm', 'md', 'lg', 'xl'];

    private const CAP = ['items' => 20, 'faq' => 30, 'gallery' => 24, 'team' => 12];

    /** Largest number a company may type in place of a live stat. */
    private const MAX_STAT = 999_999_999;

    /**
     * The canonical starter config — and already clean (sanitize() leaves it as is).
     *
     * @return Config
     */
    public static function defaults(): array
    {
        return [
            'version' => 1,
            'seo' => ['title' => null, 'description' => null, 'og_image' => null],
            'theme' => ['chatbot' => false],
            // Header title styling. `text` null = the company name; `color` null = the
            // theme's text color.
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
                // Beside the name: the uploaded logo (shown only once there is one), an icon, or null.
                'icon' => 'logo',
                'display' => 'icon_title',
            ],
            'social' => array_fill_keys(self::SOCIAL_PLATFORMS, null),
            'sections' => [
                ['key' => 'hero', 'enabled' => true, 'headline' => 'Crystal-clear water, every single week', 'subhead' => 'Reliable, professional pool care so you can skip the chemistry and just enjoy the swim.', 'cta_label' => 'Get a free quote', 'cta_anchor' => 'contact', 'bg_type' => 'preset', 'preset' => 'backyard', 'image_path' => null, 'gradient_start' => '#0f172a', 'gradient_end' => '#0369a1', 'headline_size' => 'lg', 'headline_max_width' => 56, 'align' => 'left', 'badges' => ['licensed', 'cpo', 'five_star', 'same_day'], 'effects' => ['dark_overlay' => true, 'overlay_opacity' => 40, 'cta_glow' => true, 'scroll_cue' => true, 'ken_burns' => true, 'dot_matrix' => false, 'vignette' => false]],
                ['key' => 'stats', 'enabled' => true, 'metrics' => ['pools_serviced', 'happy_customers', 'visits_completed', 'water_tests', 'gallons_maintained', 'years_active'], 'overrides' => new \stdClass, 'align' => 'left'],
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
                ['key' => 'contact', 'enabled' => true, 'heading' => 'Get in touch', 'blurb' => 'Tell us about your pool and we’ll get right back to you.'],
            ],
        ];
    }

    /**
     * A company's saved config, clean and complete.
     *
     * @return Config
     */
    public static function forTenant(int $tenantId): array
    {
        return self::fromStored(TenantSetting::getFor($tenantId, 'landing'));
    }

    /**
     * A stored config laid over the defaults, then cleaned exactly as a save is.
     *
     * @return Config
     */
    public static function fromStored(?string $json): array
    {
        $stored = is_string($json) && $json !== '' ? json_decode($json, true) : null;

        return self::sanitize(is_array($stored) ? self::overDefaults($stored) : self::defaults());
    }

    /**
     * The enabled sections, in the company's order: what the public page renders.
     *
     * @param  Config  $config
     * @return list<array<string, mixed>>
     */
    public static function enabledOrdered(array $config): array
    {
        return array_values(array_filter($config['sections'], static fn (array $s): bool => $s['enabled'] === true));
    }

    /**
     * Sections ready to render: a stored image path also as its public URL (the
     * path stays, for saving).
     *
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    public static function withImageUrls(array $sections): array
    {
        return array_map(static function (array $s): array {
            if (array_key_exists('image_path', $s)) {
                $s['image_url'] = is_string($s['image_path']) ? Storage::disk('public')->url($s['image_path']) : null;
            }

            return $s;
        }, $sections);
    }

    /**
     * The trust boundary: known sections once each (in the order given, any left
     * out appended from the defaults), and each block and section reduced to its
     * known fields with clean values — capped text, picked keys from known lists,
     * clamped numbers, image paths inside the landing folder.
     *
     * @param  array<string, mixed>  $input
     * @return Config
     */
    public static function sanitize(array $input): array
    {
        $sections = [];
        foreach (is_array($input['sections'] ?? null) ? $input['sections'] : [] as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $key = $raw['key'] ?? null;
            if (is_string($key) && in_array($key, self::SECTION_KEYS, true) && ! isset($sections[$key])) {
                $sections[$key] = self::sanitizeSection($key, $raw);
            }
        }
        foreach (self::defaults()['sections'] as $default) {
            $sections[$default['key']] ??= $default;
        }

        $seo = is_array($input['seo'] ?? null) ? $input['seo'] : [];
        $theme = is_array($input['theme'] ?? null) ? $input['theme'] : [];
        $title = is_array($input['title'] ?? null) ? $input['title'] : [];
        $social = is_array($input['social'] ?? null) ? $input['social'] : [];
        $links = [];
        foreach (self::SOCIAL_PLATFORMS as $p) {
            $links[$p] = self::url($social[$p] ?? null);
        }

        return [
            'version' => 1,
            'seo' => [
                'title' => self::str($seo['title'] ?? null, 70),
                'description' => self::str($seo['description'] ?? null, 200),
                'og_image' => self::imagePath($seo['og_image'] ?? null),
            ],
            'theme' => ['chatbot' => (bool) ($theme['chatbot'] ?? false)],
            'title' => [
                'text' => self::str($title['text'] ?? null, 60),
                'font' => self::oneOf($title['font'] ?? null, self::TITLE_FONTS, 'Inter'),
                'size' => self::oneOf($title['size'] ?? null, self::SIZES, 'md'),
                'weight' => self::oneOf($title['weight'] ?? null, ['400', '500', '600', '700', '800'], '700'),
                'tracking' => self::oneOf($title['tracking'] ?? null, ['tight', 'normal', 'wide', 'wider'], 'normal'),
                'color_type' => self::oneOf($title['color_type'] ?? null, ['solid', 'gradient'], 'solid'),
                'color' => self::hex($title['color'] ?? null),
                'gradient_start' => self::hex($title['gradient_start'] ?? null) ?? '#0ea5e9',
                'gradient_via' => self::hex($title['gradient_via'] ?? null) ?? '#38bdf8',
                'gradient_end' => self::hex($title['gradient_end'] ?? null) ?? '#f97316',
                'gradient_angle' => self::int($title['gradient_angle'] ?? null, 0, 360, 135),
                'outline' => (bool) ($title['outline'] ?? false),
                'outline_color' => self::hex($title['outline_color'] ?? null) ?? '#000000',
                'outline_width' => self::int($title['outline_width'] ?? null, 0, 3, 1),
                'shadow' => self::oneOf($title['shadow'] ?? null, ['none', 'soft', 'glow'], 'none'),
                // Left out = the default (the logo); an explicit null or unknown key = none.
                'icon' => array_key_exists('icon', $title) ? self::titleIcon($title['icon']) : 'logo',
                'display' => self::oneOf($title['display'] ?? null, self::TITLE_DISPLAYS, 'icon_title'),
            ],
            'social' => $links,
            'sections' => array_values($sections),
        ];
    }

    /**
     * A stored config over the defaults, block by block and section by section,
     * leaving sanitize() to drop duplicates and add any section it left out.
     *
     * @param  array<mixed>  $stored
     * @return array<string, mixed>
     */
    private static function overDefaults(array $stored): array
    {
        $config = self::defaults();
        foreach (['seo', 'theme', 'title', 'social'] as $block) {
            $config[$block] = array_merge($config[$block], is_array($stored[$block] ?? null) ? $stored[$block] : []);
        }

        $defaults = array_column($config['sections'], null, 'key');
        $config['sections'] = [];
        foreach (is_array($stored['sections'] ?? null) ? $stored['sections'] : [] as $s) {
            $key = is_array($s) ? ($s['key'] ?? null) : null;
            if (is_array($s) && is_string($key) && isset($defaults[$key])) {
                $config['sections'][] = array_merge($defaults[$key], $s);
            }
        }

        return $config;
    }

    /**
     * One section reduced to its known fields for its key.
     *
     * @param  array<mixed>  $raw
     * @return array<string, mixed>
     */
    private static function sanitizeSection(string $key, array $raw): array
    {
        $base = ['key' => $key, 'enabled' => (bool) ($raw['enabled'] ?? false)];
        $heading = self::str($raw['heading'] ?? null, 120);
        $blurb = self::str($raw['blurb'] ?? null, 240);

        return match ($key) {
            'hero' => $base + [
                'headline' => self::str($raw['headline'] ?? null, 120),
                'subhead' => self::str($raw['subhead'] ?? null, 240),
                'cta_label' => self::str($raw['cta_label'] ?? null, 40),
                'cta_anchor' => self::str($raw['cta_anchor'] ?? null, 40),
                'bg_type' => self::oneOf($raw['bg_type'] ?? null, ['preset', 'image', 'gradient'], 'preset'),
                'preset' => self::oneOf($raw['preset'] ?? null, self::HERO_PRESETS, 'backyard'),
                'image_path' => self::imagePath($raw['image_path'] ?? null),
                'gradient_start' => self::hex($raw['gradient_start'] ?? null) ?? '#0f172a',
                'gradient_end' => self::hex($raw['gradient_end'] ?? null) ?? '#0369a1',
                'headline_size' => self::oneOf($raw['headline_size'] ?? null, self::SIZES, 'lg'),
                'headline_max_width' => self::int($raw['headline_max_width'] ?? null, 32, 80, 56),
                'align' => self::oneOf($raw['align'] ?? null, self::ALIGNS, 'left'),
                'badges' => self::pick($raw['badges'] ?? null, self::HERO_BADGES, self::MAX_BADGES),
                'effects' => self::effects($raw['effects'] ?? null),
            ],
            'stats' => $base + [
                'metrics' => self::pick($raw['metrics'] ?? null, self::METRICS, count(self::METRICS)),
                // An object, so an empty set reaches the page as {} (a PHP [] would arrive as an array).
                'overrides' => (object) self::statOverrides($raw['overrides'] ?? null),
                'align' => self::oneOf($raw['align'] ?? null, self::ALIGNS, 'left'),
            ],
            'services' => $base + ['heading' => $heading, 'items' => self::items($raw['items'] ?? null, ['title' => 80, 'body' => 280, 'icon' => 40], self::CAP['items'])],
            'quote', 'booking', 'contact' => $base + ['heading' => $heading, 'blurb' => $blurb],
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
            default => $base,
        };
    }

    /**
     * Rows of `{field => maxLen}` text, dropping empty rows, at most $cap.
     *
     * @param  array<string, int>  $fields
     * @return list<array<string, string|null>>
     */
    private static function items(mixed $raw, array $fields, int $cap): array
    {
        $out = [];
        foreach (is_array($raw) ? $raw : [] as $item) {
            $row = [];
            foreach ($fields as $f => $max) {
                $row[$f] = is_array($item) ? self::str($item[$f] ?? null, $max) : null;
            }
            if (array_filter($row, static fn (?string $v): bool => $v !== null) !== []) {
                $out[] = $row;
            }
        }

        return array_slice($out, 0, $cap);
    }

    /**
     * Team members: a user id plus an optional title and bio.
     *
     * @return list<array{user_id: int, title: string|null, bio: string|null}>
     */
    private static function members(mixed $raw): array
    {
        $out = [];
        foreach (is_array($raw) ? $raw : [] as $m) {
            $id = is_array($m) ? self::whole($m['user_id'] ?? null) : null;
            if (is_array($m) && $id !== null) {
                $out[] = ['user_id' => $id, 'title' => self::str($m['title'] ?? null, 80), 'bio' => self::str($m['bio'] ?? null, 400)];
            }
        }

        return array_slice($out, 0, self::CAP['team']);
    }

    /**
     * Numbers a company typed in place of live stats: whole numbers, known stats
     * only. A stat left out shows its live count.
     *
     * @return array<string, int>
     */
    private static function statOverrides(mixed $raw): array
    {
        $out = [];
        foreach (self::METRICS as $m) {
            $n = is_array($raw) ? self::whole($raw[$m] ?? null) : null;
            if ($n !== null) {
                $out[$m] = max(0, min(self::MAX_STAT, $n));
            }
        }

        return $out;
    }

    /**
     * The hero effect switches, plus the overlay's opacity.
     *
     * @return array<string, bool|int>
     */
    private static function effects(mixed $raw): array
    {
        $e = is_array($raw) ? $raw : [];
        $out = ['overlay_opacity' => self::int($e['overlay_opacity'] ?? null, 0, 90, 40)];
        foreach (self::HERO_EFFECTS as $fx) {
            $out[$fx] = (bool) ($e[$fx] ?? false);
        }

        return $out;
    }

    /**
     * Keys from a known list, in the order given, each once, at most $max. An
     * empty list stays empty (the company turned them all off).
     *
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private static function pick(mixed $raw, array $allowed, int $max): array
    {
        $picked = array_filter(is_array($raw) ? $raw : [], static fn ($k): bool => in_array($k, $allowed, true));

        return array_slice(array_values(array_unique($picked)), 0, $max);
    }

    /**
     * $v when it's one of $allowed, else $default.
     *
     * @param  list<string>  $allowed
     */
    private static function oneOf(mixed $v, array $allowed, string $default): string
    {
        return is_string($v) && in_array($v, $allowed, true) ? $v : $default;
    }

    /** The mark beside the company name: 'logo', a known icon, or null for none. */
    private static function titleIcon(mixed $v): ?string
    {
        return $v === 'logo' ? 'logo' : (is_string($v) && in_array($v, self::ICONS, true) ? $v : null);
    }

    /** Trimmed text, at most $max characters; blank is null. */
    private static function str(mixed $v, int $max): ?string
    {
        $v = is_string($v) ? trim($v) : '';

        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    /** A whole number, as JSON (5) or a form ("5") sends it, else null. */
    private static function whole(mixed $v): ?int
    {
        return is_int($v) || (is_string($v) && ctype_digit($v)) ? (int) $v : null;
    }

    /** A whole number clamped to $min–$max, else $default. */
    private static function int(mixed $v, int $min, int $max, int $default): int
    {
        $n = self::whole($v);

        return $n === null ? $default : max($min, min($max, $n));
    }

    /** A #rrggbb color, else null (callers give their own default). */
    private static function hex(mixed $v): ?string
    {
        return is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) === 1 ? $v : null;
    }

    /** An image this company uploaded for its landing page (under landing/), else null. */
    private static function imagePath(mixed $v): ?string
    {
        return is_string($v) && preg_match('#^landing/[\w/-]+\.(jpe?g|png|webp)$#i', $v) === 1 ? $v : null;
    }

    /** An http(s) URL of at most 200 characters, else null (so a bad one just hides its icon). */
    private static function url(mixed $v): ?string
    {
        $v = is_string($v) ? trim($v) : '';
        $ok = $v !== '' && mb_strlen($v) <= 200 && filter_var($v, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $v) === 1;

        return $ok ? $v : null;
    }
}
