<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Limits every image upload shares.
 */
final class Upload
{
    /**
     * The widest and tallest picture accepted. A small file can still unpack into
     * a huge bitmap and exhaust memory when it's re-encoded; 12,000 px a side
     * clears any phone camera (a 50 MP photo is about 8,200 px wide).
     */
    public const IMAGE_SIZE = 'dimensions:max_width=12000,max_height=12000';
}
