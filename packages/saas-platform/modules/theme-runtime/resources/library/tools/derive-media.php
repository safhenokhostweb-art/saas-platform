<?php

/**
 * Canvas overhaul Unit 5 (ADR-075): derives the template library's media
 * set from the committed photographic sources of saas-platform's
 * database/seeders/assets/universal-multipurpose-qa. The output files are
 * committed under resources/library/media; rerun this only when a source or
 * a crop changes:
 *
 *   php resources/library/tools/derive-media.php <source-dir>
 *
 * Every crop is a deterministic cover crop (focus x/y in 0..1) to the exact
 * target size, encoded as WebP quality 80.
 */

$sourceDir = rtrim($argv[1] ?? '', '/\\');
if ($sourceDir === '' || !is_dir($sourceDir)) {
    fwrite(STDERR, "usage: php derive-media.php <source-dir>\n");
    exit(1);
}
$outDir = dirname(__DIR__) . '/media';
@mkdir($outDir, 0775, true);

// name => [source, width, height, region [x, y, w, h] in source pixels or null, focus-x, focus-y]
$specs = [
    'office-hero.webp'      => ['hero-business.jpg', 1600, 900, null, 0.5, 0.5],
    'team-collaboration.webp' => ['business-team.jpg', 1200, 800, null, 0.5, 0.5],
    'mobile-store.webp'     => ['mobile-commerce.jpg', 1200, 800, null, 0.6, 0.5],
    'tech-products.webp'    => ['technology-products.jpg', 1200, 800, null, 0.5, 0.5],
    'mountain-panorama.webp' => ['mountain-trust.jpg', 1600, 650, null, 0.5, 0.5],
    'portrait-1.webp'       => ['team-contact-sheet.jpg', 480, 600, [0, 0, 600, 600], 0.5, 0.3],
    'portrait-2.webp'       => ['team-contact-sheet.jpg', 480, 600, [600, 0, 600, 600], 0.5, 0.3],
    'portrait-3.webp'       => ['team-contact-sheet.jpg', 480, 600, [0, 600, 600, 600], 0.5, 0.3],
    'portrait-4.webp'       => ['team-contact-sheet.jpg', 480, 600, [600, 600, 600, 600], 0.5, 0.3],
];

foreach ($specs as $name => [$file, $width, $height, $region, $fx, $fy]) {
    $source = imagecreatefromjpeg("{$sourceDir}/{$file}");
    if ($source === false) {
        fwrite(STDERR, "cannot read {$file}\n");
        exit(1);
    }
    [$rx, $ry, $rw, $rh] = $region ?? [0, 0, imagesx($source), imagesy($source)];

    // Largest crop of the target ratio inside the region, centred on the focus.
    $ratio = $width / $height;
    $cw = $rw / $rh > $ratio ? (int) round($rh * $ratio) : $rw;
    $ch = $rw / $rh > $ratio ? $rh : (int) round($rw / $ratio);
    $cx = $rx + (int) round(max(0, min($rw - $cw, $rw * $fx - $cw / 2)));
    $cy = $ry + (int) round(max(0, min($rh - $ch, $rh * $fy - $ch / 2)));

    $target = imagecreatetruecolor($width, $height);
    imagecopyresampled($target, $source, 0, 0, $cx, $cy, $width, $height, $cw, $ch);
    imagewebp($target, "{$outDir}/{$name}", 80);
    printf("%-26s %4dx%-4d %6.1f KB\n", $name, $width, $height, filesize("{$outDir}/{$name}") / 1024);
}
