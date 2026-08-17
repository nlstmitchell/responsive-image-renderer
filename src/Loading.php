<?php

declare(strict_types=1);

namespace Media;

/**
 * How an image should be fetched, decided by where it sits on the page.
 *
 * Positions are 1-based and count content images in document order. The first
 * one is very likely to be visible before the reader scrolls, so it is fetched
 * eagerly and at high priority; everything after it is deferred.
 */

/** The position that is treated as the page's leading image. */
const PRIORITY_POSITION = 1;

/**
 * Whether a position is the page's leading image.
 *
 * Positions at or below the first are treated as leading, so a caller that
 * forgets to number its images does not accidentally defer the top of the page.
 */
function is_priority_position(int $position): bool
{
    return $position <= PRIORITY_POSITION;
}

/**
 * The fetching attributes for an image at a given position.
 *
 * Guarantees: exactly one attribute comes back — `fetchpriority` for the
 * leading image, `loading` for every other one. The two are never combined,
 * because deferring an image and prioritising it contradict each other.
 */
function loading_attributes(int $position): array
{
    if (is_priority_position($position)) {
        return ['fetchpriority' => 'high'];
    }

    return ['loading' => 'lazy'];
}
