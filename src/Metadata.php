<?php

declare(strict_types=1);

namespace Media;

require_once __DIR__ . '/Sizes.php';

/**
 * The metadata record kept for one uploaded image, and the lookups over it.
 *
 * A record names the original file relative to the upload root, the pixel size
 * of that original, and one entry per generated crop:
 *
 *     [
 *         'file'   => '2026/04/harbour-lights.jpg',
 *         'width'  => 3000,
 *         'height' => 2000,
 *         'sizes'  => [
 *             'card' => ['file' => 'harbour-lights-640x480.jpg', 'width' => 640,
 *                        'height' => 480, 'mime-type' => 'image/jpeg'],
 *         ],
 *     ]
 *
 * Every generated file sits in the same directory as the original, which is why
 * a crop entry carries a bare filename while the original carries a path.
 *
 * A record is written once, at upload time, and is never rewritten when the
 * size registry changes. It can therefore hold crops for sizes that no longer
 * exist; the registry, not the record, decides what may be used.
 */

/** Where uploads are served from when a caller does not name a base. */
const DEFAULT_BASE_URL = '/media/uploads';

/** The name given to the record that stands in for the un-cropped original. */
const ORIGINAL_CROP = 'original';

/**
 * One crop entry in the shape the rest of the code expects.
 *
 * Guarantees: the five keys are always present, the dimensions are integers,
 * and missing input becomes an empty string or a zero rather than an error.
 */
function normalise_crop(string $name, array $crop): array
{
    return [
        'name'      => $name,
        'file'      => (string) ($crop['file'] ?? ''),
        'width'     => (int) ($crop['width'] ?? 0),
        'height'    => (int) ($crop['height'] ?? 0),
        'mime-type' => (string) ($crop['mime-type'] ?? ''),
    ];
}

/**
 * The original upload, described in the same shape as a crop.
 *
 * Its mime type is empty unless the record happens to carry one, because a
 * record stores a mime type per generated crop rather than for the original.
 */
function original_crop(array $meta): array
{
    return normalise_crop(ORIGINAL_CROP, [
        'file'      => basename((string) ($meta['file'] ?? '')),
        'width'     => $meta['width'] ?? 0,
        'height'    => $meta['height'] ?? 0,
        'mime-type' => $meta['mime-type'] ?? '',
    ]);
}

/**
 * Every crop in a record that a currently registered size accounts for.
 *
 * Guarantees: crops left behind by sizes that are no longer registered are
 * dropped, each record is normalised, and the list runs narrowest first.
 */
function registered_crops(array $meta): array
{
    $crops = [];

    foreach (($meta['sizes'] ?? []) as $name => $crop) {
        if (!is_array($crop) || !size_is_registered((string) $name)) {
            continue;
        }

        $crops[] = normalise_crop((string) $name, $crop);
    }

    usort($crops, static fn (array $a, array $b): int => $a['width'] <=> $b['width']);

    return $crops;
}

/**
 * Whether a record holds a usable crop under this size name.
 */
function has_crop(array $meta, string $size): bool
{
    foreach (registered_crops($meta) as $crop) {
        if ($crop['name'] === $size) {
            return true;
        }
    }

    return false;
}

/**
 * The crop to point `src` at for a requested size.
 *
 * Guarantees: always returns a usable record. The requested size wins; failing
 * that the widest registered crop is used; failing that the original itself.
 */
function select_crop(array $meta, string $size): array
{
    $crops = registered_crops($meta);

    foreach ($crops as $crop) {
        if ($crop['name'] === $size) {
            return $crop;
        }
    }

    if ($crops !== []) {
        return $crops[count($crops) - 1];
    }

    return original_crop($meta);
}

/**
 * The directory part of an upload path, with a trailing slash.
 *
 * Returns an empty string when the file sits at the root of the upload area.
 */
function directory_prefix(string $file): string
{
    $directory = trim(dirname($file), '/');

    return ($directory === '' || $directory === '.') ? '' : $directory . '/';
}

/**
 * The URL a crop record is served from.
 *
 * Works for the original too, because its record carries the bare filename and
 * the original shares a directory with its crops.
 */
function crop_url(array $meta, array $crop, string $base_url = DEFAULT_BASE_URL): string
{
    $directory = directory_prefix((string) ($meta['file'] ?? ''));

    return rtrim($base_url, '/') . '/' . $directory . (string) ($crop['file'] ?? '');
}

/**
 * The URL of the original, un-cropped upload.
 */
function original_url(array $meta, string $base_url = DEFAULT_BASE_URL): string
{
    return crop_url($meta, original_crop($meta), $base_url);
}

/**
 * The `srcset` value for a record: every registered crop as a width candidate.
 *
 * Guarantees: candidates run narrowest first, and an empty string comes back
 * when there are fewer than two of them, since a browser given a single
 * candidate has no choice to make.
 */
function srcset_value(array $meta, string $base_url = DEFAULT_BASE_URL): string
{
    $crops = registered_crops($meta);

    if (count($crops) < 2) {
        return '';
    }

    $candidates = [];

    foreach ($crops as $crop) {
        $candidates[] = crop_url($meta, $crop, $base_url) . ' ' . $crop['width'] . 'w';
    }

    return implode(', ', $candidates);
}
