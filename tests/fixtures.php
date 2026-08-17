<?php

declare(strict_types=1);

namespace Media\Fixtures;

/**
 * Metadata records used by the tests, and the markup each one should produce.
 *
 * The records are written by hand rather than read from disk, so the suite does
 * not need any image files to run.
 */

/**
 * A wide photograph with a file for every registered size.
 *
 * The crops are listed out of width order on purpose: a record is written in
 * whatever order the upload pipeline finished, and the renderer sorts.
 */
function landscape_metadata(): array
{
    return [
        'file'   => '2026/04/harbour-lights.jpg',
        'width'  => 3000,
        'height' => 2000,
        'sizes'  => [
            'hero'      => ['file' => 'harbour-lights-2400x1000.jpg', 'width' => 2400, 'height' => 1000, 'mime-type' => 'image/jpeg'],
            'thumbnail' => ['file' => 'harbour-lights-400x400.jpg',   'width' => 400,  'height' => 400,  'mime-type' => 'image/jpeg'],
            'wide'      => ['file' => 'harbour-lights-1600x900.jpg',  'width' => 1600, 'height' => 900,  'mime-type' => 'image/jpeg'],
            'card'      => ['file' => 'harbour-lights-640x480.jpg',   'width' => 640,  'height' => 480,  'mime-type' => 'image/jpeg'],
            'content'   => ['file' => 'harbour-lights-1024x683.jpg',  'width' => 1024, 'height' => 683,  'mime-type' => 'image/jpeg'],
        ],
    ];
}

/**
 * The same photograph, plus a crop from a size that is no longer registered.
 *
 * Records are not rewritten when the registry changes, so this is what an old
 * upload looks like after a size is retired.
 */
function landscape_with_stale_crop_metadata(): array
{
    $meta = landscape_metadata();

    $meta['sizes']['legacy-banner'] = [
        'file'      => 'harbour-lights-1200x300.jpg',
        'width'     => 1200,
        'height'    => 300,
        'mime-type' => 'image/jpeg',
    ];

    return $meta;
}

/**
 * A tall photograph that only ever produced one crop.
 */
function single_crop_metadata(): array
{
    return [
        'file'   => '2026/05/studio-portrait.jpg',
        'width'  => 1200,
        'height' => 1600,
        'sizes'  => [
            'card' => ['file' => 'studio-portrait-640x480.jpg', 'width' => 640, 'height' => 480, 'mime-type' => 'image/jpeg'],
        ],
    ];
}

/**
 * An upload with no crops at all, small enough that none were generated.
 */
function no_crop_metadata(): array
{
    return [
        'file'   => '2026/06/flow-diagram.png',
        'width'  => 900,
        'height' => 600,
        'sizes'  => [],
    ];
}

/**
 * An upload whose filename carries characters that must be escaped.
 */
function punctuated_metadata(): array
{
    return [
        'file'   => '2026/07/sun & sea "01".jpg',
        'width'  => 2000,
        'height' => 1200,
        'sizes'  => [
            'card' => ['file' => 'sun & sea "01"-640x480.jpg',  'width' => 640,  'height' => 480, 'mime-type' => 'image/jpeg'],
            'wide' => ['file' => 'sun & sea "01"-1600x900.jpg', 'width' => 1600, 'height' => 900, 'mime-type' => 'image/jpeg'],
        ],
    ];
}

/**
 * The candidate list every landscape rendering should carry.
 */
function landscape_srcset(): string
{
    return implode(', ', [
        '/media/uploads/2026/04/harbour-lights-400x400.jpg 400w',
        '/media/uploads/2026/04/harbour-lights-640x480.jpg 640w',
        '/media/uploads/2026/04/harbour-lights-1024x683.jpg 1024w',
        '/media/uploads/2026/04/harbour-lights-1600x900.jpg 1600w',
        '/media/uploads/2026/04/harbour-lights-2400x1000.jpg 2400w',
    ]);
}

/**
 * The layout hint the renderer sends unless a caller replaces it.
 */
function default_sizes_attribute(): string
{
    return '(max-width: 1024px) 100vw, 1024px';
}

/**
 * Every rendering case: the record, the arguments, and the exact markup.
 *
 * Keyed by a label the test output can name when a case fails.
 */
