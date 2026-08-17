<?php

declare(strict_types=1);

namespace Media;

require_once __DIR__ . '/Sizes.php';
require_once __DIR__ . '/Metadata.php';
require_once __DIR__ . '/Loading.php';

/**
 * Rendering an uploaded image as markup.
 */

/** The layout hint sent with every image unless a caller replaces it. */
const DEFAULT_SIZES_ATTRIBUTE = '(max-width: 1024px) 100vw, 1024px';

/**
 * Renders one image element for an upload record.
 *
 * Accepted arguments, all optional:
 *
 *   size      registered size to point `src` at   (default: the default size)
 *   alt       alternative text                    (default: '')
 *   class     class attribute value               (default: none)
 *   position  1-based position on the page        (default: 1)
 *   sizes     layout hint for the browser         (default: the constant above)
 *   base_url  where uploads are served from       (default: the record default)
 *
 * Guarantees: the markup is a single element; `src` always resolves to a file
 * that exists in the record; `width` and `height` always describe the file in
 * `src`; `alt` is always present, empty if that is what the caller passed; and
 * every value is escaped for an HTML attribute.
 */
function render_image(array $meta, array $args = []): string
{
    $size     = (string) ($args['size'] ?? default_size());
    $alt      = (string) ($args['alt'] ?? '');
    $class    = (string) ($args['class'] ?? '');
    $position = (int) ($args['position'] ?? 1);
    $sizes    = (string) ($args['sizes'] ?? DEFAULT_SIZES_ATTRIBUTE);
    $base_url = (string) ($args['base_url'] ?? DEFAULT_BASE_URL);

    $crop   = select_crop($meta, $size);
    $srcset = srcset_value($meta, $base_url);

    $attributes = ['src' => crop_url($meta, $crop, $base_url)];

    if ($srcset !== '') {
        $attributes['srcset'] = $srcset;
        $attributes['sizes']  = $sizes;
    }

    $attributes['width']  = (string) $crop['width'];
    $attributes['height'] = (string) $crop['height'];
    $attributes['alt']    = $alt;

    if ($class !== '') {
        $attributes['class'] = $class;
    }

    $attributes = array_merge($attributes, loading_attributes($position));

    return '<img ' . attributes_to_html($attributes) . ' />';
}

/**
 * Joins attributes into the inside of a tag, in the order given.
 *
 * Guarantees: every value is escaped, so a caller may pass raw text.
 */
function attributes_to_html(array $attributes): string
{
    $parts = [];

    foreach ($attributes as $name => $value) {
        $parts[] = $name . '="' . escape((string) $value) . '"';
    }

    return implode(' ', $parts);
}

/**
 * Escapes a value for use inside a double-quoted attribute.
 */
function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
