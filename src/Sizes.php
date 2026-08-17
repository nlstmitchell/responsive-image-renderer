<?php

declare(strict_types=1);

namespace Media;

/**
 * The registered crop sizes.
 *
 * This registry is the single answer to "which crops exist?". The upload
 * pipeline generates a file for each entry, and the renderer will only ever
 * point at a crop whose size name appears here. A size that is removed from
 * this list stops being used even if old files are still on disk.
 *
 * `crop => true`  the file is cut to exactly width x height.
 * `crop => false` the file is scaled to fit inside width x height, so the
 *                 generated file is usually shorter than the height given.
 */

/**
 * Every registered size, keyed by size name.
 *
 * Guarantees: entries are ordered narrowest first, and each one has an integer
 * `width`, an integer `height` and a boolean `crop`.
 */
function registered_sizes(): array
{
    return [
        'thumbnail' => ['width' => 400,  'height' => 400,  'crop' => true],
        'card'      => ['width' => 640,  'height' => 480,  'crop' => true],
        'content'   => ['width' => 1024, 'height' => 768,  'crop' => false],
        'wide'      => ['width' => 1600, 'height' => 900,  'crop' => true],
        'hero'      => ['width' => 2400, 'height' => 1000, 'crop' => true],
    ];
}

/**
 * Whether a size name is one this registry declares.
 */
function size_is_registered(string $name): bool
{
    return isset(registered_sizes()[$name]);
}

/**
 * The declared dimensions of a size, or null when the name is not registered.
 *
 * These are the dimensions that were *asked for*. The file that was actually
 * generated may be smaller, so read a real crop record when you need the
 * numbers to put in a `width` or `height` attribute.
 */
function size_dimensions(string $name): ?array
{
    return registered_sizes()[$name] ?? null;
}

/**
 * The size used when a caller does not name one.
 */
function default_size(): string
{
    return 'content';
}
