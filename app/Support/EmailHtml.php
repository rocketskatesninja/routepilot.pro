<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Rich text for emails people write (Email people, the company signature): the
 * one place it's cleaned, styled for mail clients and turned into plain text.
 *
 * Cleaning is an allow-list — paragraphs, line breaks, bold / italic / underline /
 * strike, two heading sizes, lists, quotes and http(s)/mailto links — so scripts,
 * styles, event handlers and javascript: links never get through. It runs on the
 * server whatever the browser sent.
 */
class EmailHtml
{
    /** How much a message may be: the form's limit, with room for the markup. */
    public const MAX_LENGTH = 50000;

    /** Stands in for each recipient's first name. */
    public const FIRST_NAME = '{first name}';

    public static function clean(?string $html): string
    {
        static $sanitizer = null;
        $sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowElement('p')
                ->allowElement('br')
                ->allowElement('strong')
                ->allowElement('b')
                ->allowElement('em')
                ->allowElement('i')
                ->allowElement('u')
                ->allowElement('s')
                ->allowElement('h2')
                ->allowElement('h3')
                ->allowElement('ul')
                ->allowElement('ol')
                ->allowElement('li')
                ->allowElement('blockquote')
                ->allowElement('a', ['href'])
                ->allowLinkSchemes(['http', 'https', 'mailto'])
                ->allowRelativeLinks(false)
                ->withMaxInputLength(self::MAX_LENGTH),
        );

        $clean = $sanitizer->sanitize((string) $html);
        // The editor wraps each list item in a paragraph, which in an email spaces the list
        // out; and it leaves an empty paragraph at the end.
        $clean = str_replace(['<li><p>', '</p></li>'], ['<li>', '</li>'], $clean);

        return trim(preg_replace('#(<p>\s*</p>\s*)+$#', '', $clean) ?? $clean);
    }

    /** Whether cleaned HTML has any words in it (an editor left empty still sends "<p></p>"). */
    public static function hasText(string $html): bool
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)) !== '';
    }

    /**
     * Cleaned HTML with the inline styles mail clients need (they ignore style
     * sheets). Only our own fixed styles are added; the cleaning already removed
     * any the writer's HTML carried.
     */
    public static function styled(string $html): string
    {
        return strtr($html, [
            '<p>' => '<p style="margin: 0 0 12px;">',
            '<h2>' => '<h2 style="margin: 16px 0 8px; font-size: 17px; line-height: 1.3;">',
            '<h3>' => '<h3 style="margin: 14px 0 6px; font-size: 15px; line-height: 1.3;">',
            '<ul>' => '<ul style="margin: 0 0 12px; padding-left: 22px;">',
            '<ol>' => '<ol style="margin: 0 0 12px; padding-left: 22px;">',
            '<li>' => '<li style="margin: 0 0 4px;">',
            '<blockquote>' => '<blockquote style="margin: 0 0 12px; padding-left: 12px; border-left: 3px solid #cbd5e1; color: #475569;">',
            '<a href=' => '<a style="color: #0284c7; text-decoration: underline;" href=',
        ]);
    }

    /** The plain-text copy of an email: paragraphs and list items on their own lines, links spelled out. */
    public static function toText(string $html): string
    {
        $text = preg_replace('#<a [^>]*href="([^"]*)"[^>]*>(.*?)</a>#is', '$2 ($1)', $html) ?? $html;
        $text = preg_replace('#<li[^>]*>#i', '- ', $text) ?? $text;
        $text = preg_replace('#<br\s*/?>#i', "\n", $text) ?? $text;
        // Blocks get a blank line between them; list items and line breaks just a new line.
        $text = preg_replace('#</(p|h2|h3|blockquote|ul|ol)>#i', "\n\n", $text) ?? $text;
        $text = preg_replace('#</li>#i', "\n", $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace("/\n{3,}/", "\n\n", preg_replace("/[ \t]+\n/", "\n", $text) ?? $text) ?? $text);
    }

    /** Put a recipient's first name in (escaped for HTML), or "there" when we don't have one. */
    public static function personalise(string $html, ?string $firstName): string
    {
        $name = trim((string) $firstName);

        return str_ireplace(self::FIRST_NAME, e($name !== '' ? $name : 'there'), $html);
    }

    /**
     * A message written before rich text (plain text): keep its line breaks. The editor
     * always starts with a block (a paragraph, heading, list or quote); old text never
     * did, even when it had a "<" in it.
     */
    public static function fromPlain(string $text): string
    {
        return preg_match('#^\s*<(p|h2|h3|ul|ol|blockquote)[\s>]#i', $text) === 1 ? $text : nl2br(e($text), false);
    }
}
