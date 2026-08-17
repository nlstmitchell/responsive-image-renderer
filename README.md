# Responsive image rendering

A small, dependency-free renderer for uploaded images. It takes the metadata record written for an
upload and returns the markup for one image, with the right file in `src`, the right candidates in
`srcset`, and the right fetching behaviour for where the image sits on the page.

PHP 8 standard library only. No packages, no build step, no framework. Every file is loaded with
plain `require_once`.

## Layout

```
src/Metadata.php      the upload record: lookups, crop selection, URL building
src/Sizes.php         the registered crop sizes
src/Breakpoints.php   the screen-width ranges and the size each one is meant to show
src/Img.php           render_image()
src/Loading.php       loading and fetchpriority, decided by position on the page
tests/run.php         the test suite
tests/fixtures.php    metadata records and the exact markup each should produce
```

Everything lives in the `Media` namespace, so calls read `Media\render_image(...)`.

## The upload record

One array per uploaded image, written once at upload time:

```php
[
    'file'   => '2026/04/harbour-lights.jpg',   // path, relative to the upload root
    'width'  => 3000,                           // the original, in pixels
    'height' => 2000,
    'sizes'  => [
        'card' => [
            'file'      => 'harbour-lights-640x480.jpg',   // bare filename
            'width'     => 640,
            'height'    => 480,
            'mime-type' => 'image/jpeg',
        ],
        // one entry per generated crop
    ],
]
```

Generated files sit beside the original, which is why a crop carries a bare filename and only the
original carries a path.

A record is **not** rewritten when the size registry changes, so it can hold crops for sizes that no
longer exist. `src/Sizes.php` is the authority on what may be used: a crop whose size name is not
registered is ignored everywhere, in `src` and in `srcset` alike.

## Registered sizes

| Name        | Width | Height | Cut to fit |
| ----------- | ----- | ------ | ---------- |
| `thumbnail` | 400   | 400    | yes        |
| `card`      | 640   | 480    | yes        |
| `content`   | 1024  | 768    | no         |
| `wide`      | 1600  | 900    | yes        |
| `hero`      | 2400  | 1000   | yes        |

`content` is the default when a caller does not name a size. A size with `crop => false` is scaled
to fit inside the box rather than cut to it, so the file it produces is usually shorter than the
height shown here — always read the dimensions off the crop record, never off the registry.

## Breakpoints

`src/Breakpoints.php` declares the screen-width ranges the layout is built around and the registered
size each range is meant to show. The ranges are contiguous, ordered narrowest first, and the last
one is open ended.

| Band     | Viewport width | Size        |
| -------- | -------------- | ----------- |
| `small`  | 0 – 639        | `thumbnail` |
| `medium` | 640 – 1023     | `card`      |
| `large`  | 1024 – 1599    | `wide`      |
| `xlarge` | 1600 and up    | `hero`      |

`breakpoint_for_width()` resolves a viewport width to a band, `size_for_width()` to the size that
band wants, and `breakpoint_media_query()` writes a band as a CSS media condition
(`(min-width: 640px) and (max-width: 1023px)`).

## What `render_image()` guarantees

```php
Media\render_image(array $meta, array $args = []): string
```

Arguments, all optional: `size`, `alt`, `class`, `position`, `sizes`, `base_url`.

It returns a single image element:

```html
<img src="/media/uploads/2026/04/harbour-lights-1600x900.jpg" srcset="/media/uploads/2026/04/harbour-lights-400x400.jpg 400w, /media/uploads/2026/04/harbour-lights-640x480.jpg 640w, /media/uploads/2026/04/harbour-lights-1024x683.jpg 1024w, /media/uploads/2026/04/harbour-lights-1600x900.jpg 1600w, /media/uploads/2026/04/harbour-lights-2400x1000.jpg 2400w" sizes="(max-width: 1024px) 100vw, 1024px" width="1600" height="900" alt="Harbour lights at dusk" class="entry-image" fetchpriority="high" />
```

The contract:

- **`src` always resolves to a file the record holds.** The requested size wins; if the record has no
  crop under that name, the widest registered crop is used; if the record has no registered crops at
  all, the original itself is used. The function never returns a broken URL for a valid record.
- **`srcset` lists every registered crop**, narrowest first, as width descriptors. It is left out
  when there are fewer than two candidates, because a browser given one candidate has no choice to
  make. `sizes` is left out with it — a layout hint with nothing to choose between is noise.
- **`width` and `height` describe the file in `src`**, not the size that was asked for. They are
  always present, so the browser can reserve the space before the file arrives.
- **`alt` is always present**, empty if that is what the caller passed. An empty `alt` says
  "decorative"; a missing one says nothing.
- **`class` is left out entirely when no class is given.**
- **Exactly one of `loading="lazy"` and `fetchpriority="high"` is set**, decided by `position`.
  Position 1 is the leading content image and is prioritised; everything after it is deferred. The
  two are never combined, because deferring an image and prioritising it contradict each other.
- **Every attribute value is escaped** with `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`, so
  filenames and alternative text carrying `&` or `"` are safe to pass in raw.

Attribute order is fixed: `src`, `srcset`, `sizes`, `width`, `height`, `alt`, `class`, and the
fetching attribute last.

## Running the tests

```bash
php tests/run.php
```

Prints `image-render: all 40 checks passed.` and exits 0. On failure it prints one `FAIL` block per
check, naming the check and showing expected against actual, then exits 1.

No test framework. `assert_same($actual, $expected, $label)` records a check, the counter and the
failure list live at the top of the file, and the list of calls at the bottom **is** the suite —
there is no discovery, so a check function that is never called silently does nothing.

The suite covers the size registry, the breakpoint bands, every crop-selection fallback, `srcset`
construction and ordering, the loading strategy, and eight full markup comparisons: the leading
image, a later one, no size requested, a missing size, a crop from a retired size, an upload with
one crop, an upload with none, and values that need escaping.

## Licence

MIT. See [LICENSE](LICENSE).
