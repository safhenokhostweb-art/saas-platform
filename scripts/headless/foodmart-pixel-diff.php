<?php

$root = 'C:/laragon/www/saas-platform/storage/app/theme-baselines/foodmart';
$phase = getenv('FOODMART_QA_PHASE') ?: 'phase2c';
$viewports = ['mobile-390', 'tablet-768', 'breakpoint-991', 'desktop-1200', 'large-desktop-1440'];
$sections = ['header', 'hero', 'category-carousel', 'brand-carousel', 'trending-products', 'promo-banners', 'best-selling', 'newsletter', 'most-popular', 'just-arrived', 'blog', 'app-promo', 'trust-badges', 'footer'];

function comparePng(string $baseline, string $candidate, int $tolerance): array
{
    [$aw, $ah] = getimagesize($baseline);
    [$bw, $bh] = getimagesize($candidate);
    if ($aw !== $bw || $ah !== $bh) {
        return ['dimensionMismatch' => true, 'baseline' => "{$aw}x{$ah}", 'candidate' => "{$bw}x{$bh}"];
    }
    $a = imagecreatefrompng($baseline);
    $b = imagecreatefrompng($candidate);
    $different = 0;
    for ($y = 0; $y < $ah; $y++) {
        for ($x = 0; $x < $aw; $x++) {
            $ac = imagecolorat($a, $x, $y);
            $bc = imagecolorat($b, $x, $y);
            $delta = max(abs((($ac >> 16) & 0xff) - (($bc >> 16) & 0xff)), abs((($ac >> 8) & 0xff) - (($bc >> 8) & 0xff)), abs(($ac & 0xff) - ($bc & 0xff)));
            if ($delta > $tolerance) $different++;
        }
    }
    imagedestroy($a);
    imagedestroy($b);
    $total = $aw * $ah;
    return ['width' => $aw, 'height' => $ah, 'diffPixels' => $different, 'totalPixels' => $total, 'diffPercent' => round($different * 100 / $total, 4)];
}

$results = ['definition' => ['raw' => 'per-channel maximum delta > 0', 'normalized' => 'per-channel maximum delta > 12'], 'fullPage' => [], 'sections' => []];
foreach ($viewports as $viewport) {
    $results['fullPage'][$viewport] = [
        'rawTolerance0' => comparePng("{$root}/original/{$viewport}/full-page.png", "{$root}/{$phase}/{$viewport}/full-page.png", 0),
        'normalizedTolerance12' => comparePng("{$root}/original/{$viewport}/full-page.png", "{$root}/{$phase}/{$viewport}/full-page.png", 12),
    ];
    foreach ($sections as $section) {
        $results['sections'][$viewport][$section] = [
            'rawTolerance0' => comparePng("{$root}/original/{$viewport}/sections/{$section}.png", "{$root}/{$phase}/{$viewport}/sections/{$section}.png", 0),
            'normalizedTolerance12' => comparePng("{$root}/original/{$viewport}/sections/{$section}.png", "{$root}/{$phase}/{$viewport}/sections/{$section}.png", 12),
        ];
    }
}
file_put_contents("{$root}/{$phase}/pixeldiff-results.json", json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo json_encode($results['fullPage'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
