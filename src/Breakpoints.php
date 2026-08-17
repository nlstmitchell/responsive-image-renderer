<?php

declare(strict_types=1);

namespace Media;

require_once __DIR__ . '/Sizes.php';

/**
 * The screen widths the layout is built around, and the registered size each
 * one is meant to show.
 *
 * A breakpoint is an upper bound on viewport width in CSS pixels: it says
 * "at this width or narrower, show this size". Bounds are listed narrowest
 * first, and they deliberately overlap — a 500px viewport is at or below every
 * bound in the list. Anything wider than the widest bound falls through to the
 * original file.
 */

/**
 * Every breakpoint, narrowest first.
 *
 * Guarantees: bounds ascend, and every `size` names an entry in the registry.
 */
function breakpoints(): array
{
    return [
        ['name' => 'small',  'max_width' => 640,  'size' => 'thumbnail'],
        ['name' => 'medium', 'max_width' => 768,  'size' => 'card'],
        ['name' => 'large',  'max_width' => 1024, 'size' => 'content'],
        ['name' => 'xlarge', 'max_width' => 1600, 'size' => 'wide'],
    ];
}

/**
 * The narrowest breakpoint a viewport width still fits inside.
 *
 * Guarantees: returns null when the viewport is wider than every bound, which
 * is the case the original file covers.
 */
function breakpoint_for_width(int $viewport_width): ?array
{
    foreach (breakpoints() as $breakpoint) {
        if ($viewport_width <= $breakpoint['max_width']) {
            return $breakpoint;
        }
    }

    return null;
}

/**
 * The size a viewport width is meant to be shown at, or null when it is wider
 * than every breakpoint.
 */
function size_for_width(int $viewport_width): ?string
{
    $breakpoint = breakpoint_for_width($viewport_width);

    return $breakpoint === null ? null : $breakpoint['size'];
}

/**
 * A breakpoint written as a CSS media condition.
 */
function breakpoint_media_query(array $breakpoint): string
{
    return '(max-width: ' . (int) $breakpoint['max_width'] . 'px)';
}