function render_cases(): array
{
    $srcset = landscape_srcset();
    $sizes  = default_sizes_attribute();

    return [
        'leading image on the page' => [
            'meta'     => landscape_metadata(),
            'args'     => ['size' => 'wide', 'alt' => 'Harbour lights at dusk', 'class' => 'entry-image', 'position' => 1],
            'expected' => '<img src="/media/uploads/2026/04/harbour-lights-1600x900.jpg"'
                . ' srcset="' . $srcset . '"'
                . ' sizes="' . $sizes . '"'
                . ' width="1600" height="900" alt="Harbour lights at dusk" class="entry-image" fetchpriority="high" />',
        ],

        'later image on the page' => [
            'meta'     => landscape_metadata(),
            'args'     => ['size' => 'wide', 'alt' => 'Harbour lights at dusk', 'class' => 'entry-image', 'position' => 2],
            'expected' => '<img src="/media/uploads/2026/04/harbour-lights-1600x900.jpg"'
                . ' srcset="' . $srcset . '"'
                . ' sizes="' . $sizes . '"'
                . ' width="1600" height="900" alt="Harbour lights at dusk" class="entry-image" loading="lazy" />',
        ],

        'no size requested' => [
            'meta'     => landscape_metadata(),
            'args'     => ['alt' => 'Harbour lights at dusk', 'class' => 'entry-image', 'position' => 3],
            'expected' => '<img src="/media/uploads/2026/04/harbour-lights-1024x683.jpg"'
                . ' srcset="' . $srcset . '"'
                . ' sizes="' . $sizes . '"'
                . ' width="1024" height="683" alt="Harbour lights at dusk" class="entry-image" loading="lazy" />',
        ],

        'requested size is missing' => [
            'meta'     => landscape_metadata(),
            'args'     => ['size' => 'panorama', 'alt' => 'Harbour lights at dusk', 'class' => 'entry-image', 'position' => 2],
            'expected' => '<img src="/media/uploads/2026/04/harbour-lights-2400x1000.jpg"'
                . ' srcset="' . $srcset . '"'
                . ' sizes="' . $sizes . '"'
                . ' width="2400" height="1000" alt="Harbour lights at dusk" class="entry-image" loading="lazy" />',
        ],

        'crop from a retired size' => [
            'meta'     => landscape_with_stale_crop_metadata(),
            'args'     => ['size' => 'legacy-banner', 'alt' => 'Harbour lights at dusk', 'class' => 'entry-image', 'position' => 2],
            'expected' => '<img src="/media/uploads/2026/04/harbour-lights-2400x1000.jpg"'
                . ' srcset="' . $srcset . '"'
                . ' sizes="' . $sizes . '"'
                . ' width="2400" height="1000" alt="Harbour lights at dusk" class="entry-image" loading="lazy" />',
        ],

        'only one crop exists' => [
            'meta'     => single_crop_metadata(),
            'args'     => ['size' => 'wide', 'alt' => 'Studio portrait', 'class' => 'entry-image', 'position' => 3],
            'expected' => '<img src="/media/uploads/2026/05/studio-portrait-640x480.jpg"'
                . ' width="640" height="480" alt="Studio portrait" class="entry-image" loading="lazy" />',
        ],

        'no crops exist' => [
            'meta'     => no_crop_metadata(),
            'args'     => ['position' => 4],
            'expected' => '<img src="/media/uploads/2026/06/flow-diagram.png"'
                . ' width="900" height="600" alt="" loading="lazy" />',
        ],

        'values needing escaping' => [
            'meta'     => punctuated_metadata(),
            'args'     => [
                'size'     => 'wide',
                'alt'      => 'Rope & rigging on the "Sea Lark"',
                'class'    => 'entry-image is-featured',
                'position' => 2,
            ],
            'expected' => '<img src="/media/uploads/2026/07/sun &amp; sea &quot;01&quot;-1600x900.jpg"'
                . ' srcset="/media/uploads/2026/07/sun &amp; sea &quot;01&quot;-640x480.jpg 640w,'
                . ' /media/uploads/2026/07/sun &amp; sea &quot;01&quot;-1600x900.jpg 1600w"'
                . ' sizes="' . $sizes . '"'
                . ' width="1600" height="900" alt="Rope &amp; rigging on the &quot;Sea Lark&quot;"'
                . ' class="entry-image is-featured" loading="lazy" />',
        ],
    ];
}
