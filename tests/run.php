<?php

declare(strict_types=1);

/**
 * The whole test suite. Run it with:
 *
 *     php tests/run.php
 *
 * There is no test discovery: the list of calls at the bottom of this file is
 * the suite. A check function that is defined but never called does nothing.
 */

require_once __DIR__ . '/../src/Img.php';
require_once __DIR__ . '/../src/Breakpoints.php';
require_once __DIR__ . '/fixtures.php';

$checks   = 0;
$failures = [];

/**
 * Records one check. Values must match exactly, including type.
 */
function assert_same(mixed $actual, mixed $expected, string $label): void
{
    global $checks, $failures;

    $checks++;

    if ($actual === $expected) {
        return;
    }

    $failures[] = $label
        . "\n    expected: " . describe($expected)
        . "\n    actual:   " . describe($actual);
}

/**
 * A value in a form that can be read in failure output.
 */
function describe(mixed $value): string
{
    return is_string($value) ? $value : var_export($value, true);
}

/**
 * The size registry answers what exists and how big it was asked to be.
 */
function check_size_registry(): void
{
    $names = array_keys(Media\registered_sizes());
    $hero  = ['width' => 2400, 'height' => 1000, 'crop' => true];

    assert_same($names, ['thumbnail', 'card', 'content', 'wide', 'hero'], 'registry: narrowest first');
    assert_same(Media\size_is_registered('wide'), true, 'registry: wide is registered');
    assert_same(Media\size_is_registered('legacy-banner'), false, 'registry: legacy-banner is not');
    assert_same(Media\size_dimensions('hero'), $hero, 'registry: hero dimensions');
}

/**
 * The breakpoints ascend and name sizes that exist.
 */
function check_breakpoints(): void
{
    $bands      = Media\breakpoints();
    $registered = array_map(static fn (array $band): bool => Media\size_is_registered($band['size']), $bands);

    assert_same($registered, [true, true, true, true], 'breakpoints: every band names a registered size');
    assert_same(array_column($bands, 'max_width'), [640, 768, 1024, 1600], 'breakpoints: bounds ascend');
    assert_same(array_column($bands, 'size'), ['thumbnail', 'card', 'content', 'wide'], 'breakpoints: sizes narrowest first');

    assert_same(Media\size_for_width(640), 'thumbnail', 'breakpoints: a width on the bound fits inside it');
    assert_same(Media\size_for_width(641), 'card', 'breakpoints: one past the bound moves up');
    assert_same(Media\size_for_width(1600), 'wide', 'breakpoints: the widest bound is inclusive');
    assert_same(Media\size_for_width(1920), null, 'breakpoints: wider than every bound belongs to no band');
    assert_same(Media\breakpoint_for_width(2000), null, 'breakpoints: and names no band at all');

    // The bounds overlap on purpose: a narrow viewport sits at or below several
    // of them, so which one applies depends on which is consulted first.
    assert_same(Media\size_for_width(500), 'thumbnail', 'breakpoints: the narrowest matching band wins');

    assert_same(Media\breakpoint_media_query($bands[0]), '(max-width: 640px)', 'breakpoints: narrowest query');
    assert_same(Media\breakpoint_media_query($bands[3]), '(max-width: 1600px)', 'breakpoints: widest query');
}


/**
 * Choosing which file to point at, including every fallback.
 */
function check_crop_selection(): void
{
    $landscape = Media\Fixtures\landscape_metadata();
    $stale     = Media\Fixtures\landscape_with_stale_crop_metadata();
    $single    = Media\Fixtures\single_crop_metadata();
    $bare      = Media\Fixtures\no_crop_metadata();
    $card      = Media\select_crop($landscape, 'card');

    assert_same($card['file'], 'harbour-lights-640x480.jpg', 'crop: a size that exists is used');
    assert_same($card['mime-type'], 'image/jpeg', 'crop: the record carries the file type');
    assert_same(Media\select_crop($landscape, 'panorama')['name'], 'hero', 'crop: unknown size falls back to widest');
    assert_same(Media\has_crop($landscape, 'wide'), true, 'crop: wide is available');
    assert_same(Media\has_crop($stale, 'legacy-banner'), false, 'crop: a retired size never is');
    assert_same(Media\select_crop($single, 'wide')['name'], 'card', 'crop: the only crop is used');
    assert_same(
        Media\select_crop($bare, 'wide'),
        ['name' => 'original', 'file' => 'flow-diagram.png', 'width' => 900, 'height' => 600, 'mime-type' => ''],
        'crop: with no crops the original is used'
    );
    assert_same(Media\original_url($bare), '/media/uploads/2026/06/flow-diagram.png', 'crop: original URL');
}

/**
 * The candidate list offered to the browser.
 */
function check_srcset(): void
{
    $expected = Media\Fixtures\landscape_srcset();

    assert_same(Media\srcset_value(Media\Fixtures\landscape_metadata()), $expected, 'srcset: narrowest first');
    assert_same(Media\srcset_value(Media\Fixtures\landscape_with_stale_crop_metadata()), $expected, 'srcset: retired size left out');
    assert_same(Media\srcset_value(Media\Fixtures\single_crop_metadata()), '', 'srcset: one candidate is no choice');
    assert_same(Media\srcset_value(Media\Fixtures\no_crop_metadata()), '', 'srcset: no crops, no candidates');
}

/**
 * How position on the page decides the fetching attributes.
 */
function check_loading_strategy(): void
{
    assert_same(Media\loading_attributes(1), ['fetchpriority' => 'high'], 'loading: the leading image is prioritised');
    assert_same(Media\loading_attributes(2), ['loading' => 'lazy'], 'loading: the second image is deferred');
    assert_same(Media\is_priority_position(1), true, 'loading: position 1 leads the page');
    assert_same(Media\is_priority_position(2), false, 'loading: position 2 does not');
}

/**
 * The rendered markup, compared against the expected string for each case.
 */
function check_rendered_markup(): void
{
    foreach (Media\Fixtures\render_cases() as $label => $case) {
        assert_same(Media\render_image($case['meta'], $case['args']), $case['expected'], 'render: ' . $label);
    }

    $landscape = Media\render_image(Media\Fixtures\landscape_metadata(), ['size' => 'wide', 'position' => 1]);
    $bare      = Media\render_image(Media\Fixtures\no_crop_metadata(), ['position' => 2]);

    assert_same(str_contains($landscape, ' width="1600" height="900"'), true, 'render: dimensions match the file in src');
    assert_same(str_contains($bare, ' alt=""'), true, 'render: alt is present even when empty');
    assert_same(str_contains($bare, ' class='), false, 'render: class is left out when none is given');
}

check_size_registry();
check_breakpoints();
check_crop_selection();
check_srcset();
check_loading_strategy();
check_rendered_markup();

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo 'FAIL  ' . $failure . "\n";
    }

    printf("image-render: %d of %d checks failed.\n", count($failures), $checks);
    exit(1);
}

printf("image-render: all %d checks passed.\n", $checks);
exit(0);
